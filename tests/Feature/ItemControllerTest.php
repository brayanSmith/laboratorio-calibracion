<?php

use App\Enums\TenantRole;
use App\Models\Item;
use App\Models\ItemMantenimiento;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function registrarUsoDeItem(Item $item): ItemMantenimiento
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return ItemMantenimiento::create([
        'mantenimiento_id' => 1,
        'item_id' => $item->id,
        'cantidad' => 2,
        'tenant_id' => $item->tenant_id,
    ]);
}

test('lista solo los items del tenant con la cantidad de usos en mantenimientos', function () {
    $filtro = Item::factory()->for($this->tenant)->create(['nombre' => 'Filtro']);
    registrarUsoDeItem($filtro);
    Item::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->get(route('items.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/index')
            ->has('items', 1)
            ->where('items.0.nombre', 'Filtro')
            ->where('items.0.usos_count', 1));
});

test('un usuario sin permiso de ver items no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('items.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear items', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('items.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('items.store'), ['codigo' => 'ITM-1', 'nombre' => 'Nuevo'])
        ->assertForbidden();

    expect(Item::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('items.index'))->assertRedirect(route('login'));
});

test('crea un item asignado al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('items.store'), [
            'codigo' => 'ITM-001',
            'nombre' => 'Aceite neumático',
            'descripcion' => 'Lubricante',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('items.index'));

    $item = Item::firstOrFail();

    expect($item->codigo)->toBe('ITM-001')
        ->and($item->nombre)->toBe('Aceite neumático')
        ->and($item->descripcion)->toBe('Lubricante')
        ->and($item->tenant_id)->toBe($this->tenant->id);
});

test('la descripcion es opcional', function () {
    $this->actingAs($this->admin)
        ->post(route('items.store'), ['codigo' => 'ITM-001', 'nombre' => 'Solvente'])
        ->assertSessionHasNoErrors();

    expect(Item::firstOrFail()->descripcion)->toBeNull();
});

test('rechaza un item sin codigo ni nombre', function () {
    $this->actingAs($this->admin)
        ->post(route('items.store'), [])
        ->assertSessionHasErrors(['codigo', 'nombre']);
});

test('el codigo es unico dentro del tenant pero puede repetirse en otro', function () {
    Item::factory()->for($this->tenant)->create(['codigo' => 'ITM-001']);

    $this->actingAs($this->admin)
        ->post(route('items.store'), ['codigo' => 'ITM-001', 'nombre' => 'Otro'])
        ->assertSessionHasErrors('codigo');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('items.store'), ['codigo' => 'ITM-001', 'nombre' => 'Otro'])
        ->assertSessionHasNoErrors();

    expect(Item::where('codigo', 'ITM-001')->count())->toBe(2);
});

test('al actualizar el codigo no puede coincidir con otro item del mismo tenant', function () {
    Item::factory()->for($this->tenant)->create(['codigo' => 'ITM-001']);
    $item = Item::factory()->for($this->tenant)->create(['codigo' => 'ITM-002']);
    Item::factory()->create(['codigo' => 'ITM-003']);

    $this->actingAs($this->admin)
        ->put(route('items.update', $item), ['codigo' => 'ITM-001', 'nombre' => 'X'])
        ->assertSessionHasErrors('codigo');
    $this->actingAs($this->admin)
        ->put(route('items.update', $item), ['codigo' => 'ITM-003', 'nombre' => 'X'])
        ->assertSessionHasNoErrors();
});

test('actualiza los datos del item', function () {
    $item = Item::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('items.update', $item), ['codigo' => 'ITM-999', 'nombre' => 'Grasa', 'descripcion' => null])
        ->assertRedirect(route('items.index'));

    expect($item->fresh())
        ->codigo->toBe('ITM-999')
        ->nombre->toBe('Grasa');
});

test('permite guardar un item conservando su propio codigo', function () {
    $item = Item::factory()->for($this->tenant)->create(['codigo' => 'ITM-001']);

    $this->actingAs($this->admin)
        ->put(route('items.update', $item), ['codigo' => 'ITM-001', 'nombre' => 'Nuevo nombre'])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->nombre)->toBe('Nuevo nombre');
});

test('no permite actualizar ni eliminar items de otro tenant', function () {
    $ajeno = Item::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->put(route('items.update', $ajeno), ['codigo' => 'X-1', 'nombre' => 'Hackeado'])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('items.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('elimina un item que no se ha usado', function () {
    $item = Item::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('items.destroy', $item))
        ->assertRedirect(route('items.index'));

    expect(Item::find($item->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->not->toBeNull();
});

test('no elimina un item que ya se uso en mantenimientos', function () {
    $item = Item::factory()->for($this->tenant)->create();
    registrarUsoDeItem($item);

    $this->actingAs($this->admin)
        ->from(route('items.index'))
        ->delete(route('items.destroy', $item))
        ->assertRedirect(route('items.index'));

    expect(Item::find($item->id))->not->toBeNull();
});
