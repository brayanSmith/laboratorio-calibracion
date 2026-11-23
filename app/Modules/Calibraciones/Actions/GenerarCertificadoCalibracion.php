<?php

namespace App\Modules\Calibraciones\Actions;

use App\Models\Empresa;
use App\Models\VersionDocumento;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Equipos\Models\EquipoProgramacion;
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
     * Genera el certificado de calibración en PDF y lo guarda como una nueva versión (el
     * original o la siguiente corrección: -A, -B, ...), conservando las anteriores; su ruta
     * queda en pdf_certificado. Con $nuevaVersion en false vuelve a escribir la última
     * (para cuando se perdió su archivo).
     */
    public function handle(Calibracion $calibracion, bool $nuevaVersion = true): string
    {
        return VersionDocumento::guardar(
            $calibracion,
            'pdf_certificado',
            'certificados/calibraciones',
            fn (string $codigo) => $this->renderizar($calibracion, $codigo),
            $nuevaVersion,
        );
    }

    /**
     * Arma el PDF del certificado con el número dado, sin guardarlo (ej. para adjuntarlo a
     * un correo).
     */
    public function renderizar(Calibracion $calibracion, string $codigo): string
    {
        $calibracion->load([
            'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo',
            'ordenTrabajo.equipoProgramacion.equipo.fabricante',
            'ordenTrabajo.equipoProgramacion.equipo.equipoEspecificacionTecnica.unidadMedida',
            'solicitante',
            'tecnico',
            'procedimiento',
            'detalleMedicionCalibracion.unidadMedida',
        ]);

        $empresa = Empresa::query()->where('tenant_id', $calibracion->tenant_id)->first();
        $programacion = $calibracion->ordenTrabajo->equipoProgramacion;
        $detalles = $calibracion->detalleMedicionCalibracion;

        $svg = $this->grafico->handle($calibracion);

        return Pdf::loadView('pdf.calibracion', [
            'calibracion' => $calibracion,
            'equipo' => $programacion->equipo,
            'especificacion' => $programacion->equipo->equipoEspecificacionTecnica,
            'empresa' => $empresa,
            'logo' => $this->comoDataUri($empresa?->logo),
            'firma' => $calibracion->firmado ? $this->comoDataUri($calibracion->tecnico->firma) : null,
            'numeroCertificado' => $codigo,
            'fechaEmision' => now()->timezone(self::ZONA_HORARIA),
            'fechaVencimiento' => EquipoProgramacion::calcularFechaProximoServicio(
                $calibracion->fecha_calibracion->toDateString(),
                $programacion->intervalo_servicio,
                $programacion->intervalo_unidad,
            ),
            'resultado' => $this->resultado($calibracion),
            'detalles' => $detalles,
            'grafico' => $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null,
        ])->setPaper('letter')->output();
    }

    /**
     * Resultado de la calibración: el que definió el técnico al finalizarla. Si es una
     * calibración anterior a ese campo, se deduce de los puntos medidos (CUMPLE si ninguno
     * quedó no aprobado); null si no hay de dónde sacarlo.
     */
    private function resultado(Calibracion $calibracion): ?string
    {
        if ($calibracion->resultado_calibracion !== null) {
            return $calibracion->resultado_calibracion ? 'CUMPLE' : 'NO CUMPLE';
        }

        $resultados = $calibracion->detalleMedicionCalibracion->pluck('resultado_calibracion')->filter();

        if ($resultados->isEmpty()) {
            return null;
        }

        return $resultados->contains('NO_APROBADO') ? 'NO CUMPLE' : 'CUMPLE';
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
