<?php

use App\Enums\TenantRole;
use App\Models\Area;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\Fabricante;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function crearBahiaEnArea(Area $area, string $nombre = 'Bahía 1'): Bahia
{
    return Bahia::create(['nombre' => $nombre, 'area_id' => $area->id, 'tenant_id' => $area->tenant_id]);
}

function crearEquipoEnArea(Area $area): Equipo
{
    $tenantId = $area->tenant_id;

    return Equipo::create([
        'codigo' => 'EQ-0001',
        'tipo_equipo_id' => crearTipoEquipo($area->tenant)->id,
        'tipo_tecnologia' => 'DIGITAL',
        'modelo' => 'Modelo X',
        'fabricante_id' => Fabricante::create(['nombre' => 'Fluke', 'tenant_id' => $tenantId])->id,
        'numero_serie' => 'SN-1',
        'area_id' => $area->id,
        'bahia_id' => crearBahiaEnArea($area)->id,
        'condicion_actual' => 'Bueno',
        'cliente_id' => Cliente::create(['nombre' => 'Cliente 1', 'email' => 'cliente@example.com', 'tenant_id' => $tenantId])->id,
        'tenant_id' => $tenantId,
    ]);
}

test('lista solo las areas del tenant con la cantidad de bahias y equipos', function () {
    $area = crearArea($this->tenant, 'Presión');
    crearEquipoEnArea($area);
    crearBahiaEnArea($area, 'Bahía 2');
    crearArea(Tenant::factory()->create(), 'Ajena');

    $this->actingAs($this->admin)
        ->get(route('areas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('areas/index')
            ->has('areas', 1)
            ->where('areas.0.nombre', 'Presión')
            ->where('areas.0.bahias_count', 2)
            ->where('areas.0.equipos_count', 1));
});

test('un usuario sin permiso de ver areas no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('areas.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear areas', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('areas.index'))->assertOk();
    $this->actingAs($tecnico)->post(route('areas.store'), ['nombre' => 'Nueva'])->assertForbidden();

    expect(Area::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('areas.index'))->assertRedirect(route('login'));
});

test('crea un area asignada al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('areas.store'), [
            'nombre' => 'Temperatura',
            'descripcion' => 'Calibración de termómetros',
            'direccion' => 'Piso 2',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('areas.index'));

    $area = Area::firstOrFail();

    expect($area->nombre)->toBe('Temperatura')
        ->and($area->descripcion)->toBe('Calibración de termómetros')
        ->and($area->direccion)->toBe('Piso 2')
        ->and($area->tenant_id)->toBe($this->tenant->id);
});

test('la descripcion y la direccion son opcionales', function () {
    $this->actingAs($this->admin)
        ->post(route('areas.store'), ['nombre' => 'Masa'])
        ->assertSessionHasNoErrors();

    expect(Area::firstOrFail())->descripcion->toBeNull()->direccion->toBeNull();
});

test('rechaza un area sin nombre', function () {
    $this->actingAs($this->admin)
        ->post(route('areas.store'), [])
        ->assertSessionHasErrors('nombre');
});

test('el nombre es unico dentro del tenant pero puede repetirse en otro', function () {
    crearArea($this->tenant, 'Presión');

    $this->actingAs($this->admin)
        ->post(route('areas.store'), ['nombre' => 'Presión'])
        ->assertSessionHasErrors('nombre');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('areas.store'), ['nombre' => 'Presión'])
        ->assertSessionHasNoErrors();
});

test('actualiza los datos del area', function () {
    $area = crearArea($this->tenant);

    $this->actingAs($this->admin)
        ->put(route('areas.update', $area), ['nombre' => 'Masa', 'descripcion' => 'Balanzas', 'direccion' => null])
        ->assertRedirect(route('areas.index'));

    expect($area->fresh())
        ->nombre->toBe('Masa')
        ->descripcion->toBe('Balanzas');
});

test('permite guardar un area conservando su propio nombre', function () {
    $area = crearArea($this->tenant, 'Presión');

    $this->actingAs($this->admin)
        ->put(route('areas.update', $area), ['nombre' => 'Presión', 'direccion' => 'Piso 1'])
        ->assertSessionHasNoErrors();

    expect($area->fresh()->direccion)->toBe('Piso 1');
});

test('no permite actualizar ni eliminar areas de otro tenant', function () {
    $ajena = crearArea(Tenant::factory()->create(), 'Ajena');

    $this->actingAs($this->admin)->put(route('areas.update', $ajena), ['nombre' => 'Hackeada'])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('areas.destroy', $ajena))->assertForbidden();

    expect($ajena->fresh()->nombre)->toBe('Ajena');
});

test('elimina un area sin equipos ni bahias', function () {
    $area = crearArea($this->tenant);

    $this->actingAs($this->admin)
        ->delete(route('areas.destroy', $area))
        ->assertRedirect(route('areas.index'));

    expect(Area::find($area->id))->toBeNull()
        ->and(Area::withTrashed()->find($area->id))->not->toBeNull();
});

test('no elimina un area que tiene equipos asociados', function () {
    $area = crearArea($this->tenant);
    crearEquipoEnArea($area);

    $this->actingAs($this->admin)
        ->from(route('areas.index'))
        ->delete(route('areas.destroy', $area))
        ->assertRedirect(route('areas.index'));

    expect(Area::find($area->id))->not->toBeNull();
});

test('no elimina un area que tiene bahias asociadas', function () {
    $area = crearArea($this->tenant);
    crearBahiaEnArea($area);

    $this->actingAs($this->admin)
        ->from(route('areas.index'))
        ->delete(route('areas.destroy', $area))
        ->assertRedirect(route('areas.index'));

    expect(Area::find($area->id))->not->toBeNull();
});

test('permite volver a usar el nombre de un area eliminada', function () {
    crearArea($this->tenant, 'Presión')->delete();

    $this->actingAs($this->admin)
        ->post(route('areas.store'), ['nombre' => 'Presión'])
        ->assertSessionHasNoErrors();

    expect(Area::where('nombre', 'Presión')->count())->toBe(1);
});
