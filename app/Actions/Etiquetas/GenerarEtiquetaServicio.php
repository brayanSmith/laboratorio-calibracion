<?php

namespace App\Actions\Etiquetas;

use App\Models\Empresa;
use App\Modules\Equipos\Models\Equipo;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class GenerarEtiquetaServicio
{
    /**
     * Tamaño de la etiqueta adhesiva: 80 x 50 mm, en puntos (1 mm = 2.8346 pt).
     *
     * @var array{0: int, 1: int, 2: float, 3: float}
     */
    private const TAMANO_PAPEL = [0, 0, 226.77, 141.73];

    /**
     * Genera la etiqueta (PDF) que se pega en el equipo tras un servicio: logo de la empresa,
     * título, fecha del servicio y del próximo, código del equipo, bahía y un QR que lleva
     * a $urlDocumento (el certificado o informe del servicio).
     *
     * @param  string  $servicio  "calibración" o "mantenimiento", para los rótulos de las fechas
     */
    public function handle(
        Equipo $equipo,
        string $titulo,
        string $servicio,
        CarbonInterface $fechaServicio,
        ?string $fechaProximoServicio,
        string $urlDocumento,
    ): string {
        $equipo->loadMissing('bahia');

        $empresa = Empresa::query()->where('tenant_id', $equipo->tenant_id)->first();

        return Pdf::loadView('pdf.etiqueta', [
            'empresa' => $empresa,
            'logo' => $this->comoDataUri($empresa?->logo),
            'titulo' => $titulo,
            'servicio' => $servicio,
            'fechaServicio' => $fechaServicio->format('d/m/Y'),
            'fechaProximoServicio' => $fechaProximoServicio ? Carbon::parse($fechaProximoServicio)->format('d/m/Y') : '—',
            'codigoEquipo' => $equipo->codigo,
            'bahia' => $equipo->bahia?->nombre ?? '—',
            'qr' => 'data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(300, 1)))->writeString($urlDocumento)),
        ])->setPaper(self::TAMANO_PAPEL)->output();
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
