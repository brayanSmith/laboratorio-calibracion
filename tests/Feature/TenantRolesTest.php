<?php

use App\Actions\Tenants\SetupTenantRoles;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Equipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function enTenant(Tenant $tenant, Closure $callback): mixed
{
    $registrar = app(PermissionRegistrar::class);
    $anterior = $registrar->getPermissionsTeamId();
    $registrar->setPermissionsTeamId($tenant->id);

    try {
        return $callback();
    } finally {
        $registrar->setPermissionsTeamId($anterior);
    }
}

function equipoDe(Tenant $tenant): Equipo
{
    return (new Equipo)->forceFill(['tenant_id' => $tenant->id]);
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->otroTenant = Tenant::factory()->create();
});

test('al crear un tenant se crean sus roles y su administrador recibe el rol Administrador', function () {
    $platformAdmin = User::factory()->platformAdmin()->create(['tenant_id' => null]);

    $this->actingAs($platformAdmin)->post(route('plataforma.tenants.store'), [
        'nombre' => 'Laboratorio Norte',
        'admin_name' => 'Ana Perez',
        'admin_email' => 'ana@norte.test',
        'admin_password' => 'Temporal#2026x',
    ])->assertRedirect();

    $tenant = Tenant::where('nombre', 'Laboratorio Norte')->firstOrFail();
    $admin = User::where('email', 'ana@norte.test')->firstOrFail();

    expect(Role::where('tenant_id', $tenant->id)->pluck('name')->all())
        ->toEqualCanonicalizing(array_map(fn (TenantRole $rol) => $rol->value, TenantRole::cases()));

    enTenant($tenant, function () use ($admin) {
        expect($admin->hasRole(TenantRole::Administrador->value))->toBeTrue();

        foreach (TenantPermission::cases() as $permission) {
            expect($admin->can($permission->value))->toBeTrue();
        }
    });
});

test('la accion deja el contexto de permisos como estaba', function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->otroTenant->id);

    app(SetupTenantRoles::class)->handle($this->tenant);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($this->otroTenant->id);
});

test('los roles de un tenant no se mezclan con los de otro', function () {
    app(SetupTenantRoles::class)->handle($this->tenant);
    app(SetupTenantRoles::class)->handle($this->otroTenant);

    $idsTenant = Role::where('tenant_id', $this->tenant->id)->pluck('id');
    $idsOtro = Role::where('tenant_id', $this->otroTenant->id)->pluck('id');

    expect($idsTenant)->toHaveCount(4)
        ->and($idsOtro)->toHaveCount(4)
        ->and($idsTenant->intersect($idsOtro))->toBeEmpty();
});

test('un rol asignado en un tenant no concede permisos en otro contexto', function () {
    $user = User::factory()->forTenant($this->tenant)->create();

    enTenant($this->tenant, fn () => expect($user->can(TenantPermission::EquiposEliminar->value))->toBeTrue());

    $user->unsetRelation('roles')->unsetRelation('permissions');

    enTenant($this->otroTenant, fn () => expect($user->can(TenantPermission::EquiposEliminar->value))->toBeFalse());
});

test('volver a configurar un tenant respeta los permisos que personalizo pero restaura al Administrador', function () {
    app(SetupTenantRoles::class)->handle($this->tenant);

    enTenant($this->tenant, function () {
        Role::findByName(TenantRole::Recepcion->value)->syncPermissions([TenantPermission::EquiposEliminar->value]);
        Role::findByName(TenantRole::Administrador->value)->syncPermissions([TenantPermission::EquiposVer->value]);
    });

    app(SetupTenantRoles::class)->handle($this->tenant);

    enTenant($this->tenant, function () {
        $recepcion = Role::findByName(TenantRole::Recepcion->value);
        $administrador = Role::findByName(TenantRole::Administrador->value);

        expect($recepcion->permissions->pluck('name')->all())->toBe([TenantPermission::EquiposEliminar->value])
            ->and($administrador->permissions)->toHaveCount(count(TenantPermission::cases()));
    });

    expect(Role::where('tenant_id', $this->tenant->id)->count())->toBe(4);
});

test('los permisos del usuario se comparten con el frontend', function () {
    $user = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($user)
        ->get(route('equipos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', [
                TenantPermission::EquiposEditar->value,
                TenantPermission::EquiposVer->value,
            ]));
});

test('un usuario sin rol no puede ver los equipos aunque pertenezca al tenant', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->get(route('equipos.index'))->assertForbidden();
});

test('cada rol solo autoriza las acciones sobre equipos que le corresponden', function (TenantRole $rol, bool $ver, bool $crear, bool $editar, bool $eliminar) {
    $user = User::factory()->forTenant($this->tenant, $rol)->create();
    $equipo = equipoDe($this->tenant);

    enTenant($this->tenant, function () use ($user, $equipo, $ver, $crear, $editar, $eliminar) {
        expect(Gate::forUser($user)->allows('viewAny', Equipo::class))->toBe($ver)
            ->and(Gate::forUser($user)->allows('create', Equipo::class))->toBe($crear)
            ->and(Gate::forUser($user)->allows('update', $equipo))->toBe($editar)
            ->and(Gate::forUser($user)->allows('delete', $equipo))->toBe($eliminar);
    });
})->with([
    'Administrador' => [TenantRole::Administrador, true, true, true, true],
    'Metrólogo' => [TenantRole::Metrologo, true, true, true, false],
    'Técnico' => [TenantRole::Tecnico, true, false, true, false],
    'Recepción' => [TenantRole::Recepcion, true, true, false, false],
]);

test('un usuario con permisos no puede actuar sobre equipos de otro tenant', function () {
    $user = User::factory()->forTenant($this->tenant)->create();
    $equipoAjeno = equipoDe($this->otroTenant);

    enTenant($this->tenant, function () use ($user, $equipoAjeno) {
        expect(Gate::forUser($user)->allows('view', $equipoAjeno))->toBeFalse()
            ->and(Gate::forUser($user)->allows('update', $equipoAjeno))->toBeFalse()
            ->and(Gate::forUser($user)->allows('delete', $equipoAjeno))->toBeFalse();
    });
});

test('el comando asigna Administrador solo a los usuarios sin rol que no son administradores de plataforma', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $conRol = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $platformAdmin = User::factory()->platformAdmin()->create(['tenant_id' => $this->tenant->id]);

    $this->artisan('tenants:sync-roles', ['--assign-admins' => true])->assertSuccessful();

    enTenant($this->tenant, function () use ($sinRol, $conRol, $platformAdmin) {
        expect($sinRol->fresh()->hasRole(TenantRole::Administrador->value))->toBeTrue()
            ->and($conRol->fresh()->hasRole(TenantRole::Administrador->value))->toBeFalse()
            ->and($conRol->fresh()->hasRole(TenantRole::Tecnico->value))->toBeTrue()
            ->and($platformAdmin->fresh()->roles)->toBeEmpty();
    });
});
