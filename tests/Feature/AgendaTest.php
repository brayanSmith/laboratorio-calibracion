<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

test('un invitado no puede ver los eventos de la agenda', function () {
    $this->getJson(route('agenda.eventos'))->assertUnauthorized();
});

test('la agenda lista todos los mantenimientos y calibraciones pendientes', function () {
    $pendiente = crearMantenimiento($this->bahia, ['fecha_mantenimiento' => '2026-10-15'], 'EQ-7001');
    crearMantenimiento($this->bahia, ['fecha_mantenimiento' => '2026-10-16', 'estado_mantenimiento' => 'FINALIZADO'], 'EQ-7003');
    $calibracion = crearCalibracion($this->bahia, ['fecha_calibracion' => '2026-10-20'], 'EQ-7004');

    $respuesta = $this->actingAs($this->admin)
        ->getJson(route('agenda.eventos'))
        ->assertOk();

    expect(collect($respuesta->json())->pluck('id')->sort()->values()->all())
        ->toBe(["CALIBRACION-{$calibracion->id}", "MANTENIMIENTO-{$pendiente->id}"]);
});

test('la agenda exige su propio permiso', function () {
    $usuario = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    $usuario->syncRoles([]);
    $usuario->givePermissionTo('calibraciones.ver');

    $this->actingAs($usuario)->getJson(route('agenda.eventos'))->assertForbidden();
});

test('la agenda no incluye tareas de otro tenant', function () {
    $otro = Tenant::factory()->create();
    $bahiaAjena = Bahia::factory()->for(crearArea($otro))->create();
    crearMantenimiento($bahiaAjena, ['fecha_mantenimiento' => '2026-10-15'], 'EQ-7005');

    $this->actingAs($this->admin)
        ->getJson(route('agenda.eventos'))
        ->assertOk()
        ->assertJsonCount(0);
});

test('la agenda solo incluye los tipos que el rol puede ver', function () {
    crearMantenimiento($this->bahia, ['fecha_mantenimiento' => '2026-10-15'], 'EQ-7006');
    crearCalibracion($this->bahia, ['fecha_calibracion' => '2026-10-15'], 'EQ-7007');
    $usuario = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    $usuario->syncRoles([]);
    $usuario->givePermissionTo(['agenda.ver', 'agenda.ver-todos', 'calibraciones.ver']);

    $tipos = collect(
        $this->actingAs($usuario)
            ->getJson(route('agenda.eventos'))
            ->assertOk()
            ->json(),
    )->pluck('tipo')->unique()->all();

    expect($tipos)->toBe(['CALIBRACION']);
});

test('la agenda ubica los despachos pendientes en su fecha de despacho', function () {
    $tecnico = User::factory()->forTenant($this->tenant)->create();
    $programado = crearDespachoListo($this->bahia, ['tecnico_entrega_id' => $tecnico->id, 'fecha_programada' => '2026-10-18'], 'EQ-7008');
    crearDespachoListo($this->bahia, ['tecnico_entrega_id' => $tecnico->id, 'fecha_programada' => '2026-10-19', 'entrega_recibida' => true], 'EQ-7009');
    crearDespachoListo($this->bahia, [], 'EQ-7010');

    $respuesta = $this->actingAs($this->admin)
        ->getJson(route('agenda.eventos'))
        ->assertOk();

    expect(collect($respuesta->json())->pluck('id')->all())->toBe(["DESPACHO-{$programado->id}"])
        ->and($respuesta->json('0.start'))->toBe('2026-10-18');
});

test('sin el permiso de ver todos, la agenda solo muestra las tareas del usuario', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $propio = crearMantenimiento($this->bahia, ['tecnico_id' => $tecnico->id, 'fecha_mantenimiento' => '2026-10-15'], 'EQ-7011');
    crearMantenimiento($this->bahia, ['fecha_mantenimiento' => '2026-10-15'], 'EQ-7012');

    $ids = collect($this->actingAs($tecnico)->getJson(route('agenda.eventos'))->assertOk()->json())->pluck('id')->all();

    expect($ids)->toBe(["MANTENIMIENTO-{$propio->id}"]);
});
