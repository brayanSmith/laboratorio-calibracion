<?php

use App\Enums\TenantRole;
use App\Models\DetalleMedicionAlcance;
use App\Models\MedicionAlcance;
use App\Models\Tenant;
use App\Models\TipoEquipo;
use App\Models\UnidadMedida;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->alcance = MedicionAlcance::factory()->for($this->tenant)->create();
    $this->unidad = UnidadMedida::factory()->for($this->tenant)->create();
});

function datosDetalle(UnidadMedida $unidad, array $overrides = []): array
{
    return [
        'unidad_medida_id' => $unidad->id,
        'valor_referencia' => '25.50',
        'emp' => '0.5',
        'incertidumbre' => '0.25',
        ...$overrides,
    ];
}

test('agrega un detalle al alcance con el tenant del alcance', function () {
    $this->actingAs($this->admin)
        ->from(route('alcances-medicion.index'))
        ->post(route('alcances-medicion.detalles.store', $this->alcance), datosDetalle($this->unidad, [
            'tenant_id' => Tenant::factory()->create()->id,
        ]))
        ->assertRedirect(route('alcances-medicion.index'));

    $detalle = DetalleMedicionAlcance::firstOrFail();

    expect($detalle->medicion_alcance_id)->toBe($this->alcance->id)
        ->and($detalle->tenant_id)->toBe($this->tenant->id)
        ->and($detalle->valor_referencia)->toBe('25.50')
        ->and($detalle->emp)->toBe('0.50')
        ->and($detalle->incertidumbre)->toBe('0.25');
});

test('valida los campos del detalle', function () {
    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.detalles.store', $this->alcance), [])
        ->assertSessionHasErrors(['unidad_medida_id', 'valor_referencia', 'emp', 'incertidumbre']);

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.detalles.store', $this->alcance), datosDetalle($this->unidad, [
            'valor_referencia' => 'abc',
            'emp' => '-1',
            'incertidumbre' => '-0.1',
        ]))
        ->assertSessionHasErrors(['valor_referencia', 'emp', 'incertidumbre']);

    expect(DetalleMedicionAlcance::count())->toBe(0);
});

test('rechaza una unidad de medida de otro tenant', function () {
    $ajena = UnidadMedida::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.detalles.store', $this->alcance), datosDetalle($ajena))
        ->assertSessionHasErrors('unidad_medida_id');
});

test('un tecnico no puede agregar detalles', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)
        ->post(route('alcances-medicion.detalles.store', $this->alcance), datosDetalle($this->unidad))
        ->assertForbidden();
});

test('no permite agregar detalles a un alcance de otro tenant', function () {
    $ajeno = MedicionAlcance::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.detalles.store', $ajeno), datosDetalle($this->unidad))
        ->assertForbidden();
});

test('actualiza un detalle', function () {
    $detalle = DetalleMedicionAlcance::factory()->for($this->tenant)->create([
        'medicion_alcance_id' => $this->alcance->id,
        'unidad_medida_id' => $this->unidad->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('detalles-alcance.update', $detalle), datosDetalle($this->unidad, ['valor_referencia' => '99']))
        ->assertSessionHasNoErrors();

    expect($detalle->fresh()->valor_referencia)->toBe('99.00');
});

test('no permite actualizar ni eliminar detalles de otro tenant', function () {
    $ajeno = DetalleMedicionAlcance::factory()->create(['valor_referencia' => 10]);

    $this->actingAs($this->admin)
        ->put(route('detalles-alcance.update', $ajeno), datosDetalle($this->unidad))
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('detalles-alcance.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->valor_referencia)->toBe('10.00');
});

test('elimina un detalle', function () {
    $detalle = DetalleMedicionAlcance::factory()->for($this->tenant)->create(['medicion_alcance_id' => $this->alcance->id]);

    $this->actingAs($this->admin)
        ->delete(route('detalles-alcance.destroy', $detalle))
        ->assertRedirect();

    expect(DetalleMedicionAlcance::find($detalle->id))->toBeNull()
        ->and(DetalleMedicionAlcance::withTrashed()->find($detalle->id))->not->toBeNull();
});

test('no elimina un detalle que ya se uso en calibraciones', function () {
    $detalle = DetalleMedicionAlcance::factory()->for($this->tenant)->create(['medicion_alcance_id' => $this->alcance->id]);
    registrarMedicionDeCalibracionSobre($detalle);

    $this->actingAs($this->admin)->delete(route('detalles-alcance.destroy', $detalle))->assertRedirect();

    expect(DetalleMedicionAlcance::find($detalle->id))->not->toBeNull();
});

test('con precision en porcentaje el detalle pide emp_porcentaje en lugar de emp', function () {
    $tipoEquipo = TipoEquipo::factory()->for($this->tenant)->create();
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create([
        'tipo_equipo_id' => $tipoEquipo->id,
        'modelo' => 'M-1',
        'alcance_indicacion' => '0 - 100 bar',
    ]);
    registrarAlcanceIndicacion($this->tenant, '0 - 100 bar', $tipoEquipo, '5%');

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.detalles.store', $alcance), datosDetalle($this->unidad))
        ->assertSessionHasErrors('emp_porcentaje');

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.detalles.store', $alcance), datosDetalle($this->unidad, [
            'emp' => null,
            'emp_porcentaje' => '101',
        ]))
        ->assertSessionHasErrors('emp_porcentaje');

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.detalles.store', $alcance), datosDetalle($this->unidad, [
            'emp' => null,
            'emp_porcentaje' => '5',
        ]))
        ->assertSessionHasNoErrors();

    expect(DetalleMedicionAlcance::firstOrFail())
        ->emp->toBeNull()
        ->emp_porcentaje->toBe('5.00');
});
