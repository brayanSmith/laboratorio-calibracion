<?php

use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    config(['app.multi_tenant' => false]);

    Tenant::factory()->inactivo()->create();
    $this->tenant = Tenant::factory()->create();
    Tenant::factory()->create();
    $this->platformAdmin = User::factory()->platformAdmin()->create(['tenant_id' => null]);
});

test('sin multi tenant el panel de plataforma no existe', function () {
    $this->actingAs($this->platformAdmin)->get(route('plataforma.dashboard'))->assertNotFound();
    $this->actingAs($this->platformAdmin)->get(route('plataforma.tenants.index'))->assertNotFound();
});

test('sin multi tenant el administrador de plataforma no es llevado a su panel', function () {
    $this->post(route('login.store'), [
        'email' => $this->platformAdmin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    // Sin tenant propio no entra a las páginas del laboratorio.
    $this->get(route('dashboard'))->assertForbidden();
});

test('sin multi tenant los invitados ven la marca de la empresa del primer laboratorio activo', function () {
    Empresa::create(['nit' => '900', 'nombre' => 'Lab Principal', 'direccion' => 'Calle 1', 'telefono' => '1', 'color_tema' => '#123456', 'tenant_id' => $this->tenant->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('multiTenant', false)
            ->where('plataforma.nombre', 'Lab Principal')
            ->where('empresa.color_tema', '#123456'));
});

test('con multi tenant los invitados ven la marca de la plataforma', function () {
    config(['app.multi_tenant' => true]);
    Empresa::create(['nit' => '900', 'nombre' => 'Lab Principal', 'direccion' => 'Calle 1', 'telefono' => '1', 'tenant_id' => $this->tenant->id]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('multiTenant', true)
            ->where('plataforma.nombre', config('app.name'))
            ->where('empresa', null));
});

test('sin multi tenant no hay registro publico de usuarios', function () {
    $this->get(route('register'))->assertNotFound();

    $this->post(route('register.store'), [
        'name' => 'Nuevo',
        'email' => 'nuevo@lab.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'nuevo@lab.test')->exists())->toBeFalse();
});
