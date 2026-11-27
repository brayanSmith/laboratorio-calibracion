<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Portal\Http\Requests\CompletarRegistroPortalRequest;
use App\Modules\Portal\Mail\RegistroPortalCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * El cliente crea su propia cuenta del portal: escribe su correo y, si es el de un cliente
 * registrado en el laboratorio que aún no tiene cuenta, le llega un enlace firmado para
 * elegir su usuario y su contraseña. Así solo quien recibe ese correo puede crearla.
 */
class PortalRegistroController extends Controller
{
    /** Minutos que dura el enlace para terminar de crear la cuenta. */
    public const MINUTOS_ENLACE = 60;

    /**
     * Show the "crear cuenta" page.
     */
    public function create(): Response
    {
        return Inertia::render('portal/auth/registro');
    }

    /**
     * Busca los clientes con ese correo y le envía a cada uno que aún no tenga cuenta el
     * enlace para crearla. Si no hay ninguno, el cliente debe hablar con el laboratorio.
     */
    public function store(Request $request): RedirectResponse
    {
        $correo = Str::lower(trim((string) $request->validate([
            'correo' => ['required', 'string', 'email', 'max:255'],
        ])['correo']));

        /** @var Collection<int, Cliente> $clientes */
        $clientes = Cliente::query()
            ->with('tenant')
            ->whereRaw('LOWER(email) = ?', [$correo])
            ->get()
            ->filter(fn (Cliente $cliente): bool => (bool) $cliente->tenant?->activo);

        if ($clientes->isEmpty()) {
            throw ValidationException::withMessages([
                'correo' => __('Ese correo no está registrado como cliente. Por favor contacta al administrador del laboratorio.'),
            ]);
        }

        $sinCuenta = $clientes->filter(fn (Cliente $cliente): bool => $cliente->portal_usuario === null);

        if ($sinCuenta->isEmpty()) {
            throw ValidationException::withMessages([
                'correo' => $clientes->contains('portal_activo', true)
                    ? __('Ese correo ya tiene una cuenta. Entra con tu usuario o usa "¿Olvidaste tu contraseña?".')
                    : __('El acceso de ese correo está deshabilitado. Por favor contacta al administrador del laboratorio.'),
            ]);
        }

        try {
            $sinCuenta->each(fn (Cliente $cliente) => Mail::to($cliente->email)->send(new RegistroPortalCliente(
                $cliente,
                URL::temporarySignedRoute('portal.registro.completar', now()->addMinutes(self::MINUTOS_ENLACE), ['cliente' => $cliente->id]),
            )));
        } catch (Throwable $excepcion) {
            report($excepcion);

            throw ValidationException::withMessages([
                'correo' => __('No pudimos enviar el correo. Intenta de nuevo más tarde.'),
            ]);
        }

        return to_route('portal.login')->with('status', __('Te enviamos un enlace a :correo para terminar de crear tu cuenta. Vence en :minutos minutos; revisa también la carpeta de spam.', [
            'correo' => $correo,
            'minutos' => self::MINUTOS_ENLACE,
        ]));
    }

    /**
     * Show the page to choose the usuario and contraseña (llega desde el enlace firmado).
     */
    public function edit(Request $request, Cliente $cliente): Response|RedirectResponse
    {
        if ($redireccion = $this->yaNoSePuede($cliente)) {
            return $redireccion;
        }

        return Inertia::render('portal/auth/completar-registro', [
            'cliente' => ['nombre' => $cliente->nombre, 'correo' => $cliente->email],
            'accion' => $request->fullUrl(),
        ]);
    }

    /**
     * Crea la cuenta con el usuario y la contraseña elegidos y deja al cliente adentro.
     */
    public function update(CompletarRegistroPortalRequest $request, Cliente $cliente): RedirectResponse
    {
        if ($redireccion = $this->yaNoSePuede($cliente)) {
            return $redireccion;
        }

        $cliente->update([
            'portal_usuario' => $request->validated('portal_usuario'),
            'portal_password' => $request->validated('contrasena'),
            'portal_activo' => true,
            'portal_ultimo_acceso' => now(),
        ]);

        Auth::guard('cliente')->login($cliente);
        $request->session()->regenerate();

        return to_route('portal.equipos.index');
    }

    /**
     * El enlace solo sirve una vez y mientras el laboratorio esté activo: si la cuenta ya
     * existe (la creó el cliente o el laboratorio), se va a iniciar sesión.
     */
    private function yaNoSePuede(Cliente $cliente): ?RedirectResponse
    {
        if ($cliente->portal_usuario !== null) {
            return to_route('portal.login')->with('status', __('Esta cuenta ya fue creada. Inicia sesión con tu usuario.'));
        }

        abort_unless((bool) $cliente->tenant?->activo, 404);

        return null;
    }
}
