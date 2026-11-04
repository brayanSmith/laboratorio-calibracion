<?php

namespace App\Modules\Equipos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Equipos\Http\Requests\BuscarDocumentosRequest;
use App\Modules\Equipos\Http\Requests\StoreDocumentacionModeloRequest;
use App\Modules\Equipos\Http\Requests\StoreDocumentoEquipoRequest;
use App\Modules\Equipos\Models\DocumentoEquipo;
use App\Modules\Equipos\Models\Equipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DocumentoEquipoController extends Controller
{
    /** Máximo de documentos que devuelve el buscador universal. */
    private const MAX_RESULTADOS = 20;

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
     * Agrega la misma documentación a todos los equipos del tenant con el tipo, la marca
     * (fabricante) y el modelo elegidos. Cada archivo se guarda una sola vez y todos esos
     * equipos lo comparten; los documentos propios de cada equipo no se tocan.
     */
    public function storePorModelo(StoreDocumentacionModeloRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $equipos = Equipo::query()
            ->where('tenant_id', $tenantId)
            ->where('tipo_equipo_id', $request->validated('tipo_equipo_id'))
            ->where('fabricante_id', $request->validated('fabricante_id'))
            ->where('modelo', $request->validated('modelo'))
            ->get(['id', 'tenant_id']);

        if ($equipos->isEmpty()) {
            throw ValidationException::withMessages([
                'modelo' => __('No hay equipos con ese tipo, marca y modelo.'),
            ]);
        }

        $documentos = $request->validated('documentos');

        DB::transaction(function () use ($documentos, $equipos, $tenantId): void {
            foreach ($documentos as $documento) {
                $archivo = $documento['archivo']->store("documentos/{$tenantId}/compartidos", 'public');

                foreach ($equipos as $equipo) {
                    $equipo->equipoDocumentos()->create([
                        'nombre' => $documento['nombre'],
                        'archivo' => $archivo,
                        'tenant_id' => $tenantId,
                    ]);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{1} Documentación agregada a 1 equipo.|[2,*] Documentación agregada a :count equipos.',
            $equipos->count(),
        )]);

        return back();
    }

    /**
     * Documentos de los equipos del tenant cuyo nombre, o el código o modelo de su equipo,
     * contiene lo buscado; para el buscador universal (como mucho, MAX_RESULTADOS).
     */
    public function buscar(BuscarDocumentosRequest $request): JsonResponse
    {
        $termino = '%'.addcslashes($request->validated('q'), '%_\\').'%';

        $documentos = DocumentoEquipo::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where(fn (Builder $query) => $query
                ->where('nombre', 'like', $termino)
                ->orWhereHas('equipo', fn (Builder $equipo) => $equipo
                    ->where('codigo', 'like', $termino)
                    ->orWhere('modelo', 'like', $termino)))
            ->with(['equipo:id,codigo,modelo,tipo_equipo_id', 'equipo.tipoEquipo:id,nombre'])
            ->latest()
            ->limit(self::MAX_RESULTADOS)
            ->get()
            ->map(fn (DocumentoEquipo $documento): array => [
                'id' => $documento->id,
                'nombre' => $documento->nombre,
                'url' => $documento->archivoUrl(),
                'equipo_id' => $documento->equipo_id,
                'equipo_codigo' => $documento->equipo?->codigo,
                'equipo_modelo' => $documento->equipo?->modelo,
                'tipo_equipo' => $documento->equipo?->tipoEquipo?->nombre,
            ]);

        return response()->json($documentos);
    }

    /**
     * Remove the specified documento.
     */
    public function destroy(DocumentoEquipo $documentoEquipo): RedirectResponse
    {
        Gate::authorize('delete', $documentoEquipo);

        $documentoEquipo->eliminar();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento eliminado.')]);

        return back();
    }
}
