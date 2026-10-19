<?php

namespace App\Actions\Mantenimientos;

use App\Models\Empresa;
use App\Models\Mantenimiento;
use App\Models\TiempoServicio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerarInformeMantenimiento
{
    /**
     * Zona horaria en la que se muestran las fechas del informe (la aplicación guarda en UTC).
     */
    private const ZONA_HORARIA = 'America/Lima';

    /**
     * Genera el informe PDF del mantenimiento, lo guarda en el disco público (reemplazando
     * el anterior, si lo había) y registra su ruta en pdf_informe.
     */
    public function handle(Mantenimiento $mantenimiento): string
    {
        $mantenimiento->load([
            'ordenTrabajo.equipoProgramacion.equipo.tipoEquipo',
            'ordenTrabajo.equipoProgramacion.equipo.fabricante',
            'ordenTrabajo.equipoProgramacion.equipo.bahia',
            'tecnico',
            'novedad',
            'mantenimientoCheckLists.tipoEquipoCheckList',
            'defectoIdentificados',
            'itemMantenimientos.item',
            'comentarioMantenimientos.user',
            'galeriaMantenimientos',
        ]);

        $empresa = Empresa::query()->where('tenant_id', $mantenimiento->tenant_id)->first();

        $pdf = Pdf::loadView('pdf.mantenimiento', [
            'mantenimiento' => $mantenimiento,
            'equipo' => $mantenimiento->ordenTrabajo->equipoProgramacion->equipo,
            'programacion' => $mantenimiento->ordenTrabajo->equipoProgramacion,
            'duracion' => TiempoServicio::formatearDuracion(TiempoServicio::duracionMantenimiento($mantenimiento->orden_trabajo_id)),
            'fechaHora' => now()->timezone(self::ZONA_HORARIA),
            'empresa' => $empresa,
            'logo' => $this->comoDataUri($empresa?->logo),
            'firma' => $mantenimiento->firmado ? $this->comoDataUri($mantenimiento->tecnico->firma) : null,
            'fotos' => $mantenimiento->galeriaMantenimientos
                ->map(fn ($foto) => ['data' => $this->comoDataUri($foto->imagen), 'descripcion' => $foto->descripcion])
                ->filter(fn (array $foto) => $foto['data'] !== null)
                ->values()
                ->all(),
        ])->setPaper('letter');

        $ruta = "informes/mantenimientos/{$mantenimiento->tenant_id}/mantenimiento-{$mantenimiento->id}.pdf";

        if ($mantenimiento->pdf_informe && $mantenimiento->pdf_informe !== $ruta) {
            Storage::disk('public')->delete($mantenimiento->pdf_informe);
        }

        Storage::disk('public')->put($ruta, $pdf->output());

        $mantenimiento->update(['pdf_informe' => $ruta]);

        return $ruta;
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
