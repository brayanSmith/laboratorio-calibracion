<?php

use App\Enums\TenantRole;
use App\Models\DetalleMedicionAlcance;
use App\Models\DetalleMedicionCalibracion;
use App\Models\EquipoEspecificacionTecnica;
use App\Models\Tenant;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function registrarEspecificacionConUnidadMedida(UnidadMedida $unidadMedida): EquipoEspecificacionTecnica
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return EquipoEspecificacionTecnica::create([
        'equipo_id' => 1,
        'tipo_magnitud_id' => 1,
        'unidad_medida_id' => $unidadMedida->id,
        'alcance_indicacion' => '100'.$unidadMedida->simbolo,
        'precision' => '±0.1',
        'resolucion' => '0.01'.$unidadMedida->simbolo,
        'tenant_id' => $unidadMedida->tenant_id,
    ]);
}

function registrarMedicionAlcanceConUnidadMedida(UnidadMedida $unidadMedida): DetalleMedicionAlcance
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return DetalleMedicionAlcance::create([
        'medicion_alcance_id' => 1,
        'unidad_medida_id' => $unidadMedida->id,
        'valor_instrumento' => 1,
        'emp' => 1,
        'incertidumbre' => 1,
        'tenant_id' => $unidadMedida->tenant_id,
    ]);
}

function registrarMedicionCalibracionConUnidadMedida(UnidadMedida $unidadMedida): DetalleMedicionCalibracion
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return DetalleMedicionCalibracion::create([
        'calibracion_id' => 1,
        'detalle_medicion_alcance_id' => 1,
        'valor_referencia' => 1,
        'unidad_medida_id' => $unidadMedida->id,
        'valor_instrumento' => 1,
        'error_encontrado' => 0,
        'emp' => 1,
        'incertidumbre' => 1,
        'error_porcentaje' => 0,
        'emp_porcentaje_positivo' => 1,
        'emp_porcentaje_negativo' => 1,
        'resultado_calibracion' => 'APROBADO',
        'tenant_id' => $unidadMedida->tenant_id,
    ]);
}

test('lista solo las unidades de medida del tenant con la cantidad de usos', function () {
    $bar = UnidadMedida::factory()->for($this->tenant)->create(['nombre' => 'Bar', 'simbolo' => 'bar']);
    registrarEspecificacionConUnidadMedida($bar);
    UnidadMedida::factory()->create(['nombre' => 'Ajena', 'simbolo' => 'aj']);

    $this->actingAs($this->admin)
        ->get(route('unidades-medida.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('unidades-medida/index')
            ->has('unidadesMedida', 1)
            ->where('unidadesMedida.0.nombre', 'Bar')
            ->where('unidadesMedida.0.usos_count', 1));
});

test('un usuario sin permiso de ver unidades de medida no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('unidades-medida.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear unidades de medida', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('unidades-medida.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('unidades-medida.store'), ['nombre' => 'Nueva', 'simbolo' => 'n'])
        ->assertForbidden();

    expect(UnidadMedida::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('unidades-medida.index'))->assertRedirect(route('login'));
});

test('crea una unidad de medida asignada al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('unidades-medida.store'), [
            'nombre' => 'Bar',
            'simbolo' => 'bar',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('unidades-medida.index'));

    $unidadMedida = UnidadMedida::firstOrFail();

    expect($unidadMedida->nombre)->toBe('Bar')
        ->and($unidadMedida->simbolo)->toBe('bar')
        ->and($unidadMedida->tenant_id)->toBe($this->tenant->id);
});

test('rechaza una unidad de medida sin nombre ni simbolo', function () {
    $this->actingAs($this->admin)
        ->post(route('unidades-medida.store'), [])
        ->assertSessionHasErrors(['nombre', 'simbolo']);
});

test('el nombre y el simbolo son unicos dentro del tenant pero pueden repetirse en otro', function () {
    UnidadMedida::factory()->for($this->tenant)->create(['nombre' => 'Bar', 'simbolo' => 'bar']);

    $this->actingAs($this->admin)
        ->post(route('unidades-medida.store'), ['nombre' => 'Bar', 'simbolo' => 'otro'])
        ->assertSessionHasErrors('nombre');
    $this->actingAs($this->admin)
        ->post(route('unidades-medida.store'), ['nombre' => 'Otro', 'simbolo' => 'bar'])
        ->assertSessionHasErrors('simbolo');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('unidades-medida.store'), ['nombre' => 'Bar', 'simbolo' => 'bar'])
        ->assertSessionHasNoErrors();
});

test('actualiza el nombre y el simbolo de la unidad de medida', function () {
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('unidades-medida.update', $unidadMedida), ['nombre' => 'Pascal', 'simbolo' => 'Pa'])
        ->assertRedirect(route('unidades-medida.index'));

    expect($unidadMedida->fresh())
        ->nombre->toBe('Pascal')
        ->simbolo->toBe('Pa');
});

test('permite guardar una unidad de medida conservando su propio nombre y simbolo', function () {
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create(['nombre' => 'Bar', 'simbolo' => 'bar']);

    $this->actingAs($this->admin)
        ->put(route('unidades-medida.update', $unidadMedida), ['nombre' => 'Bar', 'simbolo' => 'bar'])
        ->assertSessionHasNoErrors();
});

test('no permite actualizar ni eliminar unidades de medida de otro tenant', function () {
    $ajena = UnidadMedida::factory()->create(['nombre' => 'Ajena', 'simbolo' => 'aj']);

    $this->actingAs($this->admin)
        ->put(route('unidades-medida.update', $ajena), ['nombre' => 'Hackeada', 'simbolo' => 'hk'])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('unidades-medida.destroy', $ajena))->assertForbidden();

    expect($ajena->fresh()->nombre)->toBe('Ajena');
});

test('elimina una unidad de medida que no esta en uso', function () {
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('unidades-medida.destroy', $unidadMedida))
        ->assertRedirect(route('unidades-medida.index'));

    expect(UnidadMedida::find($unidadMedida->id))->toBeNull()
        ->and(UnidadMedida::withTrashed()->find($unidadMedida->id))->not->toBeNull();
});

test('no elimina una unidad de medida usada en una especificacion tecnica', function () {
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();
    registrarEspecificacionConUnidadMedida($unidadMedida);

    $this->actingAs($this->admin)
        ->from(route('unidades-medida.index'))
        ->delete(route('unidades-medida.destroy', $unidadMedida))
        ->assertRedirect(route('unidades-medida.index'));

    expect(UnidadMedida::find($unidadMedida->id))->not->toBeNull();
});

test('no elimina una unidad de medida usada en un detalle de medicion de alcance', function () {
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();
    registrarMedicionAlcanceConUnidadMedida($unidadMedida);

    $this->actingAs($this->admin)
        ->from(route('unidades-medida.index'))
        ->delete(route('unidades-medida.destroy', $unidadMedida))
        ->assertRedirect(route('unidades-medida.index'));

    expect(UnidadMedida::find($unidadMedida->id))->not->toBeNull();
});

test('no elimina una unidad de medida usada en un detalle de medicion de calibracion', function () {
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();
    registrarMedicionCalibracionConUnidadMedida($unidadMedida);

    $this->actingAs($this->admin)
        ->from(route('unidades-medida.index'))
        ->delete(route('unidades-medida.destroy', $unidadMedida))
        ->assertRedirect(route('unidades-medida.index'));

    expect(UnidadMedida::find($unidadMedida->id))->not->toBeNull();
});
