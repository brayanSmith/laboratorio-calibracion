<?php

namespace App\Actions\Calibraciones;

use App\Models\Calibracion;
use App\Models\Empresa;
use App\Models\EquipoProgramacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerarCertificadoCalibracion
{
    /**
     * Zona horaria en la que se muestran las fechas del certificado (la aplicación guarda en UTC).
     */
    private const ZONA_HORARIA = 'America/Lima';

    public function __construct(private readonly GraficoErrorCalibracion $grafico) {}

    /**
     * Genera el certificado de calibración en PDF, lo guarda en el disco público
     * (reemplazando el anterior, si lo había) y registra su ruta en pdf_certificado.
     */
    public function handle(Calibracion $calibracion): string
    {
        $calibracion->load([
            'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo',
            'ordenTrabajo.equipoProgramacion.equipo.fabricante',
            'ordenTrabajo.equipoProgramacion.equipo.equipoEspecificacionTecnica.unidadMedida',
            'laboratorio',
            'solicitante',
            'tecnico',
            'procedimiento',
            'detalleMedicionCalibracion.unidadMedida',
        ]);

        $empresa = Empresa::query()->where('tenant_id', $calibracion->tenant_id)->first();
        $programacion = $calibracion->ordenTrabajo->equipoProgramacion;
        $detalles = $calibracion->detalleMedicionCalibracion;

        $svg = $this->grafico->handle($calibracion);

        $pdf = Pdf::loadView('pdf.calibracion', [
            'calibracion' => $calibracion,
            'equipo' => $programacion->equipo,
            'especificacion' => $programacion->equipo->equipoEspecificacionTecnica,
            'empresa' => $empresa,
            'logo' => $this->comoDataUri($empresa?->logo),
            'firma' => $calibracion->firmado ? $this->comoDataUri($calibracion->tecnico->firma) : null,
            'numeroCertificado' => 'CC-'.str_pad((string) $calibracion->id, 6, '0', STR_PAD_LEFT),
            'fechaEmision' => now()->timezone(self::ZONA_HORARIA),
            'fechaVencimiento' => EquipoProgramacion::calcularFechaProximoServicio(
                $calibracion->fecha_calibracion->toDateString(),
                $programacion->intervalo_servicio,
                $programacion->intervalo_unidad,
            ),
            'resultado' => $this->resultado($calibracion),
            'detalles' => $detalles,
            'grafico' => $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null,
        ])->setPaper('letter');

        $ruta = "certificados/calibraciones/{$calibracion->tenant_id}/calibracion-{$calibracion->id}.pdf";

        if ($calibracion->pdf_certificado && $calibracion->pdf_certificado !== $ruta) {
            Storage::disk('public')->delete($calibracion->pdf_certificado);
        }

        Storage::disk('public')->put($ruta, $pdf->output());

        $calibracion->update(['pdf_certificado' => $ruta]);

        return $ruta;
    }

    /**
     * Resultado de la calibración: el que definió el técnico al finalizarla. Si es una
     * calibración anterior a ese campo, se deduce de los puntos medidos (APROBADO si ninguno
     * quedó no aprobado); null si no hay de dónde sacarlo.
     */
    private function resultado(Calibracion $calibracion): ?string
    {
        if ($calibracion->resultado_calibracion !== null) {
            return $calibracion->resultado_calibracion ? 'APROBADO' : 'NO APROBADO';
        }

        $resultados = $calibracion->detalleMedicionCalibracion->pluck('resultado_calibracion')->filter();

        if ($resultados->isEmpty()) {
            return null;
        }

        return $resultados->contains('NO_APROBADO') ? 'NO APROBADO' : 'APROBADO';
    }

    /**
     * Lee una imagen del disco público como data URI, para incrustarla en el PDF sin
     * depender de URLs ni de que el servidor pueda pedirse a sí mismo.
     */
    private function comoDataUri(?string $ruta): ?string
    {
        $disco = Storage::disk('public');

        if (! $ruta || ! $disco->exists($ruta)) {
            return null;
        }

        $mime = $disco->mimeType($ruta) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode($disco->get($ruta));
    }
}
