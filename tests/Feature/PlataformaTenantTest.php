<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->platformAdmin()->create(['tenant_id' => $this->tenant->id]);
});

test('un usuario no autenticado es redirigido al login', function () {
    $this->get(route('plataforma.tenants.index'))->assertRedirect(route('login'));
});

test('un usuario que no es administrador de plataforma no puede acceder', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->get(route('plataforma.tenants.index'))->assertForbidden();
    $this->actingAs($user)->post(route('plataforma.tenants.store'), ['nombre' => 'Nuevo'])->assertForbidden();
    $this->actingAs($user)->delete(route('plataforma.tenants.destroy', $this->tenant))->assertForbidden();

    expect(Tenant::where('nombre', 'Nuevo')->exists())->toBeFalse();
});

test('el administrador de plataforma ve el listado con el conteo de usuarios', function () {
    $this->actingAs($this->admin)
        ->get(route('plataforma.tenants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/tenants/index')
            ->where('tenants.data.0.users_count', fn ($count) => $count >= 1));
});

test('el administrador de plataforma crea el tenant con su administrador y una contrasena temporal', function () {
    $this->actingAs($this->admin)
        ->post(route('plataforma.tenants.store'), [
            'nombre' => 'Laboratorio Norte',
            'activo' => '1',
            'admin_name' => 'Ana Perez',
            'admin_email' => 'ana@norte.test',
            'admin_password' => 'Temporal#2026x',
        ])
        ->assertRedirect();

    $tenant = Tenant::where('nombre', 'Laboratorio Norte')->firstOrFail();
    $nuevoAdmin = User::where('email', 'ana@norte.test')->firstOrFail();

    expect($tenant->slug)->toBe('laboratorio-norte')
        ->and($tenant->activo)->toBeTrue()
        ->and($nuevoAdmin->tenant_id)->toBe($tenant->id)
        ->and($nuevoAdmin->is_platform_admin)->toBeFalse()
        ->and($nuevoAdmin->must_change_password)->toBeTrue()
        ->and(Hash::check('Temporal#2026x', $nuevoAdmin->password))->toBeTrue();
});

test('crear un tenant valida nombre, slug unico y datos del administrador', function () {
    $this->actingAs($this->admin)
        ->post(route('plataforma.tenants.store'), ['nombre' => '', 'slug' => ''])
        ->assertSessionHasErrors(['nombre', 'admin_name', 'admin_email', 'admin_password']);

    $this->actingAs($this->admin)
        ->post(route('plataforma.tenants.store'), [
            'nombre' => 'Otro',
            'slug' => $this->tenant->slug,
            'admin_name' => 'Otro Admin',
            'admin_email' => $this->admin->email,
            'admin_password' => 'Temporal#2026x',
        ])
        ->assertSessionHasErrors(['slug', 'admin_email']);
});

test('si los datos del administrador son invalidos no se crea el tenant', function () {
    $antes = Tenant::count();

    $this->actingAs($this->admin)
        ->post(route('plataforma.tenants.store'), [
            'nombre' => 'Sin Admin',
            'admin_name' => 'Alguien',
            'admin_email' => 'no-es-un-correo',
            'admin_password' => 'Temporal#2026x',
        ])
        ->assertSessionHasErrors('admin_email');

    expect(Tenant::count())->toBe($antes);
});

test('el administrador de plataforma puede ver y editar un tenant', function () {
    $this->actingAs($this->admin)
        ->get(route('plataforma.tenants.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('plataforma/tenants/show'));

    $this->actingAs($this->admin)
        ->get(route('plataforma.tenants.edit', $this->tenant))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('plataforma/tenants/edit'));
});

test('el administrador de plataforma puede actualizar un tenant y desactivarlo', function () {
    $this->actingAs($this->admin)
        ->put(route('plataforma.tenants.update', $this->tenant), [
            'nombre' => 'Renombrado',
            'slug' => $this->tenant->slug,
        ])
        ->assertRedirect(route('plataforma.tenants.edit', $this->tenant));

    $this->tenant->refresh();

    expect($this->tenant->nombre)->toBe('Renombrado')
        ->and($this->tenant->activo)->toBeFalse();
});

test('se puede eliminar un tenant sin usuarios', function () {
    $vacio = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('plataforma.tenants.destroy', $vacio))
        ->assertRedirect(route('plataforma.tenants.index'));

    $this->assertSoftDeleted($vacio);
});

test('no se puede eliminar un tenant que tiene usuarios', function () {
    $otro = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $otro->id]);

    $this->actingAs($this->admin)
        ->delete(route('plataforma.tenants.destroy', $otro))
        ->assertRedirect();

    expect($otro->fresh())->not->toBeNull();
});

test('un administrador no puede eliminar su propio tenant', function () {
    $this->actingAs($this->admin)
        ->delete(route('plataforma.tenants.destroy', $this->tenant))
        ->assertForbidden();

    expect($this->tenant->fresh())->not->toBeNull();
});
