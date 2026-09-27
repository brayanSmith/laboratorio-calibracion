<?php

use App\Enums\TenantRole;
use App\Models\Area;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\Fabricante;
use App\Models\Ingreso;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function crearEquipoEnBahia(Bahia $bahia): Equipo
{
    $tenantId = $bahia->tenant_id;

    return Equipo::create([
        'codigo' => 'EQ-0001',
        'tipo_equipo_id' => crearTipoEquipo($bahia->tenant)->id,
        'tipo_tecnologia' => 'DIGITAL',
        'modelo' => 'Modelo X',
        'fabricante_id' => Fabricante::create(['nombre' => 'Fluke', 'tenant_id' => $tenantId])->id,
        'numero_serie' => 'SN-1',
        'area_id' => $bahia->area_id,
        'bahia_id' => $bahia->id,
        'condicion_actual' => 'Bueno',
        'cliente_id' => Cliente::create(['nombre' => 'Cliente 1', 'email' => 'cliente@example.com', 'tenant_id' => $tenantId])->id,
        'tenant_id' => $tenantId,
    ]);
}

function registrarIngresoEnBahia(Bahia $bahia): Ingreso
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return Ingreso::create([
        'bahia_id' => $bahia->id,
        'desde' => now()->toDateString(),
        'hasta' => now()->toDateString(),
        'tecnico_recibe_id' => 1,
        'cliente_entrega_id' => 1,
        'tenant_id' => $bahia->tenant_id,
    ]);
}

test('lista solo las bahias del tenant con su area y la cantidad de equipos', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $bahia = crearBahiaEnArea($area, 'Bahía 1');
    crearEquipoEnBahia($bahia);

    $areaAjena = Area::create(['nombre' => 'Ajena', 'tenant_id' => Tenant::factory()->create()->id]);
    crearBahiaEnArea($areaAjena, 'Bahía ajena');

    $this->actingAs($this->admin)
        ->get(route('bahias.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bahias/index')
            ->has('bahias', 1)
            ->where('bahias.0.nombre', 'Bahía 1')
            ->where('bahias.0.area_nombre', 'Presión')
            ->where('bahias.0.equipos_count', 1));

    expect($bahia)->not->toBeNull();
});

test('un usuario sin permiso de ver bahias no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('bahias.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear bahias', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($tecnico)->get(route('bahias.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('bahias.store'), ['nombre' => 'Nueva', 'area_id' => $area->id])
        ->assertForbidden();

    expect(Bahia::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('bahias.index'))->assertRedirect(route('login'));
});

test('crea una bahia asignada al area y tenant del usuario', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->post(route('bahias.store'), ['nombre' => 'Bahía 1', 'area_id' => $area->id])
        ->assertRedirect(route('bahias.index'));

    $bahia = Bahia::firstOrFail();

    expect($bahia->nombre)->toBe('Bahía 1')
        ->and($bahia->area_id)->toBe($area->id)
        ->and($bahia->tenant_id)->toBe($this->tenant->id);
});

test('rechaza una bahia sin nombre ni area', function () {
    $this->actingAs($this->admin)
        ->post(route('bahias.store'), [])
        ->assertSessionHasErrors(['nombre', 'area_id']);
});

test('rechaza un area que no pertenece al tenant', function () {
    $areaAjena = Area::create(['nombre' => 'Ajena', 'tenant_id' => Tenant::factory()->create()->id]);

    $this->actingAs($this->admin)
        ->post(route('bahias.store'), ['nombre' => 'Bahía 1', 'area_id' => $areaAjena->id])
        ->assertSessionHasErrors('area_id');
});

test('el nombre es unico dentro del area pero puede repetirse en otra', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $otraArea = Area::create(['nombre' => 'Temperatura', 'tenant_id' => $this->tenant->id]);
    crearBahiaEnArea($area, 'Bahía 1');

    $this->actingAs($this->admin)
        ->post(route('bahias.store'), ['nombre' => 'Bahía 1', 'area_id' => $area->id])
        ->assertSessionHasErrors('nombre');

    $this->actingAs($this->admin)
        ->post(route('bahias.store'), ['nombre' => 'Bahía 1', 'area_id' => $otraArea->id])
        ->assertSessionHasNoErrors();
});

test('actualiza el nombre y el area de la bahia', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $otraArea = Area::create(['nombre' => 'Temperatura', 'tenant_id' => $this->tenant->id]);
    $bahia = crearBahiaEnArea($area, 'Bahía 1');

    $this->actingAs($this->admin)
        ->put(route('bahias.update', $bahia), ['nombre' => 'Bahía 2', 'area_id' => $otraArea->id])
        ->assertRedirect(route('bahias.index'));

    expect($bahia->fresh())
        ->nombre->toBe('Bahía 2')
        ->area_id->toBe($otraArea->id);
});

test('permite guardar una bahia conservando su propio nombre', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $bahia = crearBahiaEnArea($area, 'Bahía 1');

    $this->actingAs($this->admin)
        ->put(route('bahias.update', $bahia), ['nombre' => 'Bahía 1', 'area_id' => $area->id])
        ->assertSessionHasNoErrors();
});

test('no permite actualizar ni eliminar bahias de otro tenant', function () {
    $areaAjena = Area::create(['nombre' => 'Ajena', 'tenant_id' => Tenant::factory()->create()->id]);
    $ajena = crearBahiaEnArea($areaAjena, 'Ajena');

    $this->actingAs($this->admin)
        ->put(route('bahias.update', $ajena), ['nombre' => 'Hackeada', 'area_id' => $areaAjena->id])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('bahias.destroy', $ajena))->assertForbidden();

    expect($ajena->fresh()->nombre)->toBe('Ajena');
});

test('elimina una bahia sin equipos ni ingresos asociados', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $bahia = crearBahiaEnArea($area, 'Bahía 1');

    $this->actingAs($this->admin)
        ->delete(route('bahias.destroy', $bahia))
        ->assertRedirect(route('bahias.index'));

    expect(Bahia::find($bahia->id))->toBeNull()
        ->and(Bahia::withTrashed()->find($bahia->id))->not->toBeNull();
});

test('no elimina una bahia que tiene equipos asociados', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $bahia = crearBahiaEnArea($area, 'Bahía 1');
    crearEquipoEnBahia($bahia);

    $this->actingAs($this->admin)
        ->from(route('bahias.index'))
        ->delete(route('bahias.destroy', $bahia))
        ->assertRedirect(route('bahias.index'));

    expect(Bahia::find($bahia->id))->not->toBeNull();
});

test('no elimina una bahia que tiene ingresos asociados', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $bahia = crearBahiaEnArea($area, 'Bahía 1');
    registrarIngresoEnBahia($bahia);

    $this->actingAs($this->admin)
        ->from(route('bahias.index'))
        ->delete(route('bahias.destroy', $bahia))
        ->assertRedirect(route('bahias.index'));

    expect(Bahia::find($bahia->id))->not->toBeNull();
});
