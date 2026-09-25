<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TipoEquipo;
use App\Models\TipoEquipoCheckList;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function crearItemChecklist(TipoEquipo $tipoEquipo, string $nombre = 'Limpieza'): TipoEquipoCheckList
{
    return $tipoEquipo->tipoEquipoCheckList()->create([
        'nombre' => $nombre,
        'tenant_id' => $tipoEquipo->tenant_id,
    ]);
}

test('agrega varios items al checklist en una sola peticion con el tenant del tipo', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);

    $this->actingAs($this->admin)
        ->from(route('tipos-equipo.index'))
        ->post(route('tipos-equipo.checklist.store', $tipoEquipo), ['nombres' => ['Limpieza', 'Ajuste', 'Estado del vidrio']])
        ->assertRedirect(route('tipos-equipo.index'));

    $items = TipoEquipoCheckList::orderBy('id')->get();

    expect($items->pluck('nombre')->all())->toBe(['Limpieza', 'Ajuste', 'Estado del vidrio'])
        ->and($items->pluck('tipo_equipo_id')->unique()->all())->toBe([$tipoEquipo->id])
        ->and($items->pluck('tenant_id')->unique()->all())->toBe([$this->tenant->id]);
});

test('rechaza una lista vacia o ausente', function (array $payload) {
    $tipoEquipo = crearTipoEquipo($this->tenant);

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.checklist.store', $tipoEquipo), $payload)
        ->assertSessionHasErrors('nombres');

    expect(TipoEquipoCheckList::count())->toBe(0);
})->with([
    'sin campo' => [[]],
    'lista vacia' => [['nombres' => []]],
]);

test('rechaza toda la lista si un item esta vacio y no guarda ninguno', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.checklist.store', $tipoEquipo), ['nombres' => ['Limpieza', '   ']])
        ->assertSessionHasErrors('nombres.1');

    expect(TipoEquipoCheckList::count())->toBe(0);
});

test('rechaza items repetidos dentro de la misma lista sin distinguir mayusculas', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.checklist.store', $tipoEquipo), ['nombres' => ['Limpieza', 'limpieza']])
        ->assertSessionHasErrors(['nombres.0', 'nombres.1']);

    expect(TipoEquipoCheckList::count())->toBe(0);
});

test('rechaza una lista con mas de 100 items', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);
    $nombres = array_map(fn (int $numero) => "Ítem {$numero}", range(1, 101));

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.checklist.store', $tipoEquipo), ['nombres' => $nombres])
        ->assertSessionHasErrors('nombres');
});

test('el nombre del item es unico dentro del tipo pero puede repetirse en otro tipo', function () {
    $manometro = crearTipoEquipo($this->tenant, 'Manómetro');
    $balanza = crearTipoEquipo($this->tenant, 'Balanza');
    crearItemChecklist($manometro, 'Limpieza');

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.checklist.store', $manometro), ['nombres' => ['Ajuste', 'Limpieza']])
        ->assertSessionHasErrors('nombres.1');

    expect($manometro->tipoEquipoCheckList()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.checklist.store', $balanza), ['nombres' => ['Limpieza']])
        ->assertSessionHasNoErrors();
});

test('permite volver a usar el nombre de un item eliminado', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);
    crearItemChecklist($tipoEquipo, 'Limpieza')->delete();

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.checklist.store', $tipoEquipo), ['nombres' => ['Limpieza']])
        ->assertSessionHasNoErrors();

    expect(TipoEquipoCheckList::where('nombre', 'Limpieza')->count())->toBe(1);
});

test('actualiza el nombre de un item', function () {
    $item = crearItemChecklist(crearTipoEquipo($this->tenant));

    $this->actingAs($this->admin)
        ->put(route('checklist.update', $item), ['nombre' => 'Calibración visual'])
        ->assertRedirect();

    expect($item->fresh()->nombre)->toBe('Calibración visual');
});

test('permite guardar un item conservando su propio nombre', function () {
    $item = crearItemChecklist(crearTipoEquipo($this->tenant), 'Limpieza');

    $this->actingAs($this->admin)
        ->put(route('checklist.update', $item), ['nombre' => 'Limpieza'])
        ->assertSessionHasNoErrors();
});

test('no permite renombrar un item con el nombre de otro item del mismo tipo', function () {
    $tipoEquipo = crearTipoEquipo($this->tenant);
    crearItemChecklist($tipoEquipo, 'Limpieza');
    $otro = crearItemChecklist($tipoEquipo, 'Ajuste');

    $this->actingAs($this->admin)
        ->put(route('checklist.update', $otro), ['nombre' => 'Limpieza'])
        ->assertSessionHasErrors('nombre');

    expect($otro->fresh()->nombre)->toBe('Ajuste');
});

test('elimina un item del checklist sin borrarlo de la base de datos', function () {
    $item = crearItemChecklist(crearTipoEquipo($this->tenant));

    $this->actingAs($this->admin)->delete(route('checklist.destroy', $item))->assertRedirect();

    expect(TipoEquipoCheckList::find($item->id))->toBeNull()
        ->and(TipoEquipoCheckList::withTrashed()->find($item->id))->not->toBeNull();
});

test('no permite gestionar el checklist de un tipo de equipo de otro tenant', function () {
    $tipoAjeno = crearTipoEquipo(Tenant::factory()->create(), 'Ajeno');
    $itemAjeno = crearItemChecklist($tipoAjeno, 'Limpieza');

    $this->actingAs($this->admin)
        ->post(route('tipos-equipo.checklist.store', $tipoAjeno), ['nombres' => ['Intruso']])
        ->assertForbidden();
    $this->actingAs($this->admin)
        ->put(route('checklist.update', $itemAjeno), ['nombre' => 'Hackeado'])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('checklist.destroy', $itemAjeno))->assertForbidden();

    expect($tipoAjeno->tipoEquipoCheckList()->pluck('nombre')->all())->toBe(['Limpieza']);
});

test('un usuario sin permiso de editar tipos de equipo no puede gestionar el checklist', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $tipoEquipo = crearTipoEquipo($this->tenant);
    $item = crearItemChecklist($tipoEquipo);

    $this->actingAs($tecnico)
        ->post(route('tipos-equipo.checklist.store', $tipoEquipo), ['nombres' => ['Nuevo']])
        ->assertForbidden();
    $this->actingAs($tecnico)
        ->put(route('checklist.update', $item), ['nombre' => 'Cambiado'])
        ->assertForbidden();
    $this->actingAs($tecnico)->delete(route('checklist.destroy', $item))->assertForbidden();

    expect(TipoEquipoCheckList::count())->toBe(1)
        ->and($item->fresh()->nombre)->toBe('Limpieza');
});

test('un invitado es redirigido al inicio de sesion al gestionar el checklist', function () {
    $item = crearItemChecklist(crearTipoEquipo($this->tenant));

    $this->put(route('checklist.update', $item), ['nombre' => 'Cambiado'])->assertRedirect(route('login'));
});
