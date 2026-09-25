<?php

use App\Models\Tenant;
use App\Models\User;

test('un usuario sin tenant recibe 403 en las rutas de negocio', function () {
    $user = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($user)->get(route('equipos.index'))->assertForbidden();
});

test('un usuario de un tenant inactivo recibe 403', function () {
    $tenant = Tenant::factory()->inactivo()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('equipos.index'))->assertForbidden();
});

test('un usuario de un tenant eliminado recibe 403', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $tenant->delete();

    $this->actingAs($user)->get(route('equipos.index'))->assertForbidden();
});

test('un usuario de un tenant activo accede a las rutas de negocio', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)->get(route('equipos.index'))->assertOk();
});
