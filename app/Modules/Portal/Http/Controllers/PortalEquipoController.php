<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoHistorico;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Actions\DatosSeguimientoIngreso;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que ve el cliente en el portal: sus equipos (los suyos y los de sus bahías) con su
 * estado, y la traza de cada uno con sus documentos. Todo se filtra por el cliente que
 * inició sesión (ver Cliente::equiposVisibles()).
 */
class PortalEquipoController extends Controller
{
    /** Gravedad del vencimiento, para quedarse con el más urgente de un equipo. */
    private const GRAVEDAD = ['AL_DIA' => 0, 'PROXIMO_A_VENCER' => 1, 'VENCIDO' => 2];

    /**
     * Los equipos del cliente con su estado de vencimiento y su próximo servicio.
     */
    public function index(Request $request): Response
    {
        $cliente = $this->cliente($request);

        $equipos = $cliente->equiposVisibles()
            ->with([
                'tipoEquipo:id,nombre,tipo_mantenimiento',
                'area:id,nombre',
                'bahia:id,nombre',
                'equipoProgramaciones' => fn ($query) => $query
                    ->where('estado_programacion', '!=', 'RECIBIDO')
                    ->select(['id', 'equipo_id', 'fecha_proximo_servicio', 'dias_plazo_vencimiento', 'estado_programacion']),
            ])
            ->orderBy('codigo')
            ->get()
            ->map(function (Equipo $equipo): array {
                $pendientes = $equipo->equipoProgramaciones;
                $estado = $pendientes
                    ->pluck('estado_vencimiento')
                    ->sortByDesc(fn (string $estado): int => self::GRAVEDAD[$estado])
                    ->first();

                return [
                    'id' => $equipo->id,
                    'codigo' => $equipo->codigo,
                    'tipo_equipo' => $equipo->tipoEquipo?->nombre,
                    'clase_equipo' => $equipo->tipoEquipo?->tipo_mantenimiento,
                    'modelo' => $equipo->modelo,
                    'numero_serie' => $equipo->numero_serie,
                    'area' => $equipo->area?->nombre,
                    'bahia' => $equipo->bahia?->nombre,
                    'activo' => (bool) $equipo->activo,
                    // Inactivo: ya no se le programan servicios, no tiene vencimiento.
                    'estado_vencimiento' => $equipo->activo ? ($estado ?? 'SIN_PROGRAMACION') : 'INACTIVO',
                    'fecha_proximo_servicio' => $pendientes
                        ->map(fn (EquipoProgramacion $programacion) => $programacion->fecha_proximo_servicio?->toDateString())
                        ->filter()
                        ->min(),
                ];
            });

        return Inertia::render('portal/equipos/index', [
            ...$this->marca($cliente),
            'equipos' => $equipos,
        ]);
    }

    /**
     * Un equipo del cliente con su traza: cada paso por un ingreso (fases y documentos) y
     * los servicios históricos que se registraron antes de usar el sistema.
     */
    public function show(Request $request, int $equipo, DatosSeguimientoIngreso $datosSeguimiento): Response
    {
        $cliente = $this->cliente($request);

        // Solo uno de sus equipos: si no, como si no existiera.
        $registro = $cliente->equiposVisibles()
            ->with(['tipoEquipo:id,nombre,tipo_mantenimiento', 'area:id,nombre', 'bahia:id,nombre', 'fabricante:id,nombre'])
            ->findOrFail($equipo);

        return Inertia::render('portal/equipos/show', [
            ...$this->marca($cliente),
            'equipo' => [
                'id' => $registro->id,
                'codigo' => $registro->codigo,
                'tipo_equipo' => $registro->tipoEquipo?->nombre,
                'clase_equipo' => $registro->tipoEquipo?->tipo_mantenimiento,
                'modelo' => $registro->modelo,
                'numero_serie' => $registro->numero_serie,
                'fabricante' => $registro->fabricante?->nombre,
                'area' => $registro->area?->nombre,
                'bahia' => $registro->bahia?->nombre,
                'activo' => (bool) $registro->activo,
                'motivo_inactivo' => $registro->activo ? null : $registro->motivo_inactivo,
            ],
            'traza' => $datosSeguimiento->trazaDeEquipo($registro),
            'historicos' => $registro->equipoHistoricos()
                ->with('empresaTercero:id,nombre')
                ->orderByDesc('fecha_servicio')
                ->get()
                ->map(fn (EquipoHistorico $historico) => $historico->resumen())
                ->values(),
        ]);
    }

    private function cliente(Request $request): Cliente
    {
        /** @var Cliente */
        return $request->user('cliente');
    }

    /**
     * La marca del laboratorio del cliente y su nombre, para el encabezado del portal.
     *
     * @return array{empresa: array{nombre: string, logo_url: string|null}|null, cliente: array{nombre: string}}
     */
    public static function marca(Cliente $cliente): array
    {
        $empresa = Empresa::query()->where('tenant_id', $cliente->tenant_id)->first();

        return [
            'empresa' => $empresa ? ['nombre' => $empresa->nombre, 'logo_url' => $empresa->logoUrl()] : null,
            'cliente' => ['nombre' => $cliente->nombre],
        ];
    }
}
