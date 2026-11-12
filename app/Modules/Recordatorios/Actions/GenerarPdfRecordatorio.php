<?php

namespace App\Modules\Recordatorios\Actions;

use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerarPdfRecordatorio
{
    /**
     * Genera el PDF resumen del recordatorio y devuelve su contenido. Los equipos se agrupan
     * por bahía (resumen por bahía) o por cliente, y dentro de cada grupo se separan en
     * vencidos, próximos a vencer y agendados.
     *
     * @param  list<array<string, mixed>>  $equipos  Filas de ObtenerEquiposRecordatorio
     * @param  'bahia'|'cliente'  $agruparPor
     */
    public function handle(int $tenantId, string $destinatario, array $equipos, string $agruparPor, int $diasAnticipacion): string
    {
        $empresa = Empresa::query()->where('tenant_id', $tenantId)->first();

        $grupos = collect($equipos)
            ->groupBy($agruparPor)
            ->sortKeys()
            ->map(fn ($filas) => $filas->groupBy('estado'));

        return Pdf::loadView('pdf.recordatorio', [
            'empresa' => $empresa,
            'logo' => $this->comoDataUri($empresa?->logo),
            'destinatario' => $destinatario,
            'agruparPor' => $agruparPor,
            'grupos' => $grupos,
            'conteo' => collect($equipos)->countBy('estado'),
            'diasAnticipacion' => $diasAnticipacion,
            'fechaEmision' => now()->utc(),
        ])->setPaper('letter', 'landscape')->output();
    }

    /**
     * La imagen del disco público como data URI, para que dompdf no tenga que pedirla por HTTP.
     */
    private function comoDataUri(?string $ruta): ?string
    {
        $disco = Storage::disk('public');

        if (! $ruta || ! $disco->exists($ruta)) {
            return null;
        }

        return 'data:'.$disco->mimeType($ruta).';base64,'.base64_encode($disco->get($ruta));
    }
}
