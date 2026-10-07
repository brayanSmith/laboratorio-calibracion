<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Calibracion;
use App\Models\EmpresaTercero;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\ServicioTercero;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    $this->novedadCalibracion = Novedad::create(['nombre' => 'Desviación detectada', 'categoria' => 'CALIBRACION', 'tenant_id' => $this->tenant->id]);
});

/** Orden de trabajo con el mantenimiento ya finalizado, lista para agendar
 * calibración (ver crearEquipoRecibido() en OrdenTrabajoControllerTest.php). */
function crearOrdenListaParaCalibracion(Bahia $bahia, string $codigo = 'EQ-5001'): OrdenTrabajo
{
    $programacion = crearEquipoRecibido($bahia, $codigo);

    return OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'mantenimiento_finalizado' => true,
        'tenant_id' => $bahia->tenant_id,
    ]);
}

test('lista las ordenes con mantenimiento finalizado para agendar calibracion', function () {
    $lista = crearOrdenListaParaCalibracion($this->bahia, 'EQ-LISTO');

    $sinFinalizar = crearOrdenListaParaCalibracion($this->bahia, 'EQ-SIN-FINALIZAR');
    $sinFinalizar->update(['mantenimiento_finalizado' => false]);

    $yaFinalizada = crearOrdenListaParaCalibracion($this->bahia, 'EQ-YA-FINALIZADA');
    $yaFinalizada->update(['calibracion_finalizado' => true]);

    $yaAgendada = crearOrdenListaParaCalibracion($this->bahia, 'EQ-YA-AGENDADA');
    $yaAgendada->update(['listo_para_calibracion' => true]);

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos-calibracion'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $lista->id)
        ->assertJsonPath('0.equipo.codigo', 'EQ-LISTO');
});

test('no incluye ordenes de otro tenant en la lista para agendar calibracion', function () {
    $ajena = Bahia::factory()->create();
    crearOrdenListaParaCalibracion($ajena, 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos-calibracion'))
        ->assertOk()
        ->assertJsonCount(0);
});

test('agenda calibracion con un tecnico propio y crea la calibracion con la novedad elegida', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'calibracion_asignado_tercero' => '0',
                    'tecnico_id' => $this->admin->id,
                    'novedad_id' => $this->novedadCalibracion->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($orden->refresh())
        ->listo_para_calibracion->toBeTrue()
        ->calibracion_asignado_tercero->toBeFalse();

    $calibracion = Calibracion::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($calibracion)
        ->tecnico_id->toBe($this->admin->id)
        ->novedad_id->toBe($this->novedadCalibracion->id)
        ->estado_calibracion->toBe('PENDIENTE')
        ->laboratorio_id->toBeNull()
        ->solicitante_id->toBeNull()
        ->procedimiento_id->toBeNull();

    expect(ServicioTercero::where('orden_trabajo_id', $orden->id)->count())->toBe(0);
});

test('agenda calibracion con tecnico propio sin novedad, dejando el resto para cuando se realice', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $calibracion = Calibracion::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($calibracion)
        ->tecnico_id->toBe($this->admin->id)
        ->novedad_id->toBeNull()
        ->estado_calibracion->toBe('PENDIENTE');
});

test('agenda calibracion asignada a un tercero y crea el servicio_tercero con la empresa elegida', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'calibracion_asignado_tercero' => '1',
                    'empresa_tercero_id' => $empresa->id,
                ],
            ],
        ])
        ->assertRedirect();

    expect($orden->refresh())->calibracion_asignado_tercero->toBeTrue();

    $servicioTercero = ServicioTercero::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($servicioTercero)
        ->tipo_servicio->toBe('CALIBRACION')
        ->empresa_tercero_id->toBe($empresa->id);

    expect(Calibracion::where('orden_trabajo_id', $orden->id)->count())->toBe(0);
});

test('no agenda una orden que no se marco como lista', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => ['listo_para_calibracion' => '0'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($orden->refresh()->listo_para_calibracion)->toBeFalse();
    expect(Calibracion::where('orden_trabajo_id', $orden->id)->count())->toBe(0);
});

test('exige tecnico al agendar calibracion con tecnico propio', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => ['listo_para_calibracion' => '1'],
            ],
        ])
        ->assertSessionHasErrors('ordenes_listas.0.tecnico_id');

    expect($orden->refresh()->listo_para_calibracion)->toBeFalse();
});

test('exige la empresa tercero al agendar calibracion asignada a un tercero', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'calibracion_asignado_tercero' => '1',
                ],
            ],
        ])
        ->assertSessionHasErrors('ordenes_listas.0.empresa_tercero_id');

    expect($orden->refresh()->listo_para_calibracion)->toBeFalse();
});

test('rechaza una orden que no pertenece al tenant al agendar calibracion', function () {
    $ajena = Bahia::factory()->create();
    $ordenAjena = crearOrdenListaParaCalibracion($ajena);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $ordenAjena->id => ['listo_para_calibracion' => '1'],
            ],
        ])
        ->assertSessionHasErrors('ordenes_listas.0.id');
});

test('rechaza una orden sin el mantenimiento finalizado al agendar calibracion', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);
    $orden->update(['mantenimiento_finalizado' => false]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => ['listo_para_calibracion' => '1'],
            ],
        ])
        ->assertSessionHasErrors('ordenes_listas.0.id');
});

test('un usuario sin permiso de editar equipos no puede agendar calibracion, pero si puede listarlas', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($recepcion)
        ->getJson(route('orden-trabajos.equipos-listos-calibracion'))
        ->assertOk()
        ->assertJsonCount(1);

    $this->actingAs($recepcion)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertForbidden();

    expect($orden->refresh()->listo_para_calibracion)->toBeFalse();
});

test('un invitado no puede agendar calibracion', function () {
    $this->post(route('orden-trabajos.store-calibracion'), ['ordenes_listas' => []])
        ->assertRedirect(route('login'));
});
