<?php

namespace App\Modules\Ingresos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Tenant;
use App\Modules\Calibraciones\Actions\GenerarCertificadoCalibracion;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Equipos\Models\SeguimientoProgramacionEquipo;
use App\Modules\Ingresos\Models\FotoRecepcion;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Mantenimientos\Actions\GenerarInformeMantenimiento;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Certificado de calibración (PDF, para verlo en el navegador) de un equipo del ingreso
     * con ese código. Solo de calibraciones finalizadas; si el PDF aún no existe, se genera.
     */
    public function certificadoCalibracion(string $codigo, int $calibracion, GenerarCertificadoCalibracion $generarCertificado): StreamedResponse
    {
        $calibracion = Calibracion::query()
            ->whereKey($calibracion)
            ->where('estado_calibracion', 'FINALIZADO')
            ->whereHas('ordenTrabajo.equipoProgramacion.ingreso', fn ($query) => $query->where('codigo', Str::upper($codigo)))
            ->with('ordenTrabajo:id,codigo')
            ->firstOrFail();

        $disco = Storage::disk('public');
        $ruta = $calibracion->pdf_certificado && $disco->exists($calibracion->pdf_certificado)
            ? $calibracion->pdf_certificado
            : $generarCertificado->handle($calibracion);

        return $disco->response($ruta, 'certificado-calibracion-'.$calibracion->ordenTrabajo->codigo.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Informe de mantenimiento (PDF, para verlo en el navegador) de un equipo del ingreso con
     * ese código. Solo de mantenimientos finalizados; si el PDF aún no existe, se genera.
     */
    public function informeMantenimiento(string $codigo, int $mantenimiento, GenerarInformeMantenimiento $generarInforme): StreamedResponse
    {
        $mantenimiento = Mantenimiento::query()
            ->whereKey($mantenimiento)
            ->where('estado_mantenimiento', 'FINALIZADO')
            ->whereHas('ordenTrabajo.equipoProgramacion.ingreso', fn ($query) => $query->where('codigo', Str::upper($codigo)))
            ->with('ordenTrabajo:id,codigo')
            ->firstOrFail();

        $disco = Storage::disk('public');
        $ruta = $mantenimiento->pdf_informe && $disco->exists($mantenimiento->pdf_informe)
            ? $mantenimiento->pdf_informe
            : $generarInforme->handle($mantenimiento);

        return $disco->response($ruta, 'informe-mantenimiento-'.$mantenimiento->ordenTrabajo->codigo.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Documento (PDF) que entregó el laboratorio externo por un servicio de mantenimiento o
     * calibración de un equipo del ingreso con ese código. Solo de servicios finalizados.
     */
    public function documentoServicioTercero(string $codigo, int $servicio): StreamedResponse
    {
        $servicio = ServicioTercero::query()
            ->whereKey($servicio)
            ->where('estado_servicio', 'FINALIZADO')
            ->whereNotNull('pdf_servicio')
            ->whereHas('ordenTrabajo.equipoProgramacion.ingreso', fn ($query) => $query->where('codigo', Str::upper($codigo)))
            ->with('ordenTrabajo:id,codigo')
            ->firstOrFail();

        $disco = Storage::disk('public');
        abort_unless($disco->exists($servicio->pdf_servicio), 404);

        $nombre = $servicio->tipo_servicio === 'CALIBRACION' ? 'certificado-calibracion' : 'informe-mantenimiento';

        return $disco->response($servicio->pdf_servicio, "{$nombre}-{$servicio->ordenTrabajo->codigo}.pdf", ['Content-Type' => 'application/pdf']);
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
                'ordenesTrabajo.mantenimientos' => fn ($query) => $query->where('estado_mantenimiento', 'FINALIZADO'),
                'ordenesTrabajo.calibraciones' => fn ($query) => $query->where('estado_calibracion', 'FINALIZADO'),
                'fotosRecepcion',
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
                // Evidencia de cómo llegó el equipo, tomada al recibirlo.
                'fotos' => $programacion->fotosRecepcion->map(fn (FotoRecepcion $foto) => $foto->url())->values()->all(),
                'documentos' => $this->documentos($programacion, $ingreso->codigo),
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
