<?php

use App\Enums\TenantRole;
use App\Models\Calibracion;
use App\Models\ProcedimientoCalibracion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function registrarCalibracionConProcedimiento(ProcedimientoCalibracion $procedimiento): Calibracion
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return Calibracion::create([
        'orden_trabajo_id' => 1,
        'laboratorio_id' => 1,
        'solicitante_id' => 1,
        'tecnico_id' => 1,
        'procedimiento_id' => $procedimiento->id,
        'estado_calibracion' => 'PENDIENTE',
        'novedad_id' => 1,
        'tenant_id' => $procedimiento->tenant_id,
    ]);
}

test('lista solo los procedimientos de calibracion del tenant con la cantidad de calibraciones', function () {
    $procedimiento = ProcedimientoCalibracion::factory()->for($this->tenant)->create(['nombre' => 'PC-01']);
    registrarCalibracionConProcedimiento($procedimiento);
    ProcedimientoCalibracion::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->get(route('procedimientos-calibracion.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('procedimientos-calibracion/index')
            ->has('procedimientosCalibracion', 1)
            ->where('procedimientosCalibracion.0.nombre', 'PC-01')
            ->where('procedimientosCalibracion.0.calibraciones_count', 1));
});

test('un usuario sin permiso de ver procedimientos no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('procedimientos-calibracion.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear procedimientos de calibracion', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('procedimientos-calibracion.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('procedimientos-calibracion.store'), ['nombre' => 'Nuevo'])
        ->assertForbidden();

    expect(ProcedimientoCalibracion::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('procedimientos-calibracion.index'))->assertRedirect(route('login'));
});

test('crea un procedimiento de calibracion asignado al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('procedimientos-calibracion.store'), [
            'nombre' => 'PC-02 Calibración de torquímetros',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('procedimientos-calibracion.index'));

    $procedimiento = ProcedimientoCalibracion::firstOrFail();

    expect($procedimiento->nombre)->toBe('PC-02 Calibración de torquímetros')
        ->and($procedimiento->tenant_id)->toBe($this->tenant->id);
});

test('rechaza un procedimiento de calibracion sin nombre', function () {
    $this->actingAs($this->admin)
        ->post(route('procedimientos-calibracion.store'), [])
        ->assertSessionHasErrors('nombre');
});

test('el nombre es unico dentro del tenant pero puede repetirse en otro', function () {
    ProcedimientoCalibracion::factory()->for($this->tenant)->create(['nombre' => 'PC-01']);

    $this->actingAs($this->admin)
        ->post(route('procedimientos-calibracion.store'), ['nombre' => 'PC-01'])
        ->assertSessionHasErrors('nombre');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('procedimientos-calibracion.store'), ['nombre' => 'PC-01'])
        ->assertSessionHasNoErrors();
});

test('actualiza el nombre del procedimiento', function () {
    $procedimiento = ProcedimientoCalibracion::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('procedimientos-calibracion.update', $procedimiento), ['nombre' => 'PC-99'])
        ->assertRedirect(route('procedimientos-calibracion.index'));

    expect($procedimiento->fresh()->nombre)->toBe('PC-99');
});

test('permite guardar un procedimiento conservando su propio nombre', function () {
    $procedimiento = ProcedimientoCalibracion::factory()->for($this->tenant)->create(['nombre' => 'PC-01']);

    $this->actingAs($this->admin)
        ->put(route('procedimientos-calibracion.update', $procedimiento), ['nombre' => 'PC-01'])
        ->assertSessionHasNoErrors();
});

test('no permite actualizar ni eliminar procedimientos de otro tenant', function () {
    $ajeno = ProcedimientoCalibracion::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->put(route('procedimientos-calibracion.update', $ajeno), ['nombre' => 'Hackeado'])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('procedimientos-calibracion.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('elimina un procedimiento sin calibraciones asociadas', function () {
    $procedimiento = ProcedimientoCalibracion::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('procedimientos-calibracion.destroy', $procedimiento))
        ->assertRedirect(route('procedimientos-calibracion.index'));

    expect(ProcedimientoCalibracion::find($procedimiento->id))->toBeNull()
        ->and(ProcedimientoCalibracion::withTrashed()->find($procedimiento->id))->not->toBeNull();
});

test('no elimina un procedimiento que tiene calibraciones asociadas', function () {
    $procedimiento = ProcedimientoCalibracion::factory()->for($this->tenant)->create();
    registrarCalibracionConProcedimiento($procedimiento);

    $this->actingAs($this->admin)
        ->from(route('procedimientos-calibracion.index'))
        ->delete(route('procedimientos-calibracion.destroy', $procedimiento))
        ->assertRedirect(route('procedimientos-calibracion.index'));

    expect(ProcedimientoCalibracion::find($procedimiento->id))->not->toBeNull();
});
