<?php

namespace App\Http\Controllers;

use App\Models\VersionDocumento;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VersionDocumentoController extends Controller
{
    /**
     * Muestra en el navegador una versión del informe de mantenimiento o del certificado
     * de calibración (la original o una corrección), para consultar las anteriores. La
     * puede ver quien puede ver el PDF de ese mantenimiento o calibración.
     */
    public function show(VersionDocumento $versionDocumento): StreamedResponse
    {
        $documento = $versionDocumento->documentable;

        abort_unless($documento !== null && $documento->tenant_id === auth()->user()->tenant_id, 404);

        Gate::authorize('verPdf', $documento);

        $disco = Storage::disk('public');

        abort_unless($disco->exists($versionDocumento->ruta), 404);

        return $disco->response($versionDocumento->ruta, "{$versionDocumento->codigo}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
