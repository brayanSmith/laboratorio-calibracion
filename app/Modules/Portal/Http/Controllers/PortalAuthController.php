<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Portal\Http\Requests\LoginPortalRequest;
use App\Modules\Portal\Mail\AccesoPortalCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Inicio de sesión del portal de clientes. Es una puerta aparte de la del laboratorio (guard
 * "cliente"): el cliente entra con el usuario y la contraseña que le dio el laboratorio y
 * nunca llega a las pantallas internas.
 */
class PortalAuthController extends Controller
{
    /** Largo de la contraseña que se genera al recuperarla. */
    public const LARGO_CONTRASENA = 10;

    /**
     * Show the login page.
     */
    public function create(): Response
    {
        return Inertia::render('portal/auth/login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Inicia la sesión del cliente.
     */
    public function store(LoginPortalRequest $request): RedirectResponse
    {
        $request->autenticar();
        $request->session()->regenerate();

        Auth::guard('cliente')->user()->forceFill(['portal_ultimo_acceso' => now()])->save();

        return redirect()->intended(route('portal.equipos.index'));
    }

    /**
     * Cierra la sesión del cliente.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('cliente')->logout();
        $request->session()->regenerateToken();

        return to_route('portal.login');
    }

    /**
     * Show the "olvidé mi contraseña" page.
     */
    public function olvide(): Response
    {
        return Inertia::render('portal/auth/olvide');
    }

    /**
     * Le genera al cliente una contraseña nueva y se la envía a su correo. Se busca por el
     * usuario o el correo; la respuesta es la misma exista o no, para no revelar qué
     * clientes tienen acceso.
     */
    public function recuperar(Request $request): RedirectResponse
    {
        $dato = Str::lower(trim((string) $request->validate([
            'usuario_o_correo' => ['required', 'string', 'max:255'],
        ])['usuario_o_correo']));

        Cliente::query()
            ->where('portal_activo', true)
            ->where(fn ($cliente) => $cliente
                ->where('portal_usuario', $dato)
                ->orWhereRaw('LOWER(email) = ?', [$dato]))
            ->get()
            ->filter(fn (Cliente $cliente): bool => $cliente->puedeEntrarAlPortal())
            ->each(function (Cliente $cliente): void {
                $contrasena = Str::password(self::LARGO_CONTRASENA, symbols: false);

                try {
                    Mail::to($cliente->email)->send(new AccesoPortalCliente($cliente, $contrasena, AccesoPortalCliente::MOTIVO_RECUPERACION));
                } catch (Throwable $excepcion) {
                    // Si el correo no sale, la contraseña anterior sigue sirviendo.
                    report($excepcion);

                    return;
                }

                $cliente->update(['portal_password' => $contrasena]);
            });

        return to_route('portal.login')->with('status', __('Si los datos corresponden a un cliente con acceso, le enviamos una nueva contraseña a su correo. Revise también la carpeta de spam.'));
    }
}
