<?php

use App\Enums\TenantRole;
use App\Models\Calibracion;
use App\Models\Novedad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function registrarCalibracionConNovedad(Novedad $novedad): Calibracion
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return Calibracion::create([
        'orden_trabajo_id' => 1,
        'fecha_calibracion' => now()->toDateString(),
        'laboratorio_id' => 1,
        'solicitante_id' => 1,
        'tecnico_id' => 1,
        'procedimiento_id' => 1,
        'estado_calibracion' => 'PENDIENTE',
        'novedad_id' => $novedad->id,
        'tenant_id' => $novedad->tenant_id,
    ]);
}

test('lista solo las novedades del tenant con su cantidad de usos', function () {
    $novedad = Novedad::factory()->for($this->tenant)->create(['nombre' => 'Daño visible', 'categoria' => 'CALIBRACION']);
    registrarCalibracionConNovedad($novedad);
    Novedad::factory()->create(['nombre' => 'Ajena']);

    $this->actingAs($this->admin)
        ->get(route('novedades.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('novedades/index')
            ->has('novedades', 1)
            ->where('novedades.0.nombre', 'Daño visible')
            ->where('novedades.0.categoria', 'CALIBRACION')
            ->where('novedades.0.usos_count', 1));
});

test('un usuario sin permiso de ver novedades no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('novedades.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear novedades', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('novedades.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('novedades.store'), ['nombre' => 'Nueva', 'categoria' => 'INGRESO'])
        ->assertForbidden();

    expect(Novedad::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('novedades.index'))->assertRedirect(route('login'));
});

test('crea una novedad asignada al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('novedades.store'), [
            'nombre' => 'Equipo sin accesorios',
            'categoria' => 'INGRESO',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('novedades.index'));

    $novedad = Novedad::firstOrFail();

    expect($novedad->nombre)->toBe('Equipo sin accesorios')
        ->and($novedad->categoria)->toBe('INGRESO')
        ->and($novedad->tenant_id)->toBe($this->tenant->id);
});

test('valida el nombre y que la categoria sea una de las permitidas', function () {
    $this->actingAs($this->admin)
        ->post(route('novedades.store'), [])
        ->assertSessionHasErrors(['nombre', 'categoria']);

    $this->actingAs($this->admin)
        ->post(route('novedades.store'), ['nombre' => 'X', 'categoria' => 'OTRA'])
        ->assertSessionHasErrors('categoria');
});

test('el nombre es unico por categoria dentro del tenant', function () {
    Novedad::factory()->for($this->tenant)->create(['nombre' => 'Daño', 'categoria' => 'INGRESO']);

    $this->actingAs($this->admin)
        ->post(route('novedades.store'), ['nombre' => 'Daño', 'categoria' => 'INGRESO'])
        ->assertSessionHasErrors('nombre');

    $this->actingAs($this->admin)
        ->post(route('novedades.store'), ['nombre' => 'Daño', 'categoria' => 'SALIDA'])
        ->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('novedades.store'), ['nombre' => 'Daño', 'categoria' => 'INGRESO'])
        ->assertSessionHasNoErrors();
});

test('actualiza una novedad conservando su propio nombre', function () {
    $novedad = Novedad::factory()->for($this->tenant)->create(['nombre' => 'Daño', 'categoria' => 'INGRESO']);

    $this->actingAs($this->admin)
        ->put(route('novedades.update', $novedad), ['nombre' => 'Daño', 'categoria' => 'SALIDA'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->put(route('novedades.update', $novedad), ['nombre' => 'Golpe', 'categoria' => 'SALIDA'])
        ->assertRedirect(route('novedades.index'));

    expect($novedad->fresh()->nombre)->toBe('Golpe')
        ->and($novedad->fresh()->categoria)->toBe('SALIDA');
});

test('no permite actualizar ni eliminar novedades de otro tenant', function () {
    $ajena = Novedad::factory()->create(['nombre' => 'Ajena']);

    $this->actingAs($this->admin)
        ->put(route('novedades.update', $ajena), ['nombre' => 'Hackeada', 'categoria' => 'INGRESO'])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('novedades.destroy', $ajena))->assertForbidden();

    expect($ajena->fresh()->nombre)->toBe('Ajena');
});

test('elimina una novedad sin usos', function () {
    $novedad = Novedad::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('novedades.destroy', $novedad))
        ->assertRedirect(route('novedades.index'));

    expect(Novedad::find($novedad->id))->toBeNull()
        ->and(Novedad::withTrashed()->find($novedad->id))->not->toBeNull();
});

test('no elimina una novedad que ya se uso', function () {
    $novedad = Novedad::factory()->for($this->tenant)->create(['categoria' => 'CALIBRACION']);
    registrarCalibracionConNovedad($novedad);

    $this->actingAs($this->admin)
        ->from(route('novedades.index'))
        ->delete(route('novedades.destroy', $novedad))
        ->assertRedirect(route('novedades.index'));

    expect(Novedad::find($novedad->id))->not->toBeNull();
});
