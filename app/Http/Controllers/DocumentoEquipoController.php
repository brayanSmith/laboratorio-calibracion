<?php

namespace App\Http\Controllers;

use App\Http\Requests\Equipos\StoreDocumentoEquipoRequest;
use App\Models\DocumentoEquipo;
use App\Models\Equipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentoEquipoController extends Controller
{
    /**
     * Store a newly created documento for the equipo.
     */
    public function store(StoreDocumentoEquipoRequest $request, Equipo $equipo): RedirectResponse
    {
        $equipo->equipoDocumentos()->create([
            'nombre' => $request->validated('nombre'),
            'archivo' => $request->file('archivo')->store("documentos/{$equipo->tenant_id}/{$equipo->id}", 'public'),
            'tenant_id' => $equipo->tenant_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento agregado.')]);

        return back();
    }

    /**
     * Remove the specified documento.
     */
    public function destroy(DocumentoEquipo $documentoEquipo): RedirectResponse
    {
        Gate::authorize('delete', $documentoEquipo);

        Storage::disk('public')->delete($documentoEquipo->archivo);

        $documentoEquipo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento eliminado.')]);

        return back();
    }
}
