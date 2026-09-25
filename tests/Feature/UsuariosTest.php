<?php

use App\Actions\Tenants\SetupTenantRoles;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function rolIdDe(Tenant $tenant, TenantRole $rol): int
{
    return Role::query()->where('tenant_id', $tenant->id)->where('name', $rol->value)->value('id');
}

/**
 * Usuario que puede gestionar usuarios pero no es Administrador.
 */
function gestorDeUsuarios(Tenant $tenant): User
{
    app(SetupTenantRoles::class)->handle($tenant);

    $rol = Role::create(['name' => 'Gestor de usuarios', 'tenant_id' => $tenant->id]);
    $rol->syncPermissions([TenantPermission::UsuariosGestionar->value]);

    $usuario = User::factory()->create(['tenant_id' => $tenant->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $usuario->assignRole($rol);

    return $usuario;
}

test('el administrador ve los usuarios de su tenant sin administradores de plataforma ni de otros tenants', function () {
    User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    User::factory()->platformAdmin()->create(['tenant_id' => $this->tenant->id]);
    User::factory()->forTenant(Tenant::factory()->create())->create();

    $this->actingAs($this->admin)
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('usuarios/index')
            ->has('usuarios', 2)
            ->has('roles', 4));
});

test('quien no es Administrador no puede asignar el rol Administrador desde la lista de roles', function () {
    $gestor = gestorDeUsuarios($this->tenant);

    $this->actingAs($gestor)
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roles', 4)
            ->where('roles', fn ($roles) => collect($roles)->pluck('name')->doesntContain(TenantRole::Administrador->value)));
});

test('un usuario sin el permiso de gestionar usuarios no puede acceder', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('usuarios.index'))->assertForbidden();
    $this->actingAs($tecnico)->post(route('usuarios.store'), [])->assertForbidden();
});

test('el administrador crea un usuario con rol y contrasena temporal', function () {
    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), [
            'name' => 'Luis Gomez',
            'email' => 'luis@lab.test',
            'role_id' => rolIdDe($this->tenant, TenantRole::Metrologo),
            'password' => 'Temporal#2026x',
        ])
        ->assertRedirect(route('usuarios.index'));

    $usuario = User::where('email', 'luis@lab.test')->firstOrFail();

    expect($usuario->tenant_id)->toBe($this->tenant->id)
        ->and($usuario->must_change_password)->toBeTrue()
        ->and($usuario->is_platform_admin)->toBeFalse()
        ->and(Hash::check('Temporal#2026x', $usuario->password))->toBeTrue()
        ->and($usuario->roles->pluck('name')->all())->toBe([TenantRole::Metrologo->value]);
});

test('crear un usuario valida los datos y no acepta roles de otro tenant', function () {
    $otroTenant = Tenant::factory()->create();
    User::factory()->forTenant($otroTenant)->create();

    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), ['name' => '', 'email' => 'no-es-correo', 'password' => ''])
        ->assertSessionHasErrors(['name', 'email', 'role_id', 'password']);

    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), [
            'name' => 'Intruso',
            'email' => 'intruso@lab.test',
            'role_id' => rolIdDe($otroTenant, TenantRole::Administrador),
            'password' => 'Temporal#2026x',
        ])
        ->assertSessionHasErrors('role_id');

    $this->actingAs($this->admin)
        ->post(route('usuarios.store'), [
            'name' => 'Repetido',
            'email' => $this->admin->email,
            'role_id' => rolIdDe($this->tenant, TenantRole::Tecnico),
            'password' => 'Temporal#2026x',
        ])
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'intruso@lab.test')->exists())->toBeFalse();
});

test('quien gestiona usuarios sin ser Administrador no puede crear un Administrador', function () {
    $gestor = gestorDeUsuarios($this->tenant);

    $this->actingAs($gestor)
        ->post(route('usuarios.store'), [
            'name' => 'Nuevo Admin',
            'email' => 'nuevo-admin@lab.test',
            'role_id' => rolIdDe($this->tenant, TenantRole::Administrador),
            'password' => 'Temporal#2026x',
        ])
        ->assertSessionHasErrors('role_id');

    $this->actingAs($gestor)
        ->post(route('usuarios.store'), [
            'name' => 'Nuevo Tecnico',
            'email' => 'nuevo-tecnico@lab.test',
            'role_id' => rolIdDe($this->tenant, TenantRole::Tecnico),
            'password' => 'Temporal#2026x',
        ])
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'nuevo-admin@lab.test')->exists())->toBeFalse()
        ->and(User::where('email', 'nuevo-tecnico@lab.test')->exists())->toBeTrue();
});

test('se puede corregir el nombre, el correo y el rol de un usuario', function () {
    $usuario = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create(['email' => 'mal@lab.test']);

    $this->actingAs($this->admin)
        ->put(route('usuarios.update', $usuario), [
            'name' => 'Nombre Correcto',
            'email' => 'bien@lab.test',
            'role_id' => rolIdDe($this->tenant, TenantRole::Recepcion),
        ])
        ->assertRedirect(route('usuarios.index'));

    $usuario->refresh();

    expect($usuario->name)->toBe('Nombre Correcto')
        ->and($usuario->email)->toBe('bien@lab.test')
        ->and($usuario->roles->pluck('name')->all())->toBe([TenantRole::Recepcion->value]);
});

test('un Administrador puede degradar a otro Administrador', function () {
    $otroAdmin = User::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('usuarios.update', $otroAdmin), [
            'name' => $otroAdmin->name,
            'email' => $otroAdmin->email,
            'role_id' => rolIdDe($this->tenant, TenantRole::Tecnico),
        ])
        ->assertSessionHasNoErrors();

    expect($otroAdmin->fresh()->hasRole(TenantRole::Tecnico->value))->toBeTrue();
});

test('un usuario no puede cambiarse su propio rol, ni siquiera un Administrador', function () {
    $gestor = gestorDeUsuarios($this->tenant);
    $payload = fn (User $usuario, TenantRole $rol) => [
        'name' => $usuario->name,
        'email' => $usuario->email,
        'role_id' => rolIdDe($this->tenant, $rol),
    ];

    $this->actingAs($gestor)
        ->put(route('usuarios.update', $gestor), $payload($gestor, TenantRole::Metrologo))
        ->assertSessionHasErrors('role_id');

    $this->actingAs($this->admin)
        ->put(route('usuarios.update', $this->admin), $payload($this->admin, TenantRole::Tecnico))
        ->assertSessionHasErrors('role_id');

    expect($this->admin->fresh()->hasRole(TenantRole::Administrador->value))->toBeTrue();
});

test('quien no es Administrador no puede editar ni cambiar la contrasena de un Administrador', function () {
    $gestor = gestorDeUsuarios($this->tenant);

    $this->actingAs($gestor)
        ->put(route('usuarios.update', $this->admin), [
            'name' => 'Hackeado',
            'email' => 'hack@lab.test',
            'role_id' => rolIdDe($this->tenant, TenantRole::Administrador),
        ])
        ->assertForbidden();

    $this->actingAs($gestor)
        ->put(route('usuarios.password', $this->admin), ['password' => 'Temporal#2026x'])
        ->assertForbidden();

    expect(Hash::check('Temporal#2026x', $this->admin->fresh()->password))->toBeFalse()
        ->and($this->admin->fresh()->name)->not->toBe('Hackeado');
});

test('el administrador asigna una nueva contrasena temporal a un usuario', function () {
    $usuario = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($this->admin)
        ->put(route('usuarios.password', $usuario), ['password' => 'Temporal#2026x'])
        ->assertRedirect();

    $usuario->refresh();

    expect(Hash::check('Temporal#2026x', $usuario->password))->toBeTrue()
        ->and($usuario->must_change_password)->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('usuarios.password', $usuario), ['password' => ''])
        ->assertSessionHasErrors('password');
});

test('no se pueden gestionar usuarios de otro tenant ni administradores de plataforma', function () {
    $otroTenant = Tenant::factory()->create();
    $ajeno = User::factory()->forTenant($otroTenant)->create();
    $platformAdmin = User::factory()->platformAdmin()->create(['tenant_id' => $this->tenant->id]);
    $payload = ['name' => 'X', 'email' => 'x@lab.test', 'role_id' => rolIdDe($this->tenant, TenantRole::Tecnico)];

    $this->actingAs($this->admin)->put(route('usuarios.update', $ajeno), $payload)->assertForbidden();
    $this->actingAs($this->admin)->put(route('usuarios.password', $ajeno), ['password' => 'Temporal#2026x'])->assertForbidden();
    $this->actingAs($this->admin)->put(route('usuarios.update', $platformAdmin), $payload)->assertForbidden();
    $this->actingAs($this->admin)->put(route('usuarios.password', $platformAdmin), ['password' => 'Temporal#2026x'])->assertForbidden();

    expect(Hash::check('Temporal#2026x', $ajeno->fresh()->password))->toBeFalse();
});
