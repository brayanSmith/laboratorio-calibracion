<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'firmaUrl' => $request->user()->firmaUrl(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->except(['firma', 'eliminar_firma', 'foto', 'eliminar_foto']));

        if ($request->hasFile('firma')) {
            $this->deleteFirma($user);
            $user->firma = $request->file('firma')->store("firmas-usuarios/{$user->id}", 'public');
        } elseif ($request->boolean('eliminar_firma')) {
            $this->deleteFirma($user);
            $user->firma = null;
        }

        if ($request->hasFile('foto')) {
            $this->deleteFoto($user);
            $user->foto = $request->file('foto')->store("fotos-usuarios/{$user->id}", 'public');
        } elseif ($request->boolean('eliminar_foto')) {
            $this->deleteFoto($user);
            $user->foto = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the signature file of the user from storage, if they have one.
     */
    private function deleteFirma(User $user): void
    {
        if ($user->firma) {
            Storage::disk('public')->delete($user->firma);
        }
    }

    /**
     * Delete the profile photo file of the user from storage, if they have one.
     */
    private function deleteFoto(User $user): void
    {
        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
        }
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
