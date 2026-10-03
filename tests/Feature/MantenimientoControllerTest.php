<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Mantenimiento;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/** Mantenimiento completo: equipo recibido -> orden de trabajo -> mantenimiento. */
function crearMantenimiento(Bahia $bahia, array $overrides = [], string $codigo = 'EQ-5001'): Mantenimiento
{
    $programacion = crearEquipoRecibido($bahia, $codigo);
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $bahia->tenant_id,
    ]);

    return Mantenimiento::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tipo_mantenimiento' => $programacion->refresh()->tipo_mantenimiento,
        'fecha_mantenimiento' => now()->toDateString(),
        'tecnico_id' => User::factory()->forTenant($bahia->tenant)->create()->id,
        'tenant_id' => $bahia->tenant_id,
        ...$overrides,
    ]);
}

test('lista solo los mantenimientos del tenant', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    crearMantenimiento(Bahia::factory()->create(), [], 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('mantenimientos/index')
            ->has('mantenimientos', 1)
            ->where('mantenimientos.0.id', $mantenimiento->id)
            ->where('mantenimientos.0.tecnico_nombre', $mantenimiento->tecnico->name)
            ->where('mantenimientos.0.equipo.codigo', $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->codigo));
});

test('actualiza un mantenimiento sin tocar su tipo_mantenimiento', function () {
    $mantenimiento = crearMantenimiento($this->bahia, ['tipo_mantenimiento' => 'PREVENTIVO']);
    $nuevoTecnico = User::factory()->forTenant($this->tenant)->create();
    $novedad = Novedad::create(['nombre' => 'Repuesto agotado', 'categoria' => 'MANTENIMIENTO', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => '2026-11-01',
            'descripcion' => 'Se cambió el filtro',
            'estado_inicial_equipo' => 'FUERA_DE_SERVICIO',
            'estado_final_equipo' => 'OPERATIVO',
            'estado_mantenimiento' => 'FINALIZADO',
            'tecnico_id' => $nuevoTecnico->id,
            'novedad_id' => $novedad->id,
            'firmado' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimientos.index'));

    expect($mantenimiento->refresh())
        ->tipo_mantenimiento->toBe('PREVENTIVO')
        ->fecha_mantenimiento->toDateString()->toBe('2026-11-01')
        ->descripcion->toBe('Se cambió el filtro')
        ->estado_inicial_equipo->toBe('FUERA_DE_SERVICIO')
        ->estado_final_equipo->toBe('OPERATIVO')
        ->estado_mantenimiento->toBe('FINALIZADO')
        ->tecnico_id->toBe($nuevoTecnico->id)
        ->novedad_id->toBe($novedad->id)
        ->firmado->toBeTrue();
});

test('desmarca firmado cuando no se envia en la actualizacion', function () {
    $mantenimiento = crearMantenimiento($this->bahia, ['firmado' => true, 'estado_mantenimiento' => 'FINALIZADO']);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => $mantenimiento->fecha_mantenimiento->toDateString(),
            'estado_mantenimiento' => 'FINALIZADO',
            'tecnico_id' => $mantenimiento->tecnico_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($mantenimiento->refresh()->firmado)->toBeFalse();
});

test('elimina un mantenimiento con borrado suave', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->delete(route('mantenimientos.destroy', $mantenimiento))
        ->assertRedirect(route('mantenimientos.index'));

    expect(Mantenimiento::find($mantenimiento->id))->toBeNull()
        ->and(Mantenimiento::withTrashed()->find($mantenimiento->id))->not->toBeNull();
});

test('no permite actualizar ni eliminar mantenimientos de otro tenant', function () {
    $ajeno = crearMantenimiento(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $ajeno), [
            'fecha_mantenimiento' => now()->toDateString(),
            'estado_mantenimiento' => 'PENDIENTE',
            'tecnico_id' => $ajeno->tecnico_id,
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('mantenimientos.destroy', $ajeno))
        ->assertForbidden();

    expect(Mantenimiento::find($ajeno->id))->not->toBeNull();
});

test('exige tecnico y estado del mantenimiento al actualizar', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['tecnico_id', 'estado_mantenimiento']);
});

test('rechaza un tecnico que no pertenece al tenant al actualizar un mantenimiento', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $tecnicoAjeno = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => now()->toDateString(),
            'estado_mantenimiento' => 'PENDIENTE',
            'tecnico_id' => $tecnicoAjeno->id,
        ])
        ->assertSessionHasErrors('tecnico_id');
});

test('un tecnico con permiso de editar mantenimientos puede verlos y actualizarlos, pero no eliminarlos', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($tecnico)
        ->get(route('mantenimientos.index'))
        ->assertOk();

    $this->actingAs($tecnico)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => now()->toDateString(),
            'estado_mantenimiento' => 'EN_PROCESO',
            'tecnico_id' => $mantenimiento->tecnico_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($tecnico)
        ->delete(route('mantenimientos.destroy', $mantenimiento))
        ->assertForbidden();
});

test('un usuario de recepcion puede ver los mantenimientos pero no editarlos ni eliminarlos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($recepcion)
        ->get(route('mantenimientos.index'))
        ->assertOk();

    $this->actingAs($recepcion)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => now()->toDateString(),
            'estado_mantenimiento' => 'PENDIENTE',
            'tecnico_id' => $mantenimiento->tecnico_id,
        ])
        ->assertForbidden();

    $this->actingAs($recepcion)
        ->delete(route('mantenimientos.destroy', $mantenimiento))
        ->assertForbidden();
});

test('un invitado no puede ver los mantenimientos', function () {
    $this->get(route('mantenimientos.index'))
        ->assertRedirect(route('login'));
});
