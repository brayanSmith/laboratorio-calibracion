<?php

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function rolDe(Tenant $tenant, TenantRole|string $nombre): Role
{
    return Role::query()
        ->where('tenant_id', $tenant->id)
        ->where('name', $nombre instanceof TenantRole ? $nombre->value : $nombre)
        ->firstOrFail();
}

test('el administrador ve los roles de su tenant con permisos y cantidad de usuarios', function () {
    $this->actingAs($this->admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/index')
            ->has('roles', 4)
            ->has('catalog', 7)
            ->where('roles.0.name', TenantRole::Administrador->value)
            ->where('roles.0.is_system', true)
            ->where('roles.0.users_count', 1));
});

test('un usuario sin el permiso de gestionar roles no puede acceder', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($tecnico)->post(route('roles.store'), ['name' => 'Nuevo'])->assertForbidden();
});

test('se puede crear un rol con permisos', function () {
    $this->actingAs($this->admin)
        ->post(route('roles.store'), [
            'name' => 'Supervisor',
            'permissions' => [TenantPermission::EquiposVer->value, TenantPermission::EquiposEditar->value],
        ])
        ->assertRedirect(route('roles.index'));

    $rol = rolDe($this->tenant, 'Supervisor');

    expect($rol->permissions->pluck('name')->sort()->values()->all())
        ->toBe([TenantPermission::EquiposEditar->value, TenantPermission::EquiposVer->value]);
});

test('se puede crear un rol sin permisos', function () {
    $this->actingAs($this->admin)->post(route('roles.store'), ['name' => 'Vacio'])->assertRedirect();

    expect(rolDe($this->tenant, 'Vacio')->permissions)->toBeEmpty();
});

test('el nombre del rol es unico dentro del tenant pero puede repetirse en otro', function () {
    $this->actingAs($this->admin)
        ->post(route('roles.store'), ['name' => TenantRole::Tecnico->value])
        ->assertSessionHasErrors('name');

    $otroTenant = Tenant::factory()->create();
    $otroAdmin = User::factory()->forTenant($otroTenant)->create();

    $this->actingAs($otroAdmin)
        ->post(route('roles.store'), ['name' => 'Supervisor'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('roles.store'), ['name' => 'Supervisor'])
        ->assertSessionHasNoErrors();
});

test('no se aceptan permisos que no existen en la plataforma', function () {
    $this->actingAs($this->admin)
        ->post(route('roles.store'), ['name' => 'Raro', 'permissions' => ['todo.poder']])
        ->assertSessionHasErrors('permissions.0');

    expect(Role::where('tenant_id', $this->tenant->id)->where('name', 'Raro')->exists())->toBeFalse();
});

test('se puede editar el nombre y los permisos de un rol', function () {
    $rol = rolDe($this->tenant, TenantRole::Recepcion);

    $this->actingAs($this->admin)
        ->get(route('roles.edit', $rol))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('roles/edit')->where('role.name', TenantRole::Recepcion->value));

    $this->actingAs($this->admin)
        ->put(route('roles.update', $rol), [
            'name' => 'Front desk',
            'permissions' => [TenantPermission::EquiposVer->value],
        ])
        ->assertRedirect(route('roles.index'));

    $rol->refresh();

    expect($rol->name)->toBe('Front desk')
        ->and($rol->permissions->pluck('name')->all())->toBe([TenantPermission::EquiposVer->value]);
});

test('quitar todos los permisos a un rol lo deja sin permisos', function () {
    $rol = rolDe($this->tenant, TenantRole::Tecnico);

    $this->actingAs($this->admin)
        ->put(route('roles.update', $rol), ['name' => TenantRole::Tecnico->value])
        ->assertSessionHasNoErrors();

    expect($rol->fresh()->permissions)->toBeEmpty();
});

test('el rol Administrador no se puede editar ni eliminar', function () {
    $administrador = rolDe($this->tenant, TenantRole::Administrador);

    $this->actingAs($this->admin)->get(route('roles.edit', $administrador))->assertForbidden();
    $this->actingAs($this->admin)->put(route('roles.update', $administrador), ['name' => 'Otro'])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('roles.destroy', $administrador))->assertForbidden();

    expect($administrador->fresh()->name)->toBe(TenantRole::Administrador->value)
        ->and($administrador->fresh()->permissions)->toHaveCount(count(TenantPermission::cases()));
});

test('no se puede eliminar un rol con usuarios asignados', function () {
    $tecnico = rolDe($this->tenant, TenantRole::Tecnico);
    User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($this->admin)->delete(route('roles.destroy', $tecnico))->assertRedirect();

    expect(Role::find($tecnico->id))->not->toBeNull();
});

test('se puede eliminar un rol sin usuarios', function () {
    $recepcion = rolDe($this->tenant, TenantRole::Recepcion);

    $this->actingAs($this->admin)
        ->delete(route('roles.destroy', $recepcion))
        ->assertRedirect(route('roles.index'));

    expect(Role::find($recepcion->id))->toBeNull();
});

test('un administrador no puede tocar los roles de otro tenant', function () {
    $otroTenant = Tenant::factory()->create();
    User::factory()->forTenant($otroTenant)->create();
    $rolAjeno = rolDe($otroTenant, TenantRole::Tecnico);

    $this->actingAs($this->admin)->get(route('roles.edit', $rolAjeno))->assertForbidden();
    $this->actingAs($this->admin)->put(route('roles.update', $rolAjeno), ['name' => 'Hackeado'])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('roles.destroy', $rolAjeno))->assertForbidden();

    expect($rolAjeno->fresh()->name)->toBe(TenantRole::Tecnico->value);
});
