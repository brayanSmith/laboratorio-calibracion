<?php

namespace App\Modules\Catalogos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalogos\Http\Requests\UpdateClientePortalRequest;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Portal\Mail\AccesoPortalCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Throwable;

/**
 * El laboratorio le da (o le quita) al cliente el acceso al portal de clientes: su usuario,
 * su contraseña y si está habilitado. Opcionalmente le envía las credenciales por correo.
 */
class ClientePortalController extends Controller
{
    /**
     * Guarda el acceso al portal del cliente.
     */
    public function update(UpdateClientePortalRequest $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validated();

        $cliente->update([
            'portal_activo' => $datos['portal_activo'],
            'portal_usuario' => $datos['portal_usuario'],
            ...($datos['contrasena'] !== null ? ['portal_password' => $datos['contrasena']] : []),
        ]);

        $mensaje = $cliente->portal_activo ? __('Acceso al portal guardado.') : __('Acceso al portal deshabilitado.');

        if ($datos['enviar_correo']) {
            try {
                Mail::to($cliente->email)->send(new AccesoPortalCliente($cliente, $datos['contrasena']));
                $mensaje .= ' '.__('Le enviamos el usuario y la contraseña a :correo.', ['correo' => $cliente->email]);
            } catch (Throwable $excepcion) {
                report($excepcion);

                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => $mensaje.' '.__('Pero no se pudo enviar el correo: compártele la contraseña por otro medio.'),
                ]);

                return back();
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);

        return back();
    }
}
