<?php

namespace App\Concerns;

use App\Enums\TenantPermission;
use App\Http\Requests\EliminarPorLoteRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Throwable;

/**
 * Eliminar varios registros de una tabla a la vez. Cada registro pasa por las mismas reglas
 * que al eliminarlo uno por uno (eliminarRegistro(), que también usa destroy()): los que no
 * se pueden eliminar se saltan y se informa cuántos y por qué.
 */
trait EliminaPorLote
{
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

        foreach ($registros as $registro) {
            $motivo = Gate::denies('delete', $registro)
                ? __('No tienes permiso para eliminarlo.')
                : $this->eliminarEnTransaccion($registro);

            if ($motivo === null) {
                $eliminados++;
            } else {
                $motivos[$motivo] = ($motivos[$motivo] ?? 0) + 1;
            }
        }

        Inertia::flash('toast', $this->resumenLote($eliminados, $motivos));

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
     * @param  array<string, int>  $motivos
     * @return array{type: string, message: string}
     */
    private function resumenLote(int $eliminados, array $motivos): array
    {
        $omitidos = array_sum($motivos);
        $mensaje = trans_choice('{0} No se eliminó ningún registro.|{1} Se eliminó 1 registro.|[2,*] Se eliminaron :count registros.', $eliminados);

        if ($omitidos === 0) {
            return ['type' => 'success', 'message' => $mensaje];
        }

        $detalle = collect($motivos)
            ->map(fn (int $cantidad, string $motivo): string => "{$motivo} ({$cantidad})")
            ->implode(' ');

        return [
            'type' => $eliminados > 0 ? 'warning' : 'error',
            'message' => $mensaje.' '.trans_choice('{1} 1 no se pudo eliminar:|[2,*] :count no se pudieron eliminar:', $omitidos).' '.$detalle,
        ];
    }
}
