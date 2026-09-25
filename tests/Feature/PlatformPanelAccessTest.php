<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->platformAdmin = User::factory()->platformAdmin()->create(['tenant_id' => null]);
    $this->tenantUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('el administrador de plataforma ve su dashboard con cifras agregadas', function () {
    User::factory()->create(['tenant_id' => $this->tenant->id, 'must_change_password' => true]);
    Tenant::factory()->inactivo()->create();

    $this->actingAs($this->platformAdmin)
        ->get(route('plataforma.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/dashboard')
            ->where('tenants.activos', Tenant::activo()->count())
            ->where('tenants.inactivos', 1)
            ->where('usuarios.total', 2)
            ->where('usuarios.pendientes', 1)
            ->has('recientes'));
});

test('el dashboard de plataforma no cuenta a los administradores de plataforma como usuarios de tenants', function () {
    $this->actingAs($this->platformAdmin)
        ->get(route('plataforma.dashboard'))
        ->assertInertia(fn ($page) => $page->where('usuarios.total', 1));
});

test('un usuario de tenant no puede entrar al panel de plataforma', function () {
    $this->actingAs($this->tenantUser)->get(route('plataforma.dashboard'))->assertForbidden();
});

test('el administrador de plataforma es redirigido fuera de las paginas de los laboratorios', function () {
    $this->actingAs($this->platformAdmin)
        ->get(route('equipos.index'))
        ->assertRedirect(route('plataforma.dashboard'));

    $this->actingAs($this->platformAdmin)
        ->get(route('dashboard'))
        ->assertRedirect(route('plataforma.dashboard'));
});

test('un administrador de plataforma que aun pertenece a un tenant tambien es redirigido', function () {
    $admin = User::factory()->platformAdmin()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($admin)->get(route('equipos.index'))->assertRedirect(route('plataforma.dashboard'));
});

test('el administrador de plataforma entra directamente a su panel al iniciar sesion', function () {
    $this->post(route('login.store'), [
        'email' => $this->platformAdmin->email,
        'password' => 'password',
    ])->assertRedirect('/plataforma');

    $this->assertAuthenticatedAs($this->platformAdmin);
});

test('el usuario de un tenant no es enviado al panel de plataforma al iniciar sesion', function () {
    $this->post(route('login.store'), [
        'email' => $this->tenantUser->email,
        'password' => 'password',
    ])->assertRedirect()->assertRedirectContains('/dashboard');
});

test('el comando convierte a un usuario en administrador de plataforma sin tenant', function () {
    $this->artisan('platform:make-admin', ['email' => $this->tenantUser->email])
        ->assertSuccessful();

    $this->tenantUser->refresh();

    expect($this->tenantUser->is_platform_admin)->toBeTrue()
        ->and($this->tenantUser->tenant_id)->toBeNull();
});

test('el comando falla si el usuario no existe', function () {
    $this->artisan('platform:make-admin', ['email' => 'nadie@example.com'])
        ->assertFailed();
});
