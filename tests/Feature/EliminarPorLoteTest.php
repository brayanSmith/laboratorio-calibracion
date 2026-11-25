<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoHistorico;
use App\Modules\Inventario\Models\Item;
use App\Modules\Mantenimientos\Models\ItemMantenimiento;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

test('elimina por lote los items elegidos y salta los que no se pueden eliminar, con su motivo', function () {
    [$libre, $otroLibre, $usado] = Item::factory()->for($this->tenant)->count(3)->create();
    DB::statement('PRAGMA defer_foreign_keys = ON');
    ItemMantenimiento::create(['mantenimiento_id' => 1, 'item_id' => $usado->id, 'cantidad' => 1, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->from(route('items.index'))
        ->post(route('items.destroy-lote'), ['ids' => [$libre->id, $otroLibre->id, $usado->id]])
        ->assertRedirect(route('items.index'))
        ->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => 'Se eliminaron 2 registros. 1 no se pudo eliminar: No se puede eliminar un ítem que ya se usó en mantenimientos. (1)',
        ]);

    expect(Item::pluck('id')->all())->toBe([$usado->id]);
});

test('no elimina por lote registros de otro tenant', function () {
    $ajeno = Fabricante::factory()->create();
    $propio = Fabricante::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(route('fabricantes.destroy-lote'), ['ids' => [$ajeno->id, $propio->id]])
        ->assertInertiaFlash('toast.message', 'Se eliminó 1 registro.');

    expect(Fabricante::find($ajeno->id))->not->toBeNull()
        ->and(Fabricante::find($propio->id))->toBeNull();
});

test('eliminar por lote requiere el permiso de lote del modulo ademas del de eliminar', function () {
    $item = Item::factory()->for($this->tenant)->create();
    $soloEliminar = usuarioConPermisos($this->tenant, [TenantPermission::ItemsVer, TenantPermission::ItemsEliminar]);
    $soloLote = usuarioConPermisos($this->tenant, [TenantPermission::ItemsVer, TenantPermission::ItemsEliminarLote]);

    $this->actingAs($soloEliminar)
        ->post(route('items.destroy-lote'), ['ids' => [$item->id]])
        ->assertForbidden();

    $this->actingAs($soloLote)
        ->post(route('items.destroy-lote'), ['ids' => [$item->id]])
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'No se eliminó ningún registro. 1 no se pudo eliminar: No tienes permiso para eliminarlo. (1)',
        ]);

    expect(Item::find($item->id))->not->toBeNull();
});

test('los equipos con historial se saltan al eliminar por lote', function () {
    $sinHistorial = crearEquipo($this->tenant, 'EQ-LIBRE');
    $conHistorial = crearEquipo($this->tenant, 'EQ-TRAZA');
    EquipoHistorico::factory()->create(['equipo_id' => $conHistorial->id, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->post(route('equipos.destroy-lote'), ['ids' => [$sinHistorial->id, $conHistorial->id]])
        ->assertInertiaFlash('toast.type', 'warning');

    expect(Equipo::pluck('codigo')->all())->toBe(['EQ-TRAZA']);
});

test('eliminar por lote exige elegir al menos un registro', function () {
    $this->actingAs($this->admin)
        ->post(route('clientes.destroy-lote'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});
