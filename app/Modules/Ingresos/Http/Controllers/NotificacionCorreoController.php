<?php

namespace App\Modules\Ingresos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ingresos\Models\IngresoEnvio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las notificaciones de la campanita: cómo terminaron los correos del ingreso que disparó
 * el usuario (al recibir un ingreso o al reenviarlo), con el motivo si fallaron.
 */
class NotificacionCorreoController extends Controller
{
    /**
     * Cuántas notificaciones se muestran (las más recientes).
     */
    private const LIMITE = 30;

    /**
     * Los correos más recientes del usuario, del más nuevo al más viejo.
     */
    public function index(Request $request): JsonResponse
    {
        $envios = IngresoEnvio::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('ingreso:id,codigo')
            ->latest('id')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (IngresoEnvio $envio) => [
                'id' => $envio->id,
                'ingreso_id' => $envio->ingreso_id,
                'ingreso_codigo' => $envio->ingreso?->codigo,
                'destinatario' => $envio->destinatario,
                'correo' => $envio->correo,
                'estado' => $envio->estado,
                'error' => $envio->error,
                'fecha' => ($envio->enviado_at ?? $envio->updated_at)->toIso8601String(),
                'leido' => $envio->leido_at !== null || $envio->estado === 'EN_COLA',
            ]);

        return response()->json(['notificaciones' => $envios]);
    }

    /**
     * Marca como vistas todas las notificaciones del usuario que ya tienen resultado.
     */
    public function marcarLeidas(Request $request): JsonResponse
    {
        IngresoEnvio::query()
            ->where('user_id', $request->user()->id)
            ->sinLeer()
            ->update(['leido_at' => now()]);

        return response()->json(['sin_leer' => 0]);
    }
}
