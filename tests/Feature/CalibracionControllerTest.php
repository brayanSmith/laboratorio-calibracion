<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Calibracion;
use App\Models\Laboratorio;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/** Calibracion completa: equipo recibido -> orden de trabajo -> calibracion. */
function crearCalibracion(Bahia $bahia, array $overrides = [], string $codigo = 'EQ-6001'): Calibracion
{
    $programacion = crearEquipoRecibido($bahia, $codigo);
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $bahia->tenant_id,
    ]);

    return Calibracion::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tecnico_id' => User::factory()->forTenant($bahia->tenant)->create()->id,
        'tenant_id' => $bahia->tenant_id,
        ...$overrides,
    ]);
}

test('lista solo las calibraciones del tenant', function () {
    $calibracion = crearCalibracion($this->bahia);
    crearCalibracion(Bahia::factory()->create(), [], 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->get(route('calibraciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calibraciones/index')
            ->has('calibraciones', 1)
            ->where('calibraciones.0.id', $calibracion->id)
            ->where('calibraciones.0.tecnico_nombre', $calibracion->tecnico->name)
            ->where('calibraciones.0.estado_calibracion', 'PENDIENTE')
            ->where('calibraciones.0.equipo.codigo', $calibracion->ordenTrabajo->equipoProgramacion->equipo->codigo));
});

test('actualiza una calibracion', function () {
    $calibracion = crearCalibracion($this->bahia);
    $nuevoTecnico = User::factory()->forTenant($this->tenant)->create();
    $laboratorio = Laboratorio::factory()->for($this->tenant)->create();
    $novedad = Novedad::create(['nombre' => 'Fuera de tolerancia', 'categoria' => 'CALIBRACION', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'tecnico_id' => $nuevoTecnico->id,
            'laboratorio_id' => $laboratorio->id,
            'temperatura' => '21.50',
            'humedad' => '45.30',
            'ajustes_requeridos' => '1',
            'estado_calibracion' => 'FINALIZADO',
            'firmado' => '1',
            'novedad_id' => $novedad->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('calibraciones.index'));

    expect($calibracion->refresh())
        ->tecnico_id->toBe($nuevoTecnico->id)
        ->laboratorio_id->toBe($laboratorio->id)
        ->temperatura->toBe('21.50')
        ->humedad->toBe('45.30')
        ->ajustes_requeridos->toBeTrue()
        ->estado_calibracion->toBe('FINALIZADO')
        ->firmado->toBeTrue()
        ->novedad_id->toBe($novedad->id);
});

test('desmarca firmado y ajustes_requeridos cuando no se envian en la actualizacion', function () {
    $calibracion = crearCalibracion($this->bahia, ['firmado' => true, 'ajustes_requeridos' => true]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'tecnico_id' => $calibracion->tecnico_id,
            'estado_calibracion' => 'PENDIENTE',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($calibracion->refresh())
        ->firmado->toBeFalse()
        ->ajustes_requeridos->toBeFalse();
});

test('elimina una calibracion con borrado suave', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->delete(route('calibraciones.destroy', $calibracion))
        ->assertRedirect(route('calibraciones.index'));

    expect(Calibracion::find($calibracion->id))->toBeNull()
        ->and(Calibracion::withTrashed()->find($calibracion->id))->not->toBeNull();
});

test('no permite actualizar ni eliminar calibraciones de otro tenant', function () {
    $ajeno = crearCalibracion(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $ajeno), [
            'tecnico_id' => $ajeno->tecnico_id,
            'estado_calibracion' => 'PENDIENTE',
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('calibraciones.destroy', $ajeno))
        ->assertForbidden();

    expect(Calibracion::find($ajeno->id))->not->toBeNull();
});

test('exige tecnico y estado de la calibracion al actualizar', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [])
        ->assertSessionHasErrors(['tecnico_id', 'estado_calibracion']);
});

test('rechaza un tecnico que no pertenece al tenant al actualizar una calibracion', function () {
    $calibracion = crearCalibracion($this->bahia);
    $tecnicoAjeno = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'tecnico_id' => $tecnicoAjeno->id,
            'estado_calibracion' => 'PENDIENTE',
        ])
        ->assertSessionHasErrors('tecnico_id');
});

test('un tecnico con permiso de editar calibraciones puede verlas y actualizarlas, pero no eliminarlas', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($tecnico)
        ->get(route('calibraciones.index'))
        ->assertOk();

    $this->actingAs($tecnico)
        ->put(route('calibraciones.update', $calibracion), [
            'tecnico_id' => $calibracion->tecnico_id,
            'estado_calibracion' => 'EN_PROCESO',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($tecnico)
        ->delete(route('calibraciones.destroy', $calibracion))
        ->assertForbidden();
});

test('un usuario de recepcion puede ver las calibraciones pero no editarlas ni eliminarlas', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($recepcion)
        ->get(route('calibraciones.index'))
        ->assertOk();

    $this->actingAs($recepcion)
        ->put(route('calibraciones.update', $calibracion), [
            'tecnico_id' => $calibracion->tecnico_id,
            'estado_calibracion' => 'PENDIENTE',
        ])
        ->assertForbidden();

    $this->actingAs($recepcion)
        ->delete(route('calibraciones.destroy', $calibracion))
        ->assertForbidden();
});

test('un invitado no puede ver las calibraciones', function () {
    $this->get(route('calibraciones.index'))
        ->assertRedirect(route('login'));
});
