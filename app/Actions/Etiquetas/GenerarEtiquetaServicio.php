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
     * Color de la etiqueta de mantenimiento (naranja), distinto al de calibración.
     */
    public const COLOR_MANTENIMIENTO = '#c2410c';

    /**
     * Genera la etiqueta (PDF) que se pega en el equipo tras un servicio: logo de la empresa,
     * título, fecha del servicio y del próximo, N° del certificado o informe, código y serie
     * del equipo, técnico, un QR que lleva a $urlDocumento (el certificado o informe) y al pie
     * el correo y teléfono del laboratorio.
     *
     * @param  string  $servicio  "calibración" o "mantenimiento", para los rótulos de las fechas
     * @param  string  $rotuloDocumento  "Cert. N°" o "Informe N°"
     */
    public function handle(
        Equipo $equipo,
        string $titulo,
        string $servicio,
        CarbonInterface $fechaServicio,
        ?string $fechaProximoServicio,
        string $urlDocumento,
        string $rotuloDocumento,
        string $numeroDocumento,
        ?string $tecnico,
    ): string {
        $empresa = Empresa::query()->where('tenant_id', $equipo->tenant_id)->first();

        return Pdf::loadView('pdf.etiqueta', [
            'empresa' => $empresa,
            'logo' => $this->comoDataUri($empresa?->logo),
            'titulo' => $titulo,
            // La de calibración lleva el color de tema de la empresa; la de mantenimiento uno
            // fijo, para distinguirlas a simple vista en el equipo.
            'colorFijo' => $servicio === 'mantenimiento' ? self::COLOR_MANTENIMIENTO : null,
            'datos' => [
                "Fecha {$servicio}" => $fechaServicio->format('d/m/Y'),
                "Próx. {$servicio}" => $fechaProximoServicio ? Carbon::parse($fechaProximoServicio)->format('d/m/Y') : '—',
                $rotuloDocumento => $numeroDocumento,
                'ID' => $equipo->codigo,
                'S/N' => $equipo->numero_serie ?: '—',
                'Técnico' => $tecnico ?: '—',
            ],
            'codigoEquipo' => $equipo->codigo,
            // Contacto del laboratorio al pie; si no tiene teléfono propio, el de la empresa.
            'contacto' => array_values(array_filter([
                $empresa?->laboratorio_email,
                $empresa?->laboratorio_telefono ?: $empresa?->telefono,
            ])),
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
