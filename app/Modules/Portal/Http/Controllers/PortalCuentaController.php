<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mi cuenta" del portal: los datos del cliente y el cambio de su contraseña.
 */
class PortalCuentaController extends Controller
{
    /**
     * Show the account page.
     */
    public function show(Request $request): Response
    {
        /** @var Cliente $cliente */
        $cliente = $request->user('cliente');

        return Inertia::render('portal/cuenta', [
            ...PortalEquipoController::marca($cliente),
            'cuenta' => [
                'usuario' => $cliente->portal_usuario,
                'correo' => $cliente->email,
            ],
        ]);
    }

    /**
     * Cambia la contraseña del cliente, pidiéndole la actual.
     */
    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'contrasena_actual' => ['required', 'string', 'current_password:cliente'],
            'contrasena' => ['required', 'string', Password::min(8), 'confirmed'],
        ], attributes: [
            'contrasena_actual' => 'contraseña actual',
            'contrasena' => 'nueva contraseña',
        ]);

        /** @var Cliente $cliente */
        $cliente = $request->user('cliente');
        $cliente->update(['portal_password' => $datos['contrasena']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contraseña actualizada.')]);

        return to_route('portal.cuenta');
    }
}
