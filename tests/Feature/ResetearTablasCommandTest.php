<?php

use App\Models\Tenant;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Inventario\Models\EntradaItem;
use App\Modules\Inventario\Models\Item;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

test('vacia la tabla con sus filas eliminadas y sus tablas dependientes, y el id vuelve a empezar en 1', function () {
    [$item, $eliminado] = Item::factory()->for($this->tenant)->count(2)->create();
    $eliminado->delete();
    EntradaItem::factory()->create(['item_id' => $item->id, 'tenant_id' => $this->tenant->id]);
    $fabricante = Fabricante::factory()->for($this->tenant)->create();

    $this->artisan('tablas:resetear', ['tablas' => ['items'], '--force' => true])->assertSuccessful();

    expect(Item::withTrashed()->count())->toBe(0)
        ->and(EntradaItem::withTrashed()->count())->toBe(0)
        ->and(Fabricante::find($fabricante->id))->not->toBeNull()
        ->and(Item::factory()->for($this->tenant)->create()->id)->toBe(1);
});

test('en las tablas con llave on delete set null conserva las filas y deja la columna en null', function () {
    $bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    $ingreso = Ingreso::factory()->create(['bahia_id' => $bahia->id]);
    $programacion = crearEquipo($this->tenant)->equipoProgramaciones()->create([
        'tipo_servicio' => 'MANTENIMIENTO',
        'ingreso_id' => $ingreso->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->artisan('tablas:resetear', ['tablas' => ['ingresos'], '--force' => true])->assertSuccessful();

    expect(Ingreso::withTrashed()->count())->toBe(0)
        ->and(EquipoProgramacion::find($programacion->id)?->ingreso_id)->toBeNull()
        ->and(EquipoProgramacion::find($programacion->id))->not->toBeNull();
});

test('borra las filas polimorficas de la tabla vaciada y conserva las de otras', function () {
    foreach ([Mantenimiento::class, Calibracion::class] as $clase) {
        DB::table('version_documentos')->insert([
            'documentable_type' => $clase,
            'documentable_id' => 99,
            'numero' => 0,
            'codigo' => 'DOC-1',
            'ruta' => 'documentos/doc.pdf',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    $this->artisan('tablas:resetear', ['tablas' => ['mantenimientos'], '--force' => true])->assertSuccessful();

    expect(DB::table('version_documentos')->pluck('documentable_type')->all())->toBe([Calibracion::class]);
});

test('rechaza tablas que no existen o protegidas y no borra nada si se cancela', function () {
    $item = Item::factory()->for($this->tenant)->create();

    $this->artisan('tablas:resetear', ['tablas' => ['no_existe']])->assertFailed();
    $this->artisan('tablas:resetear', ['tablas' => ['migrations']])->assertFailed();
    $this->artisan('tablas:resetear', ['tablas' => ['items']])
        ->expectsConfirmation('Se borrarán DEFINITIVAMENTE todas las filas de las 3 tabla(s) y sus ids volverán a empezar en 1. Esto no se puede deshacer. ¿Continuar?', 'no')
        ->assertSuccessful();

    expect(Item::find($item->id))->not->toBeNull();
});
