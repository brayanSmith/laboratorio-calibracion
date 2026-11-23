<?php

namespace App\Modules\Ingresos\Actions;

use App\Models\Empresa;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Equipos\Models\SeguimientoProgramacionEquipo;
use App\Modules\Ingresos\Models\FotoRecepcion;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lo que ve el cliente de un ingreso en el seguimiento público (por código o por bahía):
 * sus equipos, en qué etapa va cada uno, sus fotos de recepción y los documentos de los
 * servicios terminados. No incluye usuarios ni observaciones internas.
 */
class DatosSeguimientoIngreso
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
     * @return array{codigo: string, empresa: array{nombre: string, logo_url: string|null}|null, cliente: string|null, bahia: string, estado_ingreso: string, fecha_recepcion: string|null, fecha_ingreso: string|null, equipos: list<array<string, mixed>>}
     */
    public function handle(Ingreso $ingreso): array
    {
        $empresa = Empresa::query()->where('tenant_id', $ingreso->tenant_id)->first();
        $laboratorioPropio = $empresa?->laboratorio_nombre ?: $empresa?->nombre;
        $programaciones = $ingreso->equipoProgramacion()->with($this->relaciones())->get();
        $clientes = $programaciones->pluck('equipo.cliente.nombre')->unique();
        $equipos = $programaciones
            ->sortBy('equipo.codigo')
            ->map(fn (EquipoProgramacion $programacion) => $this->equipo($programacion, $ingreso, $laboratorioPropio))
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
     * Los equipos de varios ingresos (del mismo laboratorio) en una sola lista, cada uno con
     * el código, la bahía y la fecha de su ingreso, del ingreso más reciente al más antiguo.
     * Para el seguimiento por bahía, que los muestra todos juntos en una tabla.
     *
     * @param  Collection<int, Ingreso>  $ingresos  Con su bahía cargada
     * @return list<array<string, mixed>>
     */
    public function equiposDeIngresos(Collection $ingresos): array
    {
        if ($ingresos->isEmpty()) {
            return [];
        }

        $empresa = Empresa::query()->where('tenant_id', $ingresos->first()->tenant_id)->first();
        $laboratorioPropio = $empresa?->laboratorio_nombre ?: $empresa?->nombre;
        $porId = $ingresos->keyBy('id');

        return EquipoProgramacion::query()
            ->whereIn('ingreso_id', $porId->keys())
            ->with($this->relaciones())
            ->get()
            ->map(function (EquipoProgramacion $programacion) use ($porId, $laboratorioPropio): array {
                $ingreso = $porId[$programacion->ingreso_id];

                return [
                    ...$this->equipo($programacion, $ingreso, $laboratorioPropio),
                    'id' => $programacion->id,
                    'ingreso' => $ingreso->codigo,
                    'bahia_id' => $ingreso->bahia_id,
                    'bahia' => $ingreso->bahia?->nombre,
                    'estado_ingreso' => $ingreso->estado_ingreso,
                    // Para ordenar: cuándo se recibió o, si aún no, desde cuándo se espera.
                    'fecha_ingreso_ingreso' => ($ingreso->fecha_recepcion ?? $ingreso->desde)?->toDateString(),
                ];
            })
            ->sortBy([
                ['fecha_ingreso_ingreso', 'desc'],
                ['ingreso', 'desc'],
                ['codigo', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Lo que se carga de cada programación para mostrarla.
     *
     * @return array<int|string, mixed>
     */
    private function relaciones(): array
    {
        return [
            'equipo.tipoEquipo',
            'equipo.cliente:id,nombre',
            'seguimientos' => fn ($query) => $query->orderBy('id'),
            'ordenesTrabajo.serviciosTerceros.empresaTercero:id,nombre',
            'ordenesTrabajo.mantenimientos' => fn ($query) => $query->where('estado_mantenimiento', 'FINALIZADO'),
            'ordenesTrabajo.calibraciones' => fn ($query) => $query->where('estado_calibracion', 'FINALIZADO'),
            'fotosRecepcion',
        ];
    }

    /**
     * Un equipo del ingreso como lo ve el cliente.
     *
     * @return array<string, mixed>
     */
    private function equipo(EquipoProgramacion $programacion, Ingreso $ingreso, ?string $laboratorioPropio): array
    {
        return [
            'codigo' => $programacion->equipo->codigo,
            'tipo_equipo' => $programacion->equipo->tipoEquipo->nombre,
            'clase_equipo' => $programacion->equipo->tipoEquipo->tipo_mantenimiento,
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
            // Evidencia de cómo llegó el equipo, tomada al recibirlo.
            'fotos' => $programacion->fotosRecepcion->map(fn (FotoRecepcion $foto) => $foto->url())->values()->all(),
            'documentos' => $this->documentos($programacion, $ingreso->codigo),
            'siguientes' => $programacion->ingresado === false ? [] : $this->fasesSiguientes($programacion),
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

    /**
     * Los PDF de los servicios terminados del equipo: informes de mantenimiento y
     * certificados de calibración, propios o del laboratorio externo al que se envió, del
     * más antiguo al más reciente.
     *
     * @return list<array{tipo: string, nombre: string, fecha: string|null, url: string, laboratorio: string|null}>
     */
    private function documentos(EquipoProgramacion $programacion, string $codigo): array
    {
        $mantenimientos = $programacion->ordenesTrabajo
            ->flatMap(fn ($orden) => $orden->mantenimientos)
            ->map(fn (Mantenimiento $mantenimiento) => [
                'tipo' => 'MANTENIMIENTO',
                'nombre' => 'Informe de mantenimiento',
                'fecha' => ($mantenimiento->fecha_realizacion ?? $mantenimiento->fecha_mantenimiento)?->toDateString(),
                'url' => route('seguimiento.mantenimiento.informe', ['codigo' => $codigo, 'mantenimiento' => $mantenimiento->id]),
                'laboratorio' => null,
            ]);
        $calibraciones = $programacion->ordenesTrabajo
            ->flatMap(fn ($orden) => $orden->calibraciones)
            ->map(fn (Calibracion $calibracion) => [
                'tipo' => 'CALIBRACION',
                'nombre' => 'Certificado de calibración',
                'fecha' => ($calibracion->fecha_realizacion ?? $calibracion->fecha_calibracion)?->toDateString(),
                'url' => route('seguimiento.calibracion.certificado', ['codigo' => $codigo, 'calibracion' => $calibracion->id]),
                'laboratorio' => null,
            ]);
        // El documento que entregó el laboratorio externo, si el servicio se hizo afuera.
        $terceros = $programacion->ordenesTrabajo
            ->flatMap(fn ($orden) => $orden->serviciosTerceros)
            ->filter(fn (ServicioTercero $servicio) => $servicio->estado_servicio === 'FINALIZADO' && $servicio->pdf_servicio)
            ->map(fn (ServicioTercero $servicio) => [
                'tipo' => $servicio->tipo_servicio === 'CALIBRACION' ? 'CALIBRACION' : 'MANTENIMIENTO',
                'nombre' => $servicio->tipo_servicio === 'CALIBRACION' ? 'Certificado de calibración' : 'Informe de mantenimiento',
                'fecha' => $servicio->fecha_realizacion?->toDateString(),
                'url' => route('seguimiento.servicio-tercero.documento', ['codigo' => $codigo, 'servicio' => $servicio->id]),
                'laboratorio' => $servicio->empresaTercero?->nombre,
            ]);

        return $mantenimientos->concat($calibraciones)->concat($terceros)->sortBy('fecha')->values()->all();
    }
}
