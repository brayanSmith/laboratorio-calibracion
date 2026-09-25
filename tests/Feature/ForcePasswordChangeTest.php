<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->forTenant($this->tenant)->create([
        'password' => 'Temporal#2026x',
        'must_change_password' => true,
    ]);
});

test('un usuario con contrasena temporal es redirigido a cambiarla', function () {
    $this->actingAs($this->user)->get(route('equipos.index'))->assertRedirect(route('password-change.edit'));
    $this->actingAs($this->user)->get(route('profile.edit'))->assertRedirect(route('password-change.edit'));
});

test('la pantalla de cambio de contrasena se muestra al usuario con contrasena temporal', function () {
    $this->actingAs($this->user)
        ->get(route('password-change.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/password-change'));
});

test('el usuario con contrasena temporal puede cerrar sesion', function () {
    $this->actingAs($this->user)->post(route('logout'))->assertRedirect();

    $this->assertGuest();
});

test('al cambiar la contrasena el usuario queda liberado', function () {
    $this->actingAs($this->user)
        ->put(route('password-change.update'), [
            'current_password' => 'Temporal#2026x',
            'password' => 'NuevaClave#2026y',
            'password_confirmation' => 'NuevaClave#2026y',
        ])
        ->assertRedirect(route('dashboard'));

    $this->user->refresh();

    expect($this->user->must_change_password)->toBeFalse()
        ->and(Hash::check('NuevaClave#2026y', $this->user->password))->toBeTrue();

    $this->actingAs($this->user)->get(route('equipos.index'))->assertOk();
});

test('la contrasena actual debe ser correcta', function () {
    $this->actingAs($this->user)
        ->put(route('password-change.update'), [
            'current_password' => 'incorrecta',
            'password' => 'NuevaClave#2026y',
            'password_confirmation' => 'NuevaClave#2026y',
        ])
        ->assertSessionHasErrors('current_password');

    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

test('la nueva contrasena debe ser distinta de la temporal y estar confirmada', function () {
    $this->actingAs($this->user)
        ->put(route('password-change.update'), [
            'current_password' => 'Temporal#2026x',
            'password' => 'Temporal#2026x',
            'password_confirmation' => 'Temporal#2026x',
        ])
        ->assertSessionHasErrors('password');

    $this->actingAs($this->user)
        ->put(route('password-change.update'), [
            'current_password' => 'Temporal#2026x',
            'password' => 'NuevaClave#2026y',
            'password_confirmation' => 'otra-cosa',
        ])
        ->assertSessionHasErrors('password');

    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

test('un usuario sin contrasena temporal no ve la pantalla de cambio obligatorio', function () {
    $user = User::factory()->forTenant($this->tenant)->create();

    $this->actingAs($user)->get(route('password-change.edit'))->assertRedirect(route('dashboard'));
    $this->actingAs($user)->get(route('equipos.index'))->assertOk();
});

test('restablecer la contrasena por el flujo de olvido libera la marca de cambio obligatorio', function () {
    $token = Password::createToken($this->user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'NuevaClave#2026y',
        'password_confirmation' => 'NuevaClave#2026y',
    ])->assertRedirect();

    expect($this->user->fresh()->must_change_password)->toBeFalse();
});
