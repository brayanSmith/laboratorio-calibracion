<?php

use App\Enums\TenantRole;
use App\Models\EntradaItem;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->item = Item::factory()->for($this->tenant)->create();
});

test('registra una entrada con el precio total calculado y el tenant del item', function () {
    $this->actingAs($this->admin)
        ->post(route('items.entradas.store', $this->item), [
            'cantidad' => 3,
            'precio_unitario' => 12.5,
            'precio_total' => 1,
            'codigo_compra' => 'OC-100',
            'observaciones' => 'Pedido urgente',
        ])
        ->assertRedirect(route('items.index'));

    $entrada = EntradaItem::first();

    expect($entrada->item_id)->toBe($this->item->id)
        ->and($entrada->tenant_id)->toBe($this->tenant->id)
        ->and($entrada->precio_total)->toBe('37.50')
        ->and($entrada->recibido)->toBeFalse()
        ->and($entrada->fecha_recibido)->toBeNull();
});

test('una entrada recibida guarda la fecha de recibido', function () {
    $this->actingAs($this->admin)
        ->post(route('items.entradas.store', $this->item), [
            'cantidad' => 1,
            'precio_unitario' => 10,
            'recibido' => 'on',
            'fecha_recibido' => '2026-10-01',
        ])
        ->assertRedirect(route('items.index'));

    $entrada = EntradaItem::first();

    expect($entrada->recibido)->toBeTrue()
        ->and($entrada->fecha_recibido->toDateString())->toBe('2026-10-01');
});

test('valida los campos requeridos y la fecha cuando esta recibido', function () {
    $this->actingAs($this->admin)
        ->post(route('items.entradas.store', $this->item), ['recibido' => 'on'])
        ->assertSessionHasErrors(['cantidad', 'precio_unitario', 'fecha_recibido']);

    expect(EntradaItem::count())->toBe(0);
});

test('no permite registrar entradas en items de otro tenant', function () {
    $ajeno = Item::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('items.entradas.store', $ajeno), ['cantidad' => 1, 'precio_unitario' => 1])
        ->assertForbidden();

    expect(EntradaItem::count())->toBe(0);
});

test('un tecnico sin permiso de editar items no puede registrar entradas', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)
        ->post(route('items.entradas.store', $this->item), ['cantidad' => 1, 'precio_unitario' => 1])
        ->assertForbidden();
});

test('una entrada recibida actualiza el precio unitario del item', function () {
    $this->item->update(['precio_unitario' => 5]);

    $this->actingAs($this->admin)
        ->post(route('items.entradas.store', $this->item), [
            'cantidad' => 2,
            'precio_unitario' => 8.75,
            'recibido' => 'on',
            'fecha_recibido' => '2026-10-01',
        ]);

    expect($this->item->fresh()->precio_unitario)->toBe('8.75');
});

test('una entrada pendiente no cambia el precio unitario del item', function () {
    $this->item->update(['precio_unitario' => 5]);

    $this->actingAs($this->admin)
        ->post(route('items.entradas.store', $this->item), ['cantidad' => 2, 'precio_unitario' => 8.75]);

    expect($this->item->fresh()->precio_unitario)->toBe('5.00');
});

test('marca una entrada pendiente como recibida y actualiza stock y precio del item', function () {
    $entrada = EntradaItem::factory()->create([
        'item_id' => $this->item->id,
        'cantidad' => 4,
        'precio_unitario' => 20,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('entradas-item.recibir', $entrada), ['fecha_recibido' => '2026-10-05'])
        ->assertRedirect(route('items.index'));

    expect($entrada->fresh())
        ->recibido->toBeTrue()
        ->and($this->item->fresh()->precio_unitario)->toBe('20.00');

    $this->actingAs($this->admin)
        ->get(route('items.index'))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.stock', 4)
            ->has('items.0.entradas_pendientes', 0));
});

test('recibir una entrada antigua no pisa el precio de una recibida mas reciente', function () {
    EntradaItem::factory()->recibido()->create([
        'item_id' => $this->item->id,
        'precio_unitario' => 30,
        'fecha_recibido' => '2026-10-10',
    ]);
    $antigua = EntradaItem::factory()->create(['item_id' => $this->item->id, 'precio_unitario' => 10]);

    $this->actingAs($this->admin)
        ->patch(route('entradas-item.recibir', $antigua), ['fecha_recibido' => '2026-10-01']);

    expect($this->item->fresh()->precio_unitario)->toBe('30.00');
});

test('lista las entradas pendientes de cada item', function () {
    EntradaItem::factory()->create(['item_id' => $this->item->id]);
    EntradaItem::factory()->recibido()->create(['item_id' => $this->item->id]);

    $this->actingAs($this->admin)
        ->get(route('items.index'))
        ->assertInertia(fn ($page) => $page->has('items.0.entradas_pendientes', 1));
});

test('no se puede recibir dos veces ni una entrada de otro tenant', function () {
    $recibida = EntradaItem::factory()->recibido()->create(['item_id' => $this->item->id]);
    $ajena = EntradaItem::factory()->create();

    $this->actingAs($this->admin)
        ->patch(route('entradas-item.recibir', $recibida), ['fecha_recibido' => '2026-12-01']);
    $this->actingAs($this->admin)
        ->patch(route('entradas-item.recibir', $ajena), ['fecha_recibido' => '2026-12-01'])
        ->assertForbidden();

    expect($recibida->fresh()->fecha_recibido->toDateString())->not->toBe('2026-12-01')
        ->and($ajena->fresh()->recibido)->toBeFalse();
});

test('la fecha de recibido es obligatoria al marcar una entrada', function () {
    $entrada = EntradaItem::factory()->create(['item_id' => $this->item->id]);

    $this->actingAs($this->admin)
        ->patch(route('entradas-item.recibir', $entrada), [])
        ->assertSessionHasErrors('fecha_recibido');
});
