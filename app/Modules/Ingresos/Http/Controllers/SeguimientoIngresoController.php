<?php

namespace App\Modules\Ingresos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Tenant;
use App\Modules\Calibraciones\Actions\GenerarCertificadoCalibracion;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Ingresos\Actions\DatosSeguimientoIngreso;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Mantenimientos\Actions\GenerarInformeMantenimiento;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeguimientoIngresoController extends Controller
{
    /**
     * Página pública (sin iniciar sesión) donde el cliente, con el código del ingreso que le
     * llegó por correo, ve el estado de los equipos de ese ingreso. No muestra clientes,
     * usuarios ni observaciones internas.
     */
    public function __invoke(Request $request, DatosSeguimientoIngreso $datosSeguimiento): Response
    {
        $codigo = Ingreso::normalizarCodigo((string) $request->query('codigo', ''));

        $ingreso = $codigo !== ''
            ? Ingreso::query()->where('codigo', $codigo)->with(['bahia:id,nombre', 'clienteEntrega:id,nombre'])->first()
            : null;

        return Inertia::render('seguimiento/show', [
            'codigo' => $codigo,
            'ingreso' => $ingreso ? $datosSeguimiento->handle($ingreso) : null,
            'contacto' => self::contacto($ingreso),
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
            ->whereHas('ordenTrabajo.equipoProgramacion.ingreso', fn ($query) => $query->where('codigo', Ingreso::normalizarCodigo($codigo)))
            ->with('ordenTrabajo:id,codigo')
            ->firstOrFail();

        $disco = Storage::disk('public');
        $ruta = $calibracion->pdf_certificado && $disco->exists($calibracion->pdf_certificado)
            ? $calibracion->pdf_certificado
            : $generarCertificado->handle($calibracion, nuevaVersion: false);

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
            ->whereHas('ordenTrabajo.equipoProgramacion.ingreso', fn ($query) => $query->where('codigo', Ingreso::normalizarCodigo($codigo)))
            ->with('ordenTrabajo:id,codigo')
            ->firstOrFail();

        $disco = Storage::disk('public');
        $ruta = $mantenimiento->pdf_informe && $disco->exists($mantenimiento->pdf_informe)
            ? $mantenimiento->pdf_informe
            : $generarInforme->handle($mantenimiento, nuevaVersion: false);

        return $disco->response($ruta, 'informe-mantenimiento-'.$mantenimiento->ordenTrabajo->codigo.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Certificado de calibración al que lleva el QR de la etiqueta pegada en el equipo (ver
     * CalibracionController::etiqueta()). El enlace va firmado: no hace falta el código del
     * ingreso, y siempre muestra la versión más reciente del certificado.
     */
    public function certificadoEtiqueta(int $calibracion, GenerarCertificadoCalibracion $generarCertificado): StreamedResponse
    {
        $calibracion = Calibracion::query()
            ->whereKey($calibracion)
            ->where('estado_calibracion', 'FINALIZADO')
            ->with('ordenTrabajo:id,codigo')
            ->firstOrFail();

        $disco = Storage::disk('public');
        $ruta = $calibracion->pdf_certificado && $disco->exists($calibracion->pdf_certificado)
            ? $calibracion->pdf_certificado
            : $generarCertificado->handle($calibracion, nuevaVersion: false);

        return $disco->response($ruta, 'certificado-calibracion-'.$calibracion->ordenTrabajo->codigo.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Informe de mantenimiento al que lleva el QR de la etiqueta pegada en el equipo (ver
     * MantenimientoController::etiqueta()). El enlace va firmado: no hace falta el código del
     * ingreso, y siempre muestra la versión más reciente del informe.
     */
    public function informeEtiqueta(int $mantenimiento, GenerarInformeMantenimiento $generarInforme): StreamedResponse
    {
        $mantenimiento = Mantenimiento::query()
            ->whereKey($mantenimiento)
            ->where('estado_mantenimiento', 'FINALIZADO')
            ->with('ordenTrabajo:id,codigo')
            ->firstOrFail();

        $disco = Storage::disk('public');
        $ruta = $mantenimiento->pdf_informe && $disco->exists($mantenimiento->pdf_informe)
            ? $mantenimiento->pdf_informe
            : $generarInforme->handle($mantenimiento, nuevaVersion: false);

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
            ->whereHas('ordenTrabajo.equipoProgramacion.ingreso', fn ($query) => $query->where('codigo', Ingreso::normalizarCodigo($codigo)))
            ->with('ordenTrabajo:id,codigo')
            ->firstOrFail();

        $disco = Storage::disk('public');
        abort_unless($disco->exists($servicio->pdf_servicio), 404);

        $nombre = $servicio->tipo_servicio === 'CALIBRACION' ? 'certificado-calibracion' : 'informe-mantenimiento';

        return $disco->response($servicio->pdf_servicio, "{$nombre}-{$servicio->ordenTrabajo->codigo}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * Datos de contacto de la empresa para el pie de página: la del laboratorio del ingreso
     * consultado o, con un solo laboratorio (MULTI_TENANT=false), la de ese laboratorio.
     *
     * @return array{nombre: string, nit: string, direccion: string, telefono: string, email: string|null}|null
     */
    public static function contacto(?Ingreso $ingreso): ?array
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
}
