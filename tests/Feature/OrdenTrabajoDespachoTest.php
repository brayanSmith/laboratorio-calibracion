<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Despacho;
use App\Models\OrdenTrabajo;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/** Despacho ya creado (al finalizar una calibración, ver
 * CalibracionController::finalizar()), listo para completarse. */
function crearDespachoListo(Bahia $bahia, array $overrides = [], string $codigo = 'EQ-7001'): Despacho
{
    $programacion = crearEquipoRecibido($bahia, $codigo);
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'FINALIZADO',
        'tenant_id' => $bahia->tenant_id,
    ]);

    return Despacho::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'entrega_autorizada' => true,
        'tenant_id' => $bahia->tenant_id,
        ...$overrides,
    ]);
}

test('lista los despachos sin tecnico para agendar despacho', function () {
    $listo = crearDespachoListo($this->bahia, [], 'EQ-LISTO');

    $yaAgendado = crearDespachoListo($this->bahia, [], 'EQ-YA-AGENDADO');
    $yaAgendado->update(['tecnico_entrega_id' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.despachos-listos'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $listo->id)
        ->assertJsonPath('0.entrega_autorizada', true)
        ->assertJsonPath('0.equipo.codigo', 'EQ-LISTO');
});

test('no incluye despachos de otro tenant en la lista para agendar despacho', function () {
    $ajena = Bahia::factory()->create();
    crearDespachoListo($ajena, [], 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.despachos-listos'))
        ->assertOk()
        ->assertJsonCount(0);
});

test('agenda un despacho con el tecnico elegido y autoriza la entrega', function () {
    $despacho = crearDespachoListo($this->bahia, ['entrega_autorizada' => false]);
    $tecnico = User::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-despacho'), [
            'despachos_listos' => [
                $despacho->id => [
                    'tecnico_id' => $tecnico->id,
                    'entrega_autorizada' => '1',
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($despacho->refresh())
        ->tecnico_entrega_id->toBe($tecnico->id)
        ->entrega_autorizada->toBeTrue();
});

test('deja la entrega sin autorizar cuando no se marca el checkbox', function () {
    $despacho = crearDespachoListo($this->bahia, ['entrega_autorizada' => true]);
    $tecnico = User::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-despacho'), [
            'despachos_listos' => [
                $despacho->id => [
                    'tecnico_id' => $tecnico->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($despacho->refresh()->entrega_autorizada)->toBeFalse();
});

test('no agenda un despacho sin tecnico elegido', function () {
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-despacho'), [
            'despachos_listos' => [
                $despacho->id => [],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($despacho->refresh()->tecnico_entrega_id)->toBeNull();
});

test('exige tecnico cuando se autoriza la entrega', function () {
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-despacho'), [
            'despachos_listos' => [
                $despacho->id => ['entrega_autorizada' => '1'],
            ],
        ])
        ->assertSessionHasErrors('despachos_listos.0.tecnico_id');

    expect($despacho->refresh()->tecnico_entrega_id)->toBeNull();
});

test('rechaza un despacho que no pertenece al tenant al agendar', function () {
    $ajena = Bahia::factory()->create();
    $despachoAjeno = crearDespachoListo($ajena);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-despacho'), [
            'despachos_listos' => [
                $despachoAjeno->id => [],
            ],
        ])
        ->assertSessionHasErrors('despachos_listos.0.id');
});

test('rechaza un despacho que ya tiene tecnico asignado al agendar', function () {
    $despacho = crearDespachoListo($this->bahia);
    $despacho->update(['tecnico_entrega_id' => $this->admin->id]);
    $otroTecnico = User::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-despacho'), [
            'despachos_listos' => [
                $despacho->id => ['tecnico_id' => $otroTecnico->id],
            ],
        ])
        ->assertSessionHasErrors('despachos_listos.0.id');
});

test('un usuario sin permiso de editar equipos no puede agendar despacho, pero si puede listarlos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($recepcion)
        ->getJson(route('orden-trabajos.despachos-listos'))
        ->assertOk()
        ->assertJsonCount(1);

    $this->actingAs($recepcion)
        ->post(route('orden-trabajos.store-despacho'), [
            'despachos_listos' => [
                $despacho->id => ['tecnico_id' => $this->admin->id],
            ],
        ])
        ->assertForbidden();

    expect($despacho->refresh()->tecnico_entrega_id)->toBeNull();
});

test('un invitado no puede agendar despacho', function () {
    $this->post(route('orden-trabajos.store-despacho'), ['despachos_listos' => []])
        ->assertRedirect(route('login'));
});
