<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\ComentarioMantenimiento;
use App\Models\EntradaItem;
use App\Models\GaleriaMantenimiento;
use App\Models\Item;
use App\Models\ItemMantenimiento;
use App\Models\Mantenimiento;
use App\Models\MantenimientoCheckList;
use App\Models\MantenimientoDefectoIdentificado;
use App\Models\Tenant;
use App\Models\TipoEquipoCheckList;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/** Payload mínimo requerido por MantenimientoController::update() para los campos
 * propios del mantenimiento, sin tocarlos. refresh() porque estado_mantenimiento se
 * deja al valor por defecto de la base de datos en crearMantenimiento(), que no
 * queda reflejado en el modelo en memoria hasta refrescarlo. */
function datosBaseGestion(Mantenimiento $mantenimiento): array
{
    $mantenimiento->refresh();

    return [
        'fecha_mantenimiento' => $mantenimiento->fecha_mantenimiento->toDateString(),
        'estado_mantenimiento' => $mantenimiento->estado_mantenimiento,
        'tecnico_id' => $mantenimiento->tecnico_id,
    ];
}

test('la lista de mantenimientos incluye el tiempo_servicio activo, checklist, defectos, items, comentarios y galeria', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $tipoEquipoId = $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->tipo_equipo_id;
    $checkListItem = TipoEquipoCheckList::create(['tipo_equipo_id' => $tipoEquipoId, 'nombre' => 'Revisar cableado', 'tenant_id' => $this->tenant->id]);
    MantenimientoCheckList::create(['mantenimiento_id' => $mantenimiento->id, 'tipo_equipo_check_list_id' => $checkListItem->id, 'tenant_id' => $this->tenant->id]);

    MantenimientoDefectoIdentificado::create(['mantenimiento_id' => $mantenimiento->id, 'defecto_identificado' => 'Fuga de aceite', 'nivel_riesgo' => 'ALTO', 'tenant_id' => $this->tenant->id]);

    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    ItemMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'item_id' => $item->id, 'cantidad' => 2, 'tenant_id' => $this->tenant->id]);

    ComentarioMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'comentario' => 'Todo en orden', 'user_id' => $this->admin->id, 'tenant_id' => $this->tenant->id]);

    GaleriaMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'imagen' => 'mantenimientos/foto.jpg', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.iniciar', $mantenimiento));

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('mantenimientos.0.id', $mantenimiento->id)
            ->whereType('mantenimientos.0.tiempo_servicio_inicio', 'string')
            ->has('mantenimientos.0.checklist', 1)
            ->where('mantenimientos.0.checklist.0.nombre', 'Revisar cableado')
            ->has('mantenimientos.0.defectos', 1)
            ->where('mantenimientos.0.defectos.0.defecto_identificado', 'Fuga de aceite')
            ->has('mantenimientos.0.items_usados', 1)
            ->where('mantenimientos.0.items_usados.0.item_codigo', $item->codigo)
            ->has('mantenimientos.0.comentarios', 1)
            ->where('mantenimientos.0.comentarios.0.autor', $this->admin->name)
            ->has('mantenimientos.0.galeria', 1));
});

test('un mantenimiento sin tiempo_servicio activo reporta tiempo_servicio_inicio nulo', function () {
    crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('mantenimientos.0.tiempo_servicio_inicio', null));
});

test('guarda el checklist y varios defectos, items usados, comentarios y fotos nuevos en una sola solicitud', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $tipoEquipoId = $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->tipo_equipo_id;
    $checkListItem = TipoEquipoCheckList::create(['tipo_equipo_id' => $tipoEquipoId, 'nombre' => 'Revisar cableado', 'tenant_id' => $this->tenant->id]);
    $itemChecklist = MantenimientoCheckList::create(['mantenimiento_id' => $mantenimiento->id, 'tipo_equipo_check_list_id' => $checkListItem->id, 'tenant_id' => $this->tenant->id]);
    $itemA = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $itemB = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    EntradaItem::factory()->recibido()->create(['item_id' => $itemA->id, 'cantidad' => 10]);
    EntradaItem::factory()->recibido()->create(['item_id' => $itemB->id, 'cantidad' => 10]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'checklist' => [
                ['id' => $itemChecklist->id, 'cumple' => '1', 'observacion' => 'Cableado en buen estado'],
            ],
            'nuevos_defectos' => [
                ['defecto_identificado' => 'Fuga de aceite', 'nivel_riesgo' => 'ALTO', 'accion_correctiva' => 'Cambiar empaque'],
                ['defecto_identificado' => 'Ruido extraño', 'nivel_riesgo' => 'MEDIO'],
            ],
            'nuevos_items_usados' => [
                ['item_id' => $itemA->id, 'cantidad' => 2],
                ['item_id' => $itemB->id, 'cantidad' => 1.5],
            ],
            'nuevos_comentarios' => ['Todo en orden', 'Se revisó dos veces'],
            'nuevas_fotos' => [
                ['archivo' => UploadedFile::fake()->image('foto1.jpg'), 'descripcion' => 'Antes'],
                ['archivo' => UploadedFile::fake()->image('foto2.jpg'), 'descripcion' => 'Después'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimientos.index'));

    expect($itemChecklist->refresh())
        ->cumple->toBeTrue()
        ->observacion->toBe('Cableado en buen estado');

    expect(MantenimientoDefectoIdentificado::where('mantenimiento_id', $mantenimiento->id)->pluck('defecto_identificado')->all())
        ->toEqualCanonicalizing(['Fuga de aceite', 'Ruido extraño']);

    expect(ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(2);

    expect(ComentarioMantenimiento::where('mantenimiento_id', $mantenimiento->id)->pluck('comentario')->all())
        ->toEqualCanonicalizing(['Todo en orden', 'Se revisó dos veces']);
    expect(ComentarioMantenimiento::where('mantenimiento_id', $mantenimiento->id)->pluck('user_id')->unique()->all())
        ->toBe([$this->admin->id]);

    $fotos = GaleriaMantenimiento::where('mantenimiento_id', $mantenimiento->id)->get();
    expect($fotos)->toHaveCount(2);
    expect($fotos->pluck('descripcion')->all())->toEqualCanonicalizing(['Antes', 'Después']);
    $fotos->each(fn (GaleriaMantenimiento $foto) => Storage::disk('public')->assertExists($foto->imagen));
});

test('no crea defectos, items usados, comentarios ni fotos si las filas nuevas se dejan vacias', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_defectos' => [['defecto_identificado' => '', 'nivel_riesgo' => 'BAJO']],
            'nuevos_items_usados' => [['item_id' => '', 'cantidad' => '']],
            'nuevos_comentarios' => [''],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimientos.index'));

    expect(MantenimientoDefectoIdentificado::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0)
        ->and(ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0)
        ->and(ComentarioMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0)
        ->and(GaleriaMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0);
});

test('no permite guardar un item del checklist que no pertenece al mantenimiento', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $ajeno = crearMantenimiento(Bahia::factory()->create(), codigo: 'EQ-AJENO');
    $tipoEquipoId = $ajeno->ordenTrabajo->equipoProgramacion->equipo->tipo_equipo_id;
    $checkListItem = TipoEquipoCheckList::create(['tipo_equipo_id' => $tipoEquipoId, 'nombre' => 'Revisar cableado', 'tenant_id' => $ajeno->tenant_id]);
    $itemAjeno = MantenimientoCheckList::create(['mantenimiento_id' => $ajeno->id, 'tipo_equipo_check_list_id' => $checkListItem->id, 'tenant_id' => $ajeno->tenant_id]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'checklist' => [['id' => $itemAjeno->id, 'cumple' => '1']],
        ])
        ->assertSessionHasErrors('checklist.0.id');
});

test('valida que cada nuevo item usado pertenezca al tenant', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $itemAjeno = Item::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_items_usados' => [
                ['item_id' => $item->id, 'cantidad' => 1],
                ['item_id' => $itemAjeno->id, 'cantidad' => 1],
            ],
        ])
        ->assertSessionHasErrors('nuevos_items_usados.1.item_id');

    expect(ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0);
});

test('la lista de mantenimientos incluye el stock de cada item disponible', function () {
    crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    EntradaItem::factory()->recibido()->create(['item_id' => $item->id, 'cantidad' => 8]);
    EntradaItem::factory()->create(['item_id' => $item->id, 'cantidad' => 50]);

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.id', $item->id)
            ->where('items.0.stock', 8));
});

test('no permite usar mas cantidad de un item que su stock', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    EntradaItem::factory()->recibido()->create(['item_id' => $item->id, 'cantidad' => 5]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_items_usados' => [['item_id' => $item->id, 'cantidad' => 5.5]],
        ])
        ->assertSessionHasErrors('nuevos_items_usados.0.cantidad');

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_items_usados' => [['item_id' => $item->id, 'cantidad' => 5]],
        ])
        ->assertSessionHasNoErrors();

    expect(ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(1);
});

test('suma las filas que usan el mismo item al validar el stock', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    EntradaItem::factory()->recibido()->create(['item_id' => $item->id, 'cantidad' => 5]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_items_usados' => [
                ['item_id' => $item->id, 'cantidad' => 3],
                ['item_id' => $item->id, 'cantidad' => 3],
            ],
        ])
        ->assertSessionHasErrors(['nuevos_items_usados.0.cantidad', 'nuevos_items_usados.1.cantidad']);

    expect(ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0);
});

test('un item sin stock se registra como sin stock y no cuenta como salida', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_items_usados' => [['item_id' => $item->id, 'cantidad' => 4]],
        ])
        ->assertSessionHasNoErrors();

    $usado = ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->sole();

    expect($usado->con_stock)->toBeFalse()
        ->and($usado->cantidad)->toBe('4.00');

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.stock', 0)
            ->where('mantenimientos.0.items_usados.0.con_stock', false));

    $this->actingAs($this->admin)
        ->getJson(route('items.movimientos', $item))
        ->assertJsonCount(0, 'salidas');
});

test('un item con stock queda registrado como con stock y descuenta del inventario', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    EntradaItem::factory()->recibido()->create(['item_id' => $item->id, 'cantidad' => 10]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_items_usados' => [['item_id' => $item->id, 'cantidad' => 4]],
        ])
        ->assertSessionHasNoErrors();

    expect(ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->sole()->con_stock)->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page->where('items.0.stock', 6));
});

test('elimina un defecto identificado', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $defecto = MantenimientoDefectoIdentificado::create(['mantenimiento_id' => $mantenimiento->id, 'defecto_identificado' => 'Fuga de aceite', 'nivel_riesgo' => 'ALTO', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->delete(route('defectos.destroy', $defecto))
        ->assertRedirect();

    expect(MantenimientoDefectoIdentificado::find($defecto->id))->toBeNull();
});

test('elimina un item usado', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $itemMantenimiento = ItemMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'item_id' => $item->id, 'cantidad' => 2, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->delete(route('item-mantenimientos.destroy', $itemMantenimiento))
        ->assertRedirect();

    expect(ItemMantenimiento::find($itemMantenimiento->id))->toBeNull();
});

test('elimina un comentario', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $comentario = ComentarioMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'comentario' => 'Todo en orden', 'user_id' => $this->admin->id, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->delete(route('comentarios.destroy', $comentario))
        ->assertRedirect();

    expect(ComentarioMantenimiento::find($comentario->id))->toBeNull();
});

test('elimina una foto de la galeria', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $imagen = UploadedFile::fake()->image('foto.jpg')->store("mantenimientos/{$this->tenant->id}/{$mantenimiento->id}", 'public');
    $foto = GaleriaMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'imagen' => $imagen, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->delete(route('galeria.destroy', $foto))
        ->assertRedirect();

    expect(GaleriaMantenimiento::find($foto->id))->toBeNull();
    Storage::disk('public')->assertMissing($foto->imagen);
});

test('un usuario sin permiso de editar mantenimientos no puede guardar su gestion ni eliminar sus registros', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($recepcion)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_defectos' => [['defecto_identificado' => 'X', 'nivel_riesgo' => 'BAJO']],
            'nuevos_items_usados' => [['item_id' => $item->id, 'cantidad' => 1]],
            'nuevos_comentarios' => ['X'],
            'nuevas_fotos' => [['archivo' => UploadedFile::fake()->image('foto.jpg')]],
        ])
        ->assertForbidden();

    $defecto = MantenimientoDefectoIdentificado::create(['mantenimiento_id' => $mantenimiento->id, 'defecto_identificado' => 'X', 'nivel_riesgo' => 'BAJO', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($recepcion)
        ->delete(route('defectos.destroy', $defecto))
        ->assertForbidden();

    expect(MantenimientoDefectoIdentificado::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(1)
        ->and(ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0)
        ->and(ComentarioMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0)
        ->and(GaleriaMantenimiento::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0);
});

test('actualizar el stock convierte en usados los items sin stock que ya tienen stock suficiente', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $mantenimiento->update(['estado_mantenimiento' => 'FALTA_REPUESTOS']);
    $conStock = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $sinStock = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $usadoA = ItemMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'item_id' => $conStock->id, 'cantidad' => 3, 'con_stock' => false, 'tenant_id' => $this->tenant->id]);
    $usadoB = ItemMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'item_id' => $sinStock->id, 'cantidad' => 3, 'con_stock' => false, 'tenant_id' => $this->tenant->id]);
    EntradaItem::factory()->recibido()->create(['item_id' => $conStock->id, 'cantidad' => 5]);
    EntradaItem::factory()->create(['item_id' => $sinStock->id, 'cantidad' => 50]);

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.actualizar-stock', $mantenimiento))
        ->assertRedirect();

    expect($usadoA->fresh()->con_stock)->toBeTrue()
        ->and($usadoB->fresh()->con_stock)->toBeFalse()
        ->and($mantenimiento->fresh()->estado_mantenimiento)->toBe('FALTA_REPUESTOS');

    EntradaItem::query()->where('item_id', $sinStock->id)->update(['recibido' => true, 'fecha_recibido' => now()]);

    $this->actingAs($this->admin)->post(route('mantenimientos.actualizar-stock', $mantenimiento));

    expect($usadoB->fresh()->con_stock)->toBeTrue()
        ->and($mantenimiento->fresh()->estado_mantenimiento)->toBe('EN_PROCESO');
});

test('no se puede actualizar el stock de un mantenimiento de otro tenant', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $otroAdmin = User::factory()->forTenant(Tenant::factory()->create())->create();

    $this->actingAs($otroAdmin)
        ->post(route('mantenimientos.actualizar-stock', $mantenimiento))
        ->assertForbidden();
});

test('al quitar el ultimo item sin stock el mantenimiento deja de estar en falta de repuestos', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $mantenimiento->update(['estado_mantenimiento' => 'FALTA_REPUESTOS']);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $usado = ItemMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'item_id' => $item->id, 'cantidad' => 2, 'con_stock' => false, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->delete(route('item-mantenimientos.destroy', $usado));

    expect($mantenimiento->fresh()->estado_mantenimiento)->toBe('EN_PROCESO');
});

test('quitar un item sin stock no cambia el estado si quedan otros sin stock', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $mantenimiento->update(['estado_mantenimiento' => 'FALTA_REPUESTOS']);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $usadoA = ItemMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'item_id' => $item->id, 'cantidad' => 2, 'con_stock' => false, 'tenant_id' => $this->tenant->id]);
    ItemMantenimiento::create(['mantenimiento_id' => $mantenimiento->id, 'item_id' => $item->id, 'cantidad' => 1, 'con_stock' => false, 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->delete(route('item-mantenimientos.destroy', $usadoA));

    expect($mantenimiento->fresh()->estado_mantenimiento)->toBe('FALTA_REPUESTOS');
});

test('guarda el precio unitario y el subtotal del item al usarlo y no cambian si el precio se actualiza', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'precio_unitario' => 12.5]);
    EntradaItem::factory()->recibido()->create(['item_id' => $item->id, 'cantidad' => 10, 'precio_unitario' => 12.5]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_items_usados' => [['item_id' => $item->id, 'cantidad' => 3, 'precio_unitario' => 1, 'subtotal' => 1]],
        ])
        ->assertSessionHasNoErrors();

    $item->update(['precio_unitario' => 99]);

    $usado = ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->sole();

    expect($usado->precio_unitario)->toBe('12.50')
        ->and($usado->subtotal)->toBe('37.50');

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('mantenimientos.0.items_usados.0.precio_unitario', 12.5)
            ->where('mantenimientos.0.items_usados.0.subtotal', 37.5)
            ->where('items.0.precio_unitario', 99));
});

test('un item sin precio se registra con precio y subtotal en cero', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'precio_unitario' => null]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            ...datosBaseGestion($mantenimiento),
            'nuevos_items_usados' => [['item_id' => $item->id, 'cantidad' => 2]],
        ])
        ->assertSessionHasNoErrors();

    $usado = ItemMantenimiento::where('mantenimiento_id', $mantenimiento->id)->sole();

    expect($usado->precio_unitario)->toBe('0.00')->and($usado->subtotal)->toBe('0.00');
});
