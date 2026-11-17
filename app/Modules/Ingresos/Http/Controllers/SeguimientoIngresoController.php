<?php

namespace App\Modules\Ingresos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Equipos\Models\SeguimientoProgramacionEquipo;
use App\Modules\Ingresos\Models\Ingreso;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SeguimientoIngresoController extends Controller
{
    /**
     * Nombre de cada fase del seguimiento, para mostrárselo al cliente.
     *
     * @var array<string, string>
     */
    private const FASES = [
        'BAHIA' => 'En bahía',
        'INGRESO' => 'Ingreso',
        'MANTENIMIENTO' => 'Mantenimiento',
        'CALIBRACION' => 'Calibración',
        'DESPACHO' => 'Despacho',
    ];

    /**
     * Página pública (sin iniciar sesión) donde el cliente, con el código del ingreso que le
     * llegó por correo, ve el estado de los equipos de ese ingreso. No muestra clientes,
     * usuarios ni observaciones internas.
     */
    public function __invoke(Request $request): Response
    {
        $codigo = Str::upper(trim((string) $request->query('codigo', '')));

        $ingreso = $codigo !== ''
            ? Ingreso::query()->where('codigo', $codigo)->with('bahia:id,nombre')->first()
            : null;

        return Inertia::render('seguimiento/show', [
            'codigo' => $codigo,
            'ingreso' => $ingreso ? $this->datosIngreso($ingreso) : null,
        ]);
    }

    /**
     * @return array{codigo: string, empresa: array{nombre: string, logo_url: string|null}|null, bahia: string, estado_ingreso: string, fecha_recepcion: string|null, equipos: list<array<string, mixed>>}
     */
    private function datosIngreso(Ingreso $ingreso): array
    {
        $empresa = Empresa::query()->where('tenant_id', $ingreso->tenant_id)->first();

        return [
            'codigo' => $ingreso->codigo,
            'empresa' => $empresa ? ['nombre' => $empresa->nombre, 'logo_url' => $empresa->logoUrl()] : null,
            'bahia' => $ingreso->bahia->nombre,
            'estado_ingreso' => $ingreso->estado_ingreso,
            'fecha_recepcion' => $ingreso->fecha_recepcion?->toDateString(),
            'equipos' => $ingreso->equipoProgramacion()
                ->with(['equipo.tipoEquipo', 'seguimientos' => fn ($query) => $query->orderBy('id')])
                ->get()
                ->sortBy('equipo.codigo')
                ->map(fn (EquipoProgramacion $programacion) => [
                    'codigo' => $programacion->equipo->codigo,
                    'tipo_equipo' => $programacion->equipo->tipoEquipo->nombre,
                    'modelo' => $programacion->equipo->modelo,
                    'numero_serie' => $programacion->equipo->numero_serie,
                    'tipo_servicio' => $programacion->tipoServicioLegible(),
                    'ingresado' => $ingreso->estado_ingreso === 'RECIBIDO' ? $programacion->ingresado : null,
                    'historial' => $programacion->seguimientos
                        ->map(fn (SeguimientoProgramacionEquipo $seguimiento) => [
                            'fase' => self::FASES[$seguimiento->fase] ?? Str::headline(Str::lower($seguimiento->fase)),
                            'estado' => $seguimiento->seguimiento ? Str::ucfirst(Str::lower(str_replace('_', ' ', $seguimiento->seguimiento))) : null,
                            'fecha' => $seguimiento->created_at?->toIso8601String(),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
