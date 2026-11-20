<?php

namespace App\Modules\Ingresos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Ingresos\Actions\DatosSeguimientoIngreso;
use App\Modules\Ingresos\Mail\SeguimientoBahiasEnlace;
use App\Modules\Ingresos\Models\Ingreso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seguimiento público por bahía: el cliente pide con su correo un enlace (firmado y con
 * vencimiento) y con él ve los ingresos y equipos de todas las bahías que tiene asignadas,
 * sin tener que entrar código por código.
 */
class SeguimientoBahiasController extends Controller
{
    /**
     * Días que dura el enlace que llega por correo.
     */
    public const DIAS_VIGENCIA = 7;

    /**
     * Ingresos más recientes que se listan de las bahías del cliente.
     */
    private const LIMITE_INGRESOS = 100;

    /**
     * Envía el enlace al correo, si es de un cliente con bahías asignadas (uno por
     * laboratorio, con su marca). La respuesta es la misma exista o no, para no revelar qué
     * correos están registrados.
     */
    public function enviarEnlace(Request $request): JsonResponse
    {
        $correo = Str::lower(trim((string) $request->validate([
            'correo' => ['required', 'email', 'max:255'],
        ])['correo']));

        $this->clientesConBahias($correo)
            ->groupBy('tenant_id')
            ->each(function (Collection $clientes, int $tenantId) use ($correo): void {
                $bahias = $clientes->flatMap(fn (Cliente $cliente) => $cliente->bahias)->unique('id');

                Mail::to($correo)->queue(new SeguimientoBahiasEnlace(
                    $tenantId,
                    $clientes->pluck('nombre')->unique()->implode(', '),
                    $bahias->sortBy('nombre')->pluck('nombre')->values()->all(),
                    URL::temporarySignedRoute(
                        'seguimiento.bahias',
                        now()->addDays(self::DIAS_VIGENCIA),
                        ['tenant' => $tenantId, 'correo' => $correo],
                    ),
                    self::DIAS_VIGENCIA,
                ));
            });

        return response()->json([
            'mensaje' => __('Si :correo está registrado con bahías asignadas, le enviamos un correo con el enlace para ver sus equipos. Revise también la carpeta de spam.', ['correo' => $correo]),
        ]);
    }

    /**
     * Todos los equipos de los ingresos de las bahías asignadas a los clientes con ese
     * correo, en una sola lista (del ingreso más reciente al más antiguo), para controlar lo
     * que sigue pendiente. Solo con el enlace firmado del correo.
     */
    public function show(Request $request, int $tenant, string $correo, DatosSeguimientoIngreso $datosSeguimiento): Response
    {
        abort_unless($request->hasValidSignature(), 403, __('El enlace venció o no es válido. Pide uno nuevo desde la página de inicio.'));

        $clientes = $this->clientesConBahias(Str::lower($correo))->where('tenant_id', $tenant);
        $bahias = $clientes->flatMap(fn (Cliente $cliente) => $cliente->bahias)->unique('id')->sortBy('nombre')->values();

        $ingresos = Ingreso::query()
            ->whereIn('bahia_id', $bahias->pluck('id'))
            ->with('bahia:id,nombre')
            ->orderByDesc('desde')
            ->orderByDesc('id')
            ->limit(self::LIMITE_INGRESOS)
            ->get();

        $empresa = Empresa::query()->where('tenant_id', $tenant)->first();

        return Inertia::render('seguimiento/bahias', [
            'cliente' => $clientes->pluck('nombre')->unique()->implode(', ') ?: null,
            'empresa' => $empresa ? ['nombre' => $empresa->nombre, 'logo_url' => $empresa->logoUrl()] : null,
            'contacto' => $ingresos->isNotEmpty() ? SeguimientoIngresoController::contacto($ingresos->first()) : null,
            'bahias' => $bahias->map(fn (Bahia $bahia) => [
                'id' => $bahia->id,
                'nombre' => $bahia->nombre,
                'area' => $bahia->area?->nombre,
            ])->values(),
            'equipos' => $datosSeguimiento->equiposDeIngresos($ingresos),
        ]);
    }

    /**
     * Los clientes con ese correo (sin importar mayúsculas) que tienen bahías asignadas.
     *
     * @return Collection<int, Cliente>
     */
    private function clientesConBahias(string $correo): Collection
    {
        return Cliente::query()
            ->whereRaw('LOWER(email) = ?', [$correo])
            ->whereHas('bahias')
            ->with('bahias.area:id,nombre')
            ->get();
    }
}
