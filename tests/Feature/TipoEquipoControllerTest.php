<?php

use App\Enums\TenantRole;
use App\Models\Area;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\Fabricante;
use App\Models\Tenant;
use App\Models\TipoEquipo;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function crearEquipoDeTipo(TipoEquipo $tipoEquipo): Equipo
{
    $tenantId = $tipoEquipo->tenant_id;
    $area = Area::create(['nombre' => 'Área 1', 'tenant_id' => $tenantId]);

    return Equipo::create([
        'codigo' => 'EQ-0001',
        'tipo_equipo_id' => $tipoEquipo->id,
        'tipo_tecnologia' => 'DIGITAL',
        'modelo' => 'Modelo X',
        'fabricante_id' => Fabricante::create(['nombre' => 'Fluke', 'tenant_id' => $tenantId])->id,
        'numero_serie' => 'SN-1',
        'area_id' => $area->id,
        'bahia_id' => Bahia::create(['nombre' => 'Bahía 1', 'area_id' => $area->id, 'tenant_id' => $tenantId])->id,
        'condicion_actual' => 'Bueno',
        'cliente_id' => Cliente::create(['nombre' => 'Cliente 1', 'email' => 'cliente@example.com', 'tenant_id' => $tenantId])->id,
        'tenant_id' => $tenantId,
    ]);
}

test('lista solo los tipos de equipo del tenant con la cantidad de equipos asociados', function () {
    $manometro = crearTipoEquipo($this->tenant, 'Manómetro');
    crearEquipoDeTipo($manometro);
    crearTipoEquipo(Tenant::factory()->create(), 'Ajeno');

    $this->actingAs($this->admin)
        ->get(route('tipos-equipo.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tipos-equipo/index')
            ->has('tiposEquipo', 1)
            ->where('tiposEquipo.0.nombre', 'Manómetro')
            ->where('tiposEquipo.0.equipos_count', 1));
});

test('incluye el checklist de cada tipo de equipo en el listado, en orden de creacion', function () {
    $manometro = crearTipoEquipo($this->tenant, 'Manómetro');
    $manometro->tipoEquipoCheckList()->create(['nombre' => 'Limpieza', 'tenant_id' => $this->tenant->id]);
    $manometro->tipoEquipoCheckList()->create(['nombre' => 'Estado del vidrio', 'tenant_id' => $this->tenant->id]);
    $manometro->tipoEquipoCheckList()->create(['nombre' => 'Eliminado', 'tenant_id' => $this->tenant->id])->delete();

    $this->actingAs($this->admin)
        ->get(route('tipos-equipo.index'))
        ->assertInertia(fn ($page) => $page
            ->has('tiposEquipo.0.checklist', 2)
            ->where('tiposEquipo.0.checklist.0.nombre', 'Limpieza')
            ->where('tiposEquipo.0.checklist.1.nombre', 'Estado del vidrio'));
});

test('un usuario sin permiso de ver tipos de equipo no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('tipos-equipo.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear tipos de equipo', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('tipos-equipo.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('tipos-equipo.store'), ['nombre' => 'Nuevo', 'tipo_mantenimiento' => 'A'])
        ->assertForbidden();

    expect(TipoEquipo::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('tipos-equipo.index'))->assertRedirect(route('login'));
});

test('crea un tipo de equipo asignado al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.store'), [
            'nombre' => 'Termómetro',
            'tipo_mantenimiento' => 'B',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('tipos-equipo.index'));

    $tipoEquipo = TipoEquipo::firstOrFail();

    expect($tipoEquipo->nombre)->toBe('Termómetro')
        ->and($tipoEquipo->tipo_mantenimiento)->toBe('B')
        ->and($tipoEquipo->tenant_id)->toBe($this->tenant->id);
});

test('rechaza un tipo de equipo sin nombre ni tipo de mantenimiento', function () {
    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.store'), [])
        ->assertSessionHasErrors(['nombre', 'tipo_mantenimiento']);
});

test('rechaza un tipo de mantenimiento que no es A ni B', function () {
    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.store'), ['nombre' => 'Manómetro', 'tipo_mantenimiento' => 'C'])
        ->assertSessionHasErrors('tipo_mantenimiento');
});

test('el nombre es unico dentro del tenant pero puede repetirse en otro', function () {
    crearTipoEquipo($this->tenant, 'Manómetro');

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.store'), ['nombre' => 'Manómetro', 'tipo_mantenimiento' => 'A'])
        ->assertSessionHasErrors('nombre');

    $otroTenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->forTenant($otroTenant)->create())
        ->post(route('tipos-equipo.store'), ['nombre' => 'Manómetro', 'tipo_mantenimiento' => 'A'])
        ->assertSessionHasNoErrors();
});

test('actualiza el nombre y el tipo de mantenimiento', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);

    $this->actingAs($this->admin)
        ->put(route('tipos-equipo.update', $tipoEquipo), ['nombre' => 'Balanza', 'tipo_mantenimiento' => 'B'])
        ->assertRedirect(route('tipos-equipo.index'));

    expect($tipoEquipo->fresh())
        ->nombre->toBe('Balanza')
        ->tipo_mantenimiento->toBe('B');
});

test('permite guardar un tipo de equipo conservando su propio nombre', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant, 'Manómetro', 'A');

    $this->actingAs($this->admin)
        ->put(route('tipos-equipo.update', $tipoEquipo), ['nombre' => 'Manómetro', 'tipo_mantenimiento' => 'B'])
        ->assertSessionHasNoErrors();

    expect($tipoEquipo->fresh()->tipo_mantenimiento)->toBe('B');
});

test('no permite actualizar ni eliminar tipos de equipo de otro tenant', function () {
    $ajeno = crearTipoEquipo(Tenant::factory()->create(), 'Ajeno');

    $this->actingAs($this->admin)
        ->put(route('tipos-equipo.update', $ajeno), ['nombre' => 'Hackeado', 'tipo_mantenimiento' => 'A'])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('tipos-equipo.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('elimina un tipo de equipo sin equipos asociados', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);

    $this->actingAs($this->admin)
        ->delete(route('tipos-equipo.destroy', $tipoEquipo))
        ->assertRedirect(route('tipos-equipo.index'));

    expect(TipoEquipo::find($tipoEquipo->id))->toBeNull()
        ->and(TipoEquipo::withTrashed()->find($tipoEquipo->id))->not->toBeNull();
});

test('no elimina un tipo de equipo que tiene equipos asociados', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);
    crearEquipoDeTipo($tipoEquipo);

    $this->actingAs($this->admin)
        ->from(route('tipos-equipo.index'))
        ->delete(route('tipos-equipo.destroy', $tipoEquipo))
        ->assertRedirect(route('tipos-equipo.index'));

    expect(TipoEquipo::find($tipoEquipo->id))->not->toBeNull();
});

test('permite volver a usar el nombre de un tipo de equipo eliminado', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant, 'Manómetro');
    $tipoEquipo->delete();

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.store'), ['nombre' => 'Manómetro', 'tipo_mantenimiento' => 'A'])
        ->assertSessionHasNoErrors();

    expect(TipoEquipo::where('nombre', 'Manómetro')->count())->toBe(1);
});
