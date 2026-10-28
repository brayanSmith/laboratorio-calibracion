<?php

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Ingresos\Models\Ingreso;
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

test('las opciones de area para los selectores concatenan el nombre y la descripcion', function () {
    Area::factory()->for($this->tenant)->create(['nombre' => 'CRC', 'descripcion' => 'Centro de Reparación de Componentes']);
    Area::factory()->for($this->tenant)->create(['nombre' => 'SIN', 'descripcion' => null]);

    $this->actingAs($this->admin)
        ->get(route('bahias.index'))
        ->assertInertia(fn ($page) => $page
            ->where('areas.0.etiqueta', 'CRC - Centro de Reparación de Componentes')
            ->where('areas.1.etiqueta', 'SIN'));
});

test('asigna varios usuarios a cada bahia y deja cada bahia solo con los usuarios enviados', function () {
    $area = Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $bahiaNorte = crearBahiaEnArea($area, 'Bahía Norte');
    $bahiaSur = crearBahiaEnArea($area, 'Bahía Sur');
    $ana = User::factory()->forTenant($this->tenant)->create();
    $luis = User::factory()->forTenant($this->tenant)->create();
    $bahiaSur->usuarios()->attach($luis);

    $this->actingAs($this->admin)
        ->put(route('bahias.asignar-usuarios'), ['asignaciones' => [
            ['bahia_id' => $bahiaNorte->id, 'usuario_ids' => [$ana->id, $luis->id]],
            ['bahia_id' => $bahiaSur->id, 'usuario_ids' => [$ana->id]],
        ]])
        ->assertRedirect(route('bahias.index'))
        ->assertSessionHasNoErrors();

    expect($bahiaNorte->usuarios()->pluck('users.id')->sort()->values()->all())->toBe(collect([$ana->id, $luis->id])->sort()->values()->all())
        ->and($bahiaSur->usuarios()->pluck('users.id')->all())->toBe([$ana->id])
        ->and($ana->bahias()->count())->toBe(2);

    $this->actingAs($this->admin)
        ->get(route('bahias.index'))
        ->assertInertia(fn ($page) => $page
            ->where('bahias.1.usuario_ids', [$ana->id])
            ->has('usuarios', 3));
});

test('una bahia enviada sin usuarios queda sin asignaciones', function () {
    $bahia = crearBahiaEnArea(Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]), 'Bahía 1');
    $bahia->usuarios()->attach($this->admin);

    $this->actingAs($this->admin)
        ->put(route('bahias.asignar-usuarios'), ['asignaciones' => [['bahia_id' => $bahia->id, 'usuario_ids' => []]]])
        ->assertSessionHasNoErrors();

    expect($bahia->usuarios()->count())->toBe(0);
});

test('no permite asignar bahias ni usuarios de otro tenant', function () {
    $otro = Tenant::factory()->create();
    $bahia = crearBahiaEnArea(Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]), 'Bahía 1');
    $bahiaAjena = crearBahiaEnArea(Area::create(['nombre' => 'Ajena', 'tenant_id' => $otro->id]), 'Bahía ajena');
    $usuarioAjeno = User::factory()->forTenant($otro)->create();

    $this->actingAs($this->admin)
        ->put(route('bahias.asignar-usuarios'), ['asignaciones' => [
            ['bahia_id' => $bahia->id, 'usuario_ids' => [$usuarioAjeno->id]],
            ['bahia_id' => $bahiaAjena->id, 'usuario_ids' => [$this->admin->id]],
        ]])
        ->assertSessionHasErrors(['asignaciones.0.usuario_ids.0', 'asignaciones.1.bahia_id']);

    expect(DB::table('bahia_user')->count())->toBe(0);
});

test('asignar usuarios a bahias requiere su propio permiso', function () {
    $bahia = crearBahiaEnArea(Area::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]), 'Bahía 1');
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::BahiasVer, TenantPermission::BahiasEditar]);
    $datos = ['asignaciones' => [['bahia_id' => $bahia->id, 'usuario_ids' => [$usuario->id]]]];

    $this->actingAs($usuario)->put(route('bahias.asignar-usuarios'), $datos)->assertForbidden();
    $this->actingAs($usuario)->get(route('bahias.index'))->assertInertia(fn ($page) => $page->where('usuarios', []));

    $usuario->givePermissionTo(TenantPermission::BahiasAsignarUsuarios->value);

    $this->actingAs($usuario)->put(route('bahias.asignar-usuarios'), $datos)->assertSessionHasNoErrors();
    expect($bahia->usuarios()->pluck('users.id')->all())->toBe([$usuario->id]);
});
