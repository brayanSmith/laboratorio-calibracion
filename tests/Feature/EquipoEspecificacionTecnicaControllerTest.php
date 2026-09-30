<?php

use App\Enums\TenantRole;
use App\Models\EquipoEspecificacionTecnica;
use App\Models\Tenant;
use App\Models\TipoMagnitud;
use App\Models\UnidadMedida;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->equipo = crearEquipoDeTipo(crearTipoEquipo($this->tenant));
});

function datosEspecificacionTecnica(Tenant $tenant, array $overrides = []): array
{
    $unidadMedida = UnidadMedida::factory()->for($tenant)->create();

    return array_merge([
        'tipo_magnitud_id' => TipoMagnitud::factory()->for($tenant)->create()->id,
        'unidad_medida_id' => $unidadMedida->id,
        'alcance_indicacion' => '100'.$unidadMedida->simbolo,
        'precision' => '±0.1',
        'resolucion' => '0.01'.$unidadMedida->simbolo,
    ], $overrides);
}

test('crea la especificacion tecnica del equipo', function () {
    $datos = datosEspecificacionTecnica($this->tenant);

    $this->actingAs($this->admin)
        ->post(route('equipos.especificacion-tecnica.store', $this->equipo), $datos)
        ->assertRedirect();

    $especificacion = EquipoEspecificacionTecnica::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($especificacion->tipo_magnitud_id)->toBe($datos['tipo_magnitud_id'])
        ->and($especificacion->unidad_medida_id)->toBe($datos['unidad_medida_id'])
        ->and($especificacion->alcance_indicacion)->toBe($datos['alcance_indicacion'])
        ->and($especificacion->precision)->toBe('±0.1')
        ->and($especificacion->resolucion)->toBe($datos['resolucion'])
        ->and($especificacion->tenant_id)->toBe($this->tenant->id);
});

test('rechaza una resolucion que no termina con el simbolo de la unidad de medida elegida', function () {
    $this->actingAs($this->admin)
        ->post(
            route('equipos.especificacion-tecnica.store', $this->equipo),
            datosEspecificacionTecnica($this->tenant, ['resolucion' => '0.01']),
        )
        ->assertSessionHasErrors('resolucion');
});

test('rechaza un alcance de indicacion que no termina con el simbolo de la unidad de medida elegida', function () {
    $this->actingAs($this->admin)
        ->post(
            route('equipos.especificacion-tecnica.store', $this->equipo),
            datosEspecificacionTecnica($this->tenant, ['alcance_indicacion' => '100']),
        )
        ->assertSessionHasErrors('alcance_indicacion');
});

test('acepta un alcance de indicacion en texto libre que termina con el simbolo de la unidad de medida', function () {
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(
            route('equipos.especificacion-tecnica.store', $this->equipo),
            datosEspecificacionTecnica($this->tenant, [
                'unidad_medida_id' => $unidadMedida->id,
                'alcance_indicacion' => '0 a 100'.$unidadMedida->simbolo,
                'resolucion' => '0.01'.$unidadMedida->simbolo,
            ]),
        )
        ->assertSessionHasNoErrors();

    $especificacion = EquipoEspecificacionTecnica::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($especificacion->alcance_indicacion)->toBe('0 a 100'.$unidadMedida->simbolo);
});

test('acepta la precision como porcentaje concatenado al final', function () {
    $this->actingAs($this->admin)
        ->post(
            route('equipos.especificacion-tecnica.store', $this->equipo),
            datosEspecificacionTecnica($this->tenant, ['precision' => '0.5%']),
        )
        ->assertSessionHasNoErrors();

    $especificacion = EquipoEspecificacionTecnica::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($especificacion->precision)->toBe('0.5%');
});

test('rechaza una precision con un formato invalido', function () {
    $this->actingAs($this->admin)
        ->post(
            route('equipos.especificacion-tecnica.store', $this->equipo),
            datosEspecificacionTecnica($this->tenant, ['precision' => '0.5']),
        )
        ->assertSessionHasErrors('precision');
});

test('guardar de nuevo actualiza la especificacion existente en lugar de duplicarla', function () {
    $datos = datosEspecificacionTecnica($this->tenant);

    $this->actingAs($this->admin)->post(
        route('equipos.especificacion-tecnica.store', $this->equipo),
        $datos,
    );

    $simbolo = UnidadMedida::find($datos['unidad_medida_id'])->simbolo;

    $this->actingAs($this->admin)->post(
        route('equipos.especificacion-tecnica.store', $this->equipo),
        array_merge($datos, ['alcance_indicacion' => '250'.$simbolo]),
    );

    expect(EquipoEspecificacionTecnica::where('equipo_id', $this->equipo->id)->count())->toBe(1)
        ->and($this->equipo->equipoEspecificacionTecnica->fresh()->alcance_indicacion)->toBe('250'.$simbolo);
});

test('rechaza la especificacion tecnica sin los campos requeridos', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.especificacion-tecnica.store', $this->equipo), [])
        ->assertSessionHasErrors([
            'tipo_magnitud_id', 'unidad_medida_id', 'alcance_indicacion', 'precision', 'resolucion',
        ]);
});

test('rechaza un tipo de magnitud o unidad de medida de otro tenant', function () {
    $ajeno = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('equipos.especificacion-tecnica.store', $this->equipo), datosEspecificacionTecnica($ajeno))
        ->assertSessionHasErrors(['tipo_magnitud_id', 'unidad_medida_id']);
});

test('un usuario sin permiso de editar equipos no puede guardar la especificacion tecnica', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();

    $this->actingAs($recepcion)
        ->post(route('equipos.especificacion-tecnica.store', $this->equipo), datosEspecificacionTecnica($this->tenant))
        ->assertForbidden();
});

test('no permite guardar la especificacion tecnica de un equipo de otro tenant', function () {
    $ajeno = crearEquipoDeTipo(crearTipoEquipo(Tenant::factory()->create()), 'EQ-9999');

    $this->actingAs($this->admin)
        ->post(route('equipos.especificacion-tecnica.store', $ajeno), datosEspecificacionTecnica($this->tenant))
        ->assertForbidden();
});
