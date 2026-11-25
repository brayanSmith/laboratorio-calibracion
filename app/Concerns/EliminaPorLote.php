<?php

namespace App\Concerns;

use App\Enums\TenantPermission;
use App\Http\Requests\EliminarPorLoteRequest;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Throwable;

/**
 * Eliminar varios registros de una tabla a la vez. Cada registro pasa por las mismas reglas
 * que al eliminarlo uno por uno (eliminarRegistro(), que también usa destroy()): los que no
 * se pueden eliminar se saltan y se informa cuántos y por qué. El aviso trae el detalle de
 * qué registros los tienen en uso (dependenciasDe()), para que el usuario pueda revisarlos.
 *
 * @phpstan-type Dependencia array{titulo: string, total: int, registros: list<string>}
 * @phpstan-type DetalleRegistro array{nombre: string, motivo: string, dependencias: list<Dependencia>}
 */
trait EliminaPorLote
{
    /** Cuántos registros se listan por cada dependencia en el detalle (el resto solo se cuenta). */
    private static int $registrosPorDependencia = 10;

    /** Cuántos registros no eliminados de un lote se detallan. */
    private static int $registrosEnDetalle = 50;

    /**
     * Modelo de los registros de la tabla.
     *
     * @return class-string<Model>
     */
    abstract protected function modeloPorLote(): string;

    /**
     * Permiso, además del de eliminar del módulo, para eliminar varios a la vez.
     */
    abstract protected function permisoEliminarPorLote(): TenantPermission;

    /**
     * Elimina el registro si se puede; si no, devuelve el motivo y no lo toca.
     */
    abstract protected function eliminarRegistro(Model $registro): ?string;

    /**
     * Registros que tienen en uso al registro y por eso no se puede eliminar. Cada módulo
     * lista los de su regla con dependencia(); los vacíos se descartan.
     *
     * @return list<Dependencia|null>
     */
    protected function dependenciasDe(Model $registro): array
    {
        return [];
    }

    /**
     * Cómo se nombra el registro en el detalle: su código y su nombre, si los tiene.
     */
    protected function etiquetaDe(Model $registro): string
    {
        $partes = array_filter([$registro->getAttribute('codigo'), $registro->getAttribute('nombre')]);

        return $partes === [] ? '#'.$registro->getKey() : implode(' · ', $partes);
    }

    /**
     * Una dependencia del detalle: cuántos registros de la consulta hay y los primeros, con su etiqueta.
     *
     * @param  Relation<Model, Model, mixed>|Builder<Model>  $consulta
     * @param  Closure(Model): ?string  $etiqueta
     * @return Dependencia|null
     */
    protected static function dependencia(string $titulo, Relation|Builder $consulta, Closure $etiqueta): ?array
    {
        $total = $consulta->count();

        if ($total === 0) {
            return null;
        }

        return [
            'titulo' => $titulo,
            'total' => $total,
            'registros' => $consulta->limit(self::$registrosPorDependencia)->get()
                ->map(fn (Model $dependiente): string => $etiqueta($dependiente) ?? '#'.$dependiente->getKey())
                ->values()
                ->all(),
        ];
    }

    /**
     * Aviso de error de un registro que no se pudo eliminar, con el detalle de qué lo tiene en uso.
     *
     * @return array{type: string, message: string, detalle: array{registros: list<DetalleRegistro>}}
     */
    protected function toastNoEliminable(Model $registro, string $motivo): array
    {
        return [
            'type' => 'error',
            'message' => $motivo,
            'detalle' => ['registros' => [$this->detalleRegistro($registro, $motivo)]],
        ];
    }

    /**
     * Elimina los registros elegidos del tenant del usuario.
     */
    public function destroyLote(EliminarPorLoteRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can($this->permisoEliminarPorLote()->value), 403);

        $registros = ($this->modeloPorLote())::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereIn('id', $request->validated('ids'))
            ->get();

        $eliminados = 0;
        /** @var array<string, int> $motivos motivo => cuántos registros no se eliminaron por él */
        $motivos = [];
        /** @var list<DetalleRegistro> $noEliminados */
        $noEliminados = [];

        foreach ($registros as $registro) {
            $motivo = Gate::denies('delete', $registro)
                ? __('No tienes permiso para eliminarlo.')
                : $this->eliminarEnTransaccion($registro);

            if ($motivo === null) {
                $eliminados++;

                continue;
            }

            $motivos[$motivo] = ($motivos[$motivo] ?? 0) + 1;

            if (count($noEliminados) < self::$registrosEnDetalle) {
                $noEliminados[] = $this->detalleRegistro($registro, $motivo);
            }
        }

        Inertia::flash('toast', $this->resumenLote($eliminados, $motivos, $noEliminados));

        return back();
    }

    private function eliminarEnTransaccion(Model $registro): ?string
    {
        try {
            return DB::transaction(fn (): ?string => $this->eliminarRegistro($registro));
        } catch (Throwable $excepcion) {
            report($excepcion);

            return __('No se pudo eliminar por un error inesperado.');
        }
    }

    /**
     * @return DetalleRegistro
     */
    private function detalleRegistro(Model $registro, string $motivo): array
    {
        return [
            'nombre' => $this->etiquetaDe($registro),
            'motivo' => $motivo,
            'dependencias' => array_values(array_filter($this->dependenciasDe($registro))),
        ];
    }

    /**
     * @param  array<string, int>  $motivos
     * @param  list<DetalleRegistro>  $noEliminados
     * @return array{type: string, message: string, detalle?: array{registros: list<DetalleRegistro>}}
     */
    private function resumenLote(int $eliminados, array $motivos, array $noEliminados): array
    {
        $omitidos = array_sum($motivos);
        $mensaje = trans_choice('{0} No se eliminó ningún registro.|{1} Se eliminó 1 registro.|[2,*] Se eliminaron :count registros.', $eliminados);

        if ($omitidos === 0) {
            return ['type' => 'success', 'message' => $mensaje];
        }

        $resumenMotivos = collect($motivos)
            ->map(fn (int $cantidad, string $motivo): string => "{$motivo} ({$cantidad})")
            ->implode(' ');

        return [
            'type' => $eliminados > 0 ? 'warning' : 'error',
            'message' => $mensaje.' '.trans_choice('{1} 1 no se pudo eliminar:|[2,*] :count no se pudieron eliminar:', $omitidos).' '.$resumenMotivos,
            'detalle' => ['registros' => $noEliminados],
        ];
    }
}
