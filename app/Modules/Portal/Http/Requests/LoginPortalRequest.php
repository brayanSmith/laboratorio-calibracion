<?php

namespace App\Modules\Portal\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginPortalRequest extends FormRequest
{
    /** Intentos fallidos antes de bloquear un minuto (por usuario e IP). */
    private const INTENTOS = 5;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'portal_usuario' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'portal_usuario' => 'usuario',
            'password' => 'contraseña',
        ];
    }

    /**
     * Inicia la sesión del cliente con su usuario y contraseña, si su acceso está habilitado.
     *
     * @throws ValidationException
     */
    public function autenticar(): void
    {
        $this->ensureIsNotRateLimited();

        $credenciales = [
            'portal_usuario' => Str::lower(trim((string) $this->input('portal_usuario'))),
            'password' => (string) $this->input('password'),
            'portal_activo' => true,
        ];

        $guard = Auth::guard('cliente');

        if (! $guard->attempt($credenciales, $this->boolean('remember')) || ! $guard->user()->puedeEntrarAlPortal()) {
            $guard->logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'portal_usuario' => __('El usuario o la contraseña no son correctos, o tu acceso no está habilitado.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::INTENTOS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'portal_usuario' => __('Demasiados intentos. Vuelve a intentarlo en :segundos segundos.', [
                'segundos' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'portal|'.Str::lower((string) $this->input('portal_usuario')).'|'.$this->ip();
    }
}
