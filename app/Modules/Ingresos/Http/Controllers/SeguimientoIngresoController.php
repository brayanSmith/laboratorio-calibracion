<?php

namespace App\Modules\Ingresos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Tenant;
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
            ? Ingreso::query()->where('codigo', $codigo)->with(['bahia:id,nombre', 'clienteEntrega:id,nombre'])->first()
            : null;

        return Inertia::render('seguimiento/show', [
            'codigo' => $codigo,
            'ingreso' => $ingreso ? $this->datosIngreso($ingreso) : null,
            'contacto' => $this->contacto($ingreso),
        ]);
    }

    /**
     * Datos de contacto de la empresa para el pie de página: la del laboratorio del ingreso
     * consultado o, con un solo laboratorio (MULTI_TENANT=false), la de ese laboratorio.
     *
     * @return array{nombre: string, nit: string, direccion: string, telefono: string, email: string|null}|null
     */
    private function contacto(?Ingreso $ingreso): ?array
    {
        $tenantId = $ingreso?->tenant_id
            ?? (config('app.multi_tenant') ? null : Tenant::principal()?->id);

        $empresa = $tenantId ? Empresa::query()->where('tenant_id', $tenantId)->first() : null;

        return $empresa ? [
            'nombre' => $empresa->nombre,
            'nit' => $empresa->nit,
            'direccion' => $empresa->laboratorio_direccion ?: $empresa->direccion,
            'telefono' => $empresa->laboratorio_telefono ?: $empresa->telefono,
            'email' => $empresa->laboratorio_email,
        ] : null;
    }

    /**
     * @return array{codigo: string, empresa: array{nombre: string, logo_url: string|null}|null, cliente: string|null, bahia: string, estado_ingreso: string, fecha_recepcion: string|null, fecha_ingreso: string|null, equipos: list<array<string, mixed>>}
     */
    private function datosIngreso(Ingreso $ingreso): array
    {
        $empresa = Empresa::query()->where('tenant_id', $ingreso->tenant_id)->first();
        $laboratorioPropio = $empresa?->laboratorio_nombre ?: $empresa?->nombre;
        $programaciones = $ingreso->equipoProgramacion()
            ->with([
                'equipo.tipoEquipo',
                'equipo.cliente:id,nombre',
                'seguimientos' => fn ($query) => $query->orderBy('id'),
                'ordenesTrabajo.serviciosTerceros.empresaTercero:id,nombre',
            ])
            ->get();
        $clientes = $programaciones->pluck('equipo.cliente.nombre')->unique();
        $equipos = $programaciones
            ->sortBy('equipo.codigo')
            ->map(fn (EquipoProgramacion $programacion) => [
                'codigo' => $programacion->equipo->codigo,
                'tipo_equipo' => $programacion->equipo->tipoEquipo->nombre,
                'modelo' => $programacion->equipo->modelo,
                'numero_serie' => $programacion->equipo->numero_serie,
                'tipo_servicio' => $programacion->tipoServicioLegible(),
                'ingresado' => $ingreso->estado_ingreso === 'RECIBIDO' ? $programacion->ingresado : null,
                // Cuándo se recibió el equipo: el seguimiento que lo dejó RECIBIDO.
                'fecha_ingreso' => $programacion->seguimientos
                    ->first(fn (SeguimientoProgramacionEquipo $seguimiento) => $seguimiento->fase === 'INGRESO' && $seguimiento->seguimiento === 'RECIBIDO')
                    ?->created_at?->toIso8601String(),
                'historial' => $programacion->seguimientos
                    ->map(fn (SeguimientoProgramacionEquipo $seguimiento) => [
                        'fase' => self::FASES[$seguimiento->fase] ?? Str::headline(Str::lower($seguimiento->fase)),
                        'estado' => $seguimiento->seguimiento ? Str::ucfirst(Str::lower(str_replace('_', ' ', $seguimiento->seguimiento))) : null,
                        'fecha' => $seguimiento->created_at?->toIso8601String(),
                        'laboratorio' => $this->laboratorio($programacion, $seguimiento->fase, $laboratorioPropio),
                    ])
                    ->values()
                    ->all(),
                'siguientes' => $programacion->ingresado === false ? [] : $this->fasesSiguientes($programacion),
            ])
            ->values();

        return [
            'codigo' => $ingreso->codigo,
            'empresa' => $empresa ? ['nombre' => $empresa->nombre, 'logo_url' => $empresa->logoUrl()] : null,
            // El cliente que entregó los equipos o, si no se registró, el de los equipos
            // cuando todos son del mismo.
            'cliente' => $ingreso->clienteEntrega?->nombre ?? ($clientes->count() === 1 ? $clientes->first() : null),
            'bahia' => $ingreso->bahia->nombre,
            'estado_ingreso' => $ingreso->estado_ingreso,
            'fecha_recepcion' => $ingreso->fecha_recepcion?->toDateString(),
            // Fecha y hora en que llegaron: la del primer equipo recibido.
            'fecha_ingreso' => $equipos->pluck('fecha_ingreso')->filter()->sort()->first(),
            'equipos' => $equipos->all(),
        ];
    }

    /**
     * Etapas que le faltan al equipo, en orden: las de su recorrido (bahía, ingreso, los
     * servicios que se le programaron y despacho) que van después de la última alcanzada.
     *
     * @return list<string>
     */
    private function fasesSiguientes(EquipoProgramacion $programacion): array
    {
        $servicios = array_map('trim', explode(',', (string) $programacion->tipo_servicio));
        $recorrido = array_values(array_filter(
            array_keys(self::FASES),
            fn (string $fase) => ! in_array($fase, ['MANTENIMIENTO', 'CALIBRACION'], true) || in_array($fase, $servicios, true),
        ));
        $alcanzadas = $programacion->seguimientos
            ->map(fn (SeguimientoProgramacionEquipo $seguimiento) => array_search($seguimiento->fase, $recorrido, true))
            ->filter(fn (int|false $posicion) => $posicion !== false);
        $desde = $alcanzadas->isEmpty() ? 0 : $alcanzadas->max() + 1;

        return array_map(fn (string $fase) => self::FASES[$fase], array_slice($recorrido, $desde));
    }

    /**
     * Dónde se hace el mantenimiento o la calibración del equipo: el laboratorio externo al
     * que se envió (servicio de tercero de esa fase) o, si no, el laboratorio propio. Null en
     * las demás fases.
     */
    private function laboratorio(EquipoProgramacion $programacion, string $fase, ?string $laboratorioPropio): ?string
    {
        if (! in_array($fase, ['MANTENIMIENTO', 'CALIBRACION'], true)) {
            return null;
        }

        $tercero = $programacion->ordenesTrabajo
            ->flatMap(fn ($orden) => $orden->serviciosTerceros)
            ->where('tipo_servicio', $fase)
            ->sortByDesc('id')
            ->first();

        return $tercero?->empresaTercero?->nombre ?? $laboratorioPropio;
    }
}
