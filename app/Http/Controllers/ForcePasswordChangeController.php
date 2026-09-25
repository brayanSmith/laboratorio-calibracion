<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForcePasswordChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    /**
     * Show the screen where a user replaces their temporary password.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/password-change', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Replace the temporary password and release the user into the application.
     */
    public function update(ForcePasswordChangeRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->password,
            'must_change_password' => false,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contraseña actualizada.')]);

        return to_route('dashboard');
    }
}
