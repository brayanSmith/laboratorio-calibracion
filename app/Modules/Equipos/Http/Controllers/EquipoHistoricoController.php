<?php

namespace App\Modules\Equipos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Equipos\Http\Requests\StoreEquipoHistoricoRequest;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoHistorico;
use Illuminate\Http\JsonResponse;

class EquipoHistoricoController extends Controller
{
    /**
     * Agrega a mano un servicio histórico al equipo (el mismo dato que se importa desde un
     * CSV), desde la modal de historial. Devuelve el registro para mostrarlo en la pestaña.
     */
    public function store(StoreEquipoHistoricoRequest $request, Equipo $equipo): JsonResponse
    {
        $adjunto = $request->file('adjunto');

        $historico = $equipo->equipoHistoricos()->create([
            ...$request->safe()->only(['tipo_servicio', 'fecha_servicio', 'es_tercero', 'empresa_tercero_id']),
            'adjunto' => $adjunto?->storeAs(
                EquipoHistorico::carpetaAdjuntos($equipo->tenant_id, $equipo->id),
                EquipoHistorico::nombreArchivoAdjunto($adjunto->getClientOriginalName()),
                'public',
            ),
            'tenant_id' => $equipo->tenant_id,
        ]);

        return response()->json($historico->resumen(), 201);
    }
}
