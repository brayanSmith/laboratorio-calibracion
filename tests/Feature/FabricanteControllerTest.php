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

function crearEquipoDeFabricante(Fabricante $fabricante): Equipo
{
    $tenantId = $fabricante->tenant_id;
    $area = Area::create(['nombre' => 'Área 1', 'tenant_id' => $tenantId]);

    return Equipo::create([
        'codigo' => 'EQ-0001',
        'tipo_equipo_id' => crearTipoEquipo($fabricante->tenant)->id,
        'tipo_tecnologia' => 'DIGITAL',
        'modelo' => 'Modelo X',
        'fabricante_id' => $fabricante->id,
        'numero_serie' => 'SN-1',
        'area_id' => $area->id,
        'bahia_id' => Bahia::create(['nombre' => 'Bahía 1', 'area_id' => $area->id, 'tenant_id' => $tenantId])->id,
        'condicion_actual' => 'Bueno',
        'cliente_id' => Cliente::create(['nombre' => 'Cliente 1', 'email' => 'cliente@example.com', 'tenant_id' => $tenantId])->id,
        'tenant_id' => $tenantId,
    ]);
}

test('lista solo los fabricantes del tenant con la cantidad de equipos asociados', function () {
    $fluke = Fabricante::factory()->for($this->tenant)->create(['nombre' => 'Fluke']);
    crearEquipoDeFabricante($fluke);
    Fabricante::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->get(route('fabricantes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fabricantes/index')
            ->has('fabricantes', 1)
            ->where('fabricantes.0.nombre', 'Fluke')
            ->where('fabricantes.0.equipos_count', 1));
});

test('un usuario sin permiso de ver fabricantes no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('fabricantes.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear fabricantes', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('fabricantes.index'))->assertOk();
    $this->actingAs($tecnico)->post(route('fabricantes.store'), ['nombre' => 'Nuevo'])->assertForbidden();

    expect(Fabricante::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('fabricantes.index'))->assertRedirect(route('login'));
});

test('crea un fabricante asignado al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('fabricantes.store'), [
            'nombre' => 'Mitutoyo',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('fabricantes.index'));

    $fabricante = Fabricante::firstOrFail();

    expect($fabricante->nombre)->toBe('Mitutoyo')
        ->and($fabricante->tenant_id)->toBe($this->tenant->id);
});

test('rechaza un fabricante sin nombre', function () {
    $this->actingAs($this->admin)
        ->post(route('fabricantes.store'), [])
        ->assertSessionHasErrors('nombre');
});

test('el nombre es unico dentro del tenant pero puede repetirse en otro', function () {
    Fabricante::factory()->for($this->tenant)->create(['nombre' => 'Fluke']);

    $this->actingAs($this->admin)
        ->post(route('fabricantes.store'), ['nombre' => 'Fluke'])
        ->assertSessionHasErrors('nombre');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('fabricantes.store'), ['nombre' => 'Fluke'])
        ->assertSessionHasNoErrors();
});

test('actualiza el nombre del fabricante', function () {
    $fabricante = Fabricante::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('fabricantes.update', $fabricante), ['nombre' => 'Ametek'])
        ->assertRedirect(route('fabricantes.index'));

    expect($fabricante->fresh()->nombre)->toBe('Ametek');
});

test('permite guardar un fabricante conservando su propio nombre', function () {
    $fabricante = Fabricante::factory()->for($this->tenant)->create(['nombre' => 'Fluke']);

    $this->actingAs($this->admin)
        ->put(route('fabricantes.update', $fabricante), ['nombre' => 'Fluke'])
        ->assertSessionHasNoErrors();
});

test('no permite actualizar ni eliminar fabricantes de otro tenant', function () {
    $ajeno = Fabricante::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)->put(route('fabricantes.update', $ajeno), ['nombre' => 'Hackeado'])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('fabricantes.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('elimina un fabricante sin equipos asociados', function () {
    $fabricante = Fabricante::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('fabricantes.destroy', $fabricante))
        ->assertRedirect(route('fabricantes.index'));

    expect(Fabricante::find($fabricante->id))->toBeNull()
        ->and(Fabricante::withTrashed()->find($fabricante->id))->not->toBeNull();
});

test('no elimina un fabricante que tiene equipos asociados', function () {
    $fabricante = Fabricante::factory()->for($this->tenant)->create();
    crearEquipoDeFabricante($fabricante);

    $this->actingAs($this->admin)
        ->from(route('fabricantes.index'))
        ->delete(route('fabricantes.destroy', $fabricante))
        ->assertRedirect(route('fabricantes.index'));

    expect(Fabricante::find($fabricante->id))->not->toBeNull();
});

test('permite volver a usar el nombre de un fabricante eliminado', function () {
    Fabricante::factory()->for($this->tenant)->create(['nombre' => 'Fluke'])->delete();

    $this->actingAs($this->admin)
        ->post(route('fabricantes.store'), ['nombre' => 'Fluke'])
        ->assertSessionHasNoErrors();

    expect(Fabricante::where('nombre', 'Fluke')->count())->toBe(1);
});
