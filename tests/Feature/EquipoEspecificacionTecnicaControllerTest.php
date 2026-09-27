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
    return array_merge([
        'tipo_magnitud_id' => TipoMagnitud::factory()->for($tenant)->create()->id,
        'unidad_medida_id' => UnidadMedida::factory()->for($tenant)->create()->id,
        'alcance_indicacion' => 100,
        'precision' => 0.1,
        'resolucion' => 0.01,
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
        ->and((float) $especificacion->alcance_indicacion)->toBe(100.0)
        ->and($especificacion->tenant_id)->toBe($this->tenant->id);
});

test('guardar de nuevo actualiza la especificacion existente en lugar de duplicarla', function () {
    $this->actingAs($this->admin)->post(
        route('equipos.especificacion-tecnica.store', $this->equipo),
        datosEspecificacionTecnica($this->tenant),
    );

    $this->actingAs($this->admin)->post(
        route('equipos.especificacion-tecnica.store', $this->equipo),
        datosEspecificacionTecnica($this->tenant, ['alcance_indicacion' => 250]),
    );

    expect(EquipoEspecificacionTecnica::where('equipo_id', $this->equipo->id)->count())->toBe(1)
        ->and((float) $this->equipo->equipoEspecificacionTecnica->fresh()->alcance_indicacion)->toBe(250.0);
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
