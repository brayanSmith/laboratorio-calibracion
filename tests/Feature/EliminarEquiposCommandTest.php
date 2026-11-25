<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\AlcancesMedicion\Models\DetalleMedicionAlcance;
use App\Modules\AlcancesMedicion\Models\MedicionAlcance;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\DocumentoEquipo;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

function documentoDelEquipo(Equipo $equipo, string $archivo): DocumentoEquipo
{
    Storage::disk('public')->put($archivo, 'pdf');

    return DocumentoEquipo::create([
        'equipo_id' => $equipo->id,
        'nombre' => 'Manual',
        'archivo' => $archivo,
        'tenant_id' => $equipo->tenant_id,
    ]);
}

test('elimina definitivamente el equipo con su traza y sus archivos, sin tocar los demas equipos', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-BORRAR');
    $orden = crearServiciosDelEquipo($programacion, $this->admin);
    Mantenimiento::where('orden_trabajo_id', $orden->id)->update(['pdf_informe' => 'mantenimientos/informe.pdf']);
    Storage::disk('public')->put('mantenimientos/informe.pdf', 'pdf');
    documentoDelEquipo($programacion->equipo, 'documentos/propio.pdf');
    documentoDelEquipo($programacion->equipo, 'documentos/compartido.pdf');

    $otro = crearEquipoRecibido($this->bahia, 'EQ-QUEDA');
    crearServiciosDelEquipo($otro, $this->admin);
    documentoDelEquipo($otro->equipo, 'documentos/compartido.pdf');

    $this->artisan('equipos:eliminar', ['codigos' => ['EQ-BORRAR'], '--tenant' => $this->tenant->id, '--force' => true])
        ->assertSuccessful();

    expect(Equipo::withTrashed()->where('codigo', 'EQ-BORRAR')->exists())->toBeFalse()
        ->and(EquipoProgramacion::withTrashed()->find($programacion->id))->toBeNull()
        ->and(Ingreso::withTrashed()->find($programacion->ingreso_id))->toBeNull()
        ->and(OrdenTrabajo::withTrashed()->find($orden->id))->toBeNull()
        ->and(Mantenimiento::withTrashed()->where('orden_trabajo_id', $orden->id)->exists())->toBeFalse()
        ->and(Calibracion::withTrashed()->where('orden_trabajo_id', $orden->id)->exists())->toBeFalse()
        ->and(Despacho::withTrashed()->where('orden_trabajo_id', $orden->id)->exists())->toBeFalse()
        ->and(ServicioTercero::withTrashed()->where('orden_trabajo_id', $orden->id)->exists())->toBeFalse()
        ->and(Equipo::where('codigo', 'EQ-QUEDA')->exists())->toBeTrue()
        ->and(OrdenTrabajo::count())->toBe(1);

    Storage::disk('public')->assertMissing(['mantenimientos/informe.pdf', 'documentos/propio.pdf']);
    Storage::disk('public')->assertExists('documentos/compartido.pdf');
});

test('elimina los alcances de medicion de los modelos que se quedan sin equipos', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia, 'EQ-BORRAR');
    $alcanceHuerfano = MedicionAlcance::factory()->create(['tipo_equipo_id' => $equipo->tipo_equipo_id, 'modelo' => $equipo->modelo, 'tenant_id' => $this->tenant->id]);
    DetalleMedicionAlcance::factory()->create(['medicion_alcance_id' => $alcanceHuerfano->id, 'tenant_id' => $this->tenant->id]);

    $companero = crearEquipoParaBusqueda($this->bahia, 'EQ-QUEDA');
    $companero->update(['modelo' => 'Modelo compartido']);
    $otro = crearEquipoParaBusqueda($this->bahia, 'EQ-BORRAR-2');
    $otro->update(['modelo' => 'Modelo compartido', 'tipo_equipo_id' => $companero->tipo_equipo_id]);
    $alcanceEnUso = MedicionAlcance::factory()->create(['tipo_equipo_id' => $companero->tipo_equipo_id, 'modelo' => 'Modelo compartido', 'tenant_id' => $this->tenant->id]);

    $this->artisan('equipos:eliminar', ['codigos' => ['EQ-BORRAR', 'EQ-BORRAR-2'], '--tenant' => $this->tenant->id, '--force' => true])
        ->assertSuccessful();

    expect(MedicionAlcance::withTrashed()->find($alcanceHuerfano->id))->toBeNull()
        ->and(DetalleMedicionAlcance::withTrashed()->where('medicion_alcance_id', $alcanceHuerfano->id)->exists())->toBeFalse()
        ->and(MedicionAlcance::find($alcanceEnUso->id))->not->toBeNull();
});

test('conserva el ingreso que tambien trajo otros equipos', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-BORRAR');
    $companero = crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-COMPANERO'), ['ingreso_id' => $programacion->ingreso_id]);

    $this->artisan('equipos:eliminar', ['codigos' => ['EQ-BORRAR'], '--tenant' => $this->tenant->id, '--force' => true])
        ->assertSuccessful();

    expect(Ingreso::find($programacion->ingreso_id))->not->toBeNull()
        ->and($companero->refresh()->ingreso_id)->toBe($programacion->ingreso_id);
});

test('elimina los equipos que ya estaban eliminados y reajusta el autoincremental', function () {
    $activo = crearEquipoParaBusqueda($this->bahia, 'EQ-ACTIVO');
    $eliminado = crearEquipoParaBusqueda($this->bahia, 'EQ-ELIMINADO');
    crearProgramacion($eliminado);
    $eliminado->delete();

    $this->artisan('equipos:eliminar', ['--eliminados' => true, '--force' => true])->assertSuccessful();

    expect(Equipo::withTrashed()->pluck('codigo')->all())->toBe(['EQ-ACTIVO'])
        ->and(crearEquipoParaBusqueda($this->bahia, 'EQ-NUEVO')->id)->toBe($activo->id + 1);
});

test('pide el tenant al eliminar por codigo y no borra nada si se cancela', function () {
    crearEquipoParaBusqueda($this->bahia, 'EQ-1');

    $this->artisan('equipos:eliminar', ['codigos' => ['EQ-1']])->assertFailed();
    $this->artisan('equipos:eliminar', ['codigos' => ['EQ-1'], '--tenant' => $this->tenant->id])
        ->expectsConfirmation('Se eliminarán DEFINITIVAMENTE 1 equipo(s) con toda su traza y sus archivos. Esto no se puede deshacer. ¿Continuar?', 'no')
        ->assertSuccessful();

    expect(Equipo::where('codigo', 'EQ-1')->exists())->toBeTrue();
});

test('el codigo de la siguiente orden de trabajo no repite uno usado tras un borrado definitivo', function () {
    foreach (['OT-0001', 'OT-0002', 'OT-0003'] as $codigo) {
        OrdenTrabajo::create([
            'codigo' => $codigo,
            'equipo_programacion_id' => crearProgramacion(crearEquipoParaBusqueda($this->bahia, "EQ-{$codigo}"))->id,
            'fecha_programada_orden_trabajo' => now()->toDateString(),
            'estado' => 'EN_BAHIA',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    OrdenTrabajo::where('codigo', 'OT-0002')->forceDelete();

    expect(OrdenTrabajo::siguienteNumero($this->tenant->id))->toBe(4);
});
