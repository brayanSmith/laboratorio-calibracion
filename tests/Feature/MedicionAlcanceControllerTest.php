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
    $this->tipoEquipo = TipoEquipo::factory()->for($this->tenant)->create(['nombre' => 'Manómetro']);
    foreach (['0 - 10 bar', '0 - 10 bar', '0 - 20 bar', '0 - 100 bar'] as $alcance) {
        registrarAlcanceIndicacion($this->tenant, $alcance, $this->tipoEquipo);
    }
});

test('lista solo los alcances de medicion del tenant con sus detalles', function () {
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create([
        'tipo_equipo_id' => $this->tipoEquipo->id,
        'alcance_indicacion' => '0 - 100 bar',
    ]);
    DetalleMedicionAlcance::factory()->for($this->tenant)->create([
        'medicion_alcance_id' => $alcance->id,
        'valor_instrumento' => 50,
    ]);
    MedicionAlcance::factory()->create(['alcance_indicacion' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->get(route('alcances-medicion.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('alcances-medicion/index')
            ->has('alcancesMedicion', 1)
            ->where('alcancesMedicion.0.alcance_indicacion', '0 - 100 bar')
            ->where('alcancesMedicion.0.tipo_equipo', 'Manómetro')
            ->has('alcancesMedicion.0.detalles', 1)
            ->where('alcancesMedicion.0.detalles.0.valor_instrumento', '50.00')
            ->has('tiposEquipo', 1)
            ->where('alcancesIndicacion', [$this->tipoEquipo->id => ['0 - 10 bar', '0 - 100 bar', '0 - 20 bar']])
            ->has('unidadesMedida', 1));
});

test('un usuario sin permiso de ver alcances no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('alcances-medicion.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear alcances de medicion', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('alcances-medicion.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('alcances-medicion.store'), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 10 bar',
        ])
        ->assertForbidden();

    expect(MedicionAlcance::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('alcances-medicion.index'))->assertRedirect(route('login'));
});

test('crea un alcance de medicion asignado al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.store'), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 10 bar',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('alcances-medicion.index'));

    $alcance = MedicionAlcance::firstOrFail();

    expect($alcance->alcance_indicacion)->toBe('0 - 10 bar')
        ->and($alcance->tipo_equipo_id)->toBe($this->tipoEquipo->id)
        ->and($alcance->tenant_id)->toBe($this->tenant->id);
});

test('valida los campos requeridos y que el tipo de equipo sea del tenant', function () {
    $ajeno = TipoEquipo::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.store'), [])
        ->assertSessionHasErrors(['tipo_equipo_id', 'alcance_indicacion']);

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.store'), [
            'tipo_equipo_id' => $ajeno->id,
            'alcance_indicacion' => '0 - 10 bar',
        ])
        ->assertSessionHasErrors('tipo_equipo_id');
});

test('el alcance es unico por tipo de equipo dentro del tenant', function () {
    MedicionAlcance::factory()->for($this->tenant)->create([
        'tipo_equipo_id' => $this->tipoEquipo->id,
        'alcance_indicacion' => '0 - 10 bar',
    ]);
    $otroTipo = TipoEquipo::factory()->for($this->tenant)->create();
    registrarAlcanceIndicacion($this->tenant, '0 - 10 bar', $otroTipo);

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.store'), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 10 bar',
        ])
        ->assertSessionHasErrors('alcance_indicacion');

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.store'), [
            'tipo_equipo_id' => $otroTipo->id,
            'alcance_indicacion' => '0 - 10 bar',
        ])
        ->assertSessionHasNoErrors();
});

test('actualiza un alcance de medicion conservando su propia indicacion', function () {
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create([
        'tipo_equipo_id' => $this->tipoEquipo->id,
        'alcance_indicacion' => '0 - 10 bar',
    ]);

    $this->actingAs($this->admin)
        ->put(route('alcances-medicion.update', $alcance), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 10 bar',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->put(route('alcances-medicion.update', $alcance), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 20 bar',
        ])
        ->assertRedirect(route('alcances-medicion.index'));

    expect($alcance->fresh()->alcance_indicacion)->toBe('0 - 20 bar');
});

test('no permite actualizar ni eliminar alcances de otro tenant', function () {
    $ajeno = MedicionAlcance::factory()->create(['alcance_indicacion' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->put(route('alcances-medicion.update', $ajeno), [
            'tipo_equipo_id' => $ajeno->tipo_equipo_id,
            'alcance_indicacion' => 'Hackeado',
        ])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('alcances-medicion.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->alcance_indicacion)->toBe('Ajeno');
});

test('elimina un alcance de medicion junto con sus detalles', function () {
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create(['tipo_equipo_id' => $this->tipoEquipo->id]);
    $detalle = DetalleMedicionAlcance::factory()->for($this->tenant)->create(['medicion_alcance_id' => $alcance->id]);

    $this->actingAs($this->admin)
        ->delete(route('alcances-medicion.destroy', $alcance))
        ->assertRedirect(route('alcances-medicion.index'));

    expect(MedicionAlcance::find($alcance->id))->toBeNull()
        ->and(DetalleMedicionAlcance::find($detalle->id))->toBeNull()
        ->and(MedicionAlcance::withTrashed()->find($alcance->id))->not->toBeNull();
});

test('no elimina un alcance cuyos detalles ya se usaron en calibraciones', function () {
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create(['tipo_equipo_id' => $this->tipoEquipo->id]);
    $detalle = DetalleMedicionAlcance::factory()->for($this->tenant)->create(['medicion_alcance_id' => $alcance->id]);
    registrarMedicionDeCalibracionSobre($detalle);

    $this->actingAs($this->admin)
        ->from(route('alcances-medicion.index'))
        ->delete(route('alcances-medicion.destroy', $alcance))
        ->assertRedirect(route('alcances-medicion.index'));

    expect(MedicionAlcance::find($alcance->id))->not->toBeNull()
        ->and(DetalleMedicionAlcance::find($detalle->id))->not->toBeNull();
});

test('solo acepta alcances de indicacion registrados en las especificaciones tecnicas del tenant', function () {
    registrarAlcanceIndicacion(Tenant::factory()->create(), '0 - 999 bar', $this->tipoEquipo);
    $otroTipo = TipoEquipo::factory()->for($this->tenant)->create();
    registrarAlcanceIndicacion($this->tenant, '0 - 500 bar', $otroTipo);

    foreach (['0 - 999 bar', '0 - 500 bar', 'texto libre'] as $alcance) {
        $this->actingAs($this->admin)
            ->post(route('alcances-medicion.store'), [
                'tipo_equipo_id' => $this->tipoEquipo->id,
                'alcance_indicacion' => $alcance,
            ])
            ->assertSessionHasErrors('alcance_indicacion');
    }

    expect(MedicionAlcance::count())->toBe(0);
});

test('crea el alcance junto con sus detalles en una sola solicitud', function () {
    $unidad = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.store'), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 10 bar',
            'detalles' => [
                ['unidad_medida_id' => $unidad->id, 'valor_instrumento' => '5', 'emp' => '0.1', 'incertidumbre' => '0.05'],
                ['unidad_medida_id' => $unidad->id, 'valor_instrumento' => '10', 'emp' => '0.2', 'incertidumbre' => '0.1'],
            ],
        ])
        ->assertRedirect(route('alcances-medicion.index'));

    $alcance = MedicionAlcance::firstOrFail();

    expect($alcance->detalleMedicionAlcance)->toHaveCount(2)
        ->and($alcance->detalleMedicionAlcance->pluck('tenant_id')->unique()->all())->toBe([$this->tenant->id])
        ->and($alcance->detalleMedicionAlcance->first()->valor_instrumento)->toBe('5.00');
});

test('si un detalle es invalido no se crea ni el alcance', function () {
    $ajena = UnidadMedida::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('alcances-medicion.store'), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 10 bar',
            'detalles' => [
                ['unidad_medida_id' => $ajena->id, 'valor_instrumento' => 'x', 'emp' => '-1', 'incertidumbre' => ''],
            ],
        ])
        ->assertSessionHasErrors([
            'detalles.0.unidad_medida_id',
            'detalles.0.valor_instrumento',
            'detalles.0.emp',
            'detalles.0.incertidumbre',
        ]);

    expect(MedicionAlcance::count())->toBe(0)
        ->and(DetalleMedicionAlcance::count())->toBe(0);
});

test('al editar sincroniza los detalles: actualiza, crea y elimina los que faltan', function () {
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create([
        'tipo_equipo_id' => $this->tipoEquipo->id,
        'alcance_indicacion' => '0 - 10 bar',
    ]);
    $unidad = UnidadMedida::factory()->for($this->tenant)->create();
    $conservado = DetalleMedicionAlcance::factory()->for($this->tenant)->create(['medicion_alcance_id' => $alcance->id, 'unidad_medida_id' => $unidad->id]);
    $quitado = DetalleMedicionAlcance::factory()->for($this->tenant)->create(['medicion_alcance_id' => $alcance->id, 'unidad_medida_id' => $unidad->id]);

    $this->actingAs($this->admin)
        ->put(route('alcances-medicion.update', $alcance), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 10 bar',
            'sincronizar_detalles' => '1',
            'detalles' => [
                ['id' => $conservado->id, 'unidad_medida_id' => $unidad->id, 'valor_instrumento' => '77', 'emp' => '1', 'incertidumbre' => '1'],
                ['unidad_medida_id' => $unidad->id, 'valor_instrumento' => '88', 'emp' => '2', 'incertidumbre' => '2'],
            ],
        ])
        ->assertRedirect(route('alcances-medicion.index'));

    expect($conservado->fresh()->valor_instrumento)->toBe('77.00')
        ->and(DetalleMedicionAlcance::find($quitado->id))->toBeNull()
        ->and($alcance->detalleMedicionAlcance()->pluck('valor_instrumento')->all())->toBe(['77.00', '88.00']);
});

test('al editar sin sincronizar detalles no se tocan los existentes', function () {
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create([
        'tipo_equipo_id' => $this->tipoEquipo->id,
        'alcance_indicacion' => '0 - 10 bar',
    ]);
    $detalle = DetalleMedicionAlcance::factory()->for($this->tenant)->create(['medicion_alcance_id' => $alcance->id]);

    $this->actingAs($this->admin)
        ->put(route('alcances-medicion.update', $alcance), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 20 bar',
        ])
        ->assertSessionHasNoErrors();

    expect(DetalleMedicionAlcance::find($detalle->id))->not->toBeNull();
});

test('al editar no permite quitar un detalle ya usado en calibraciones y no guarda nada', function () {
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create([
        'tipo_equipo_id' => $this->tipoEquipo->id,
        'alcance_indicacion' => '0 - 10 bar',
    ]);
    $usado = DetalleMedicionAlcance::factory()->for($this->tenant)->create(['medicion_alcance_id' => $alcance->id]);
    registrarMedicionDeCalibracionSobre($usado);

    $this->actingAs($this->admin)
        ->from(route('alcances-medicion.index'))
        ->put(route('alcances-medicion.update', $alcance), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 20 bar',
            'sincronizar_detalles' => '1',
        ])
        ->assertRedirect(route('alcances-medicion.index'));

    expect(DetalleMedicionAlcance::find($usado->id))->not->toBeNull()
        ->and($alcance->fresh()->alcance_indicacion)->toBe('0 - 10 bar');
});

test('al editar rechaza el id de un detalle de otro alcance', function () {
    $alcance = MedicionAlcance::factory()->for($this->tenant)->create([
        'tipo_equipo_id' => $this->tipoEquipo->id,
        'alcance_indicacion' => '0 - 10 bar',
    ]);
    $ajeno = DetalleMedicionAlcance::factory()->for($this->tenant)->create();
    $unidad = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('alcances-medicion.update', $alcance), [
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'alcance_indicacion' => '0 - 10 bar',
            'sincronizar_detalles' => '1',
            'detalles' => [
                ['id' => $ajeno->id, 'unidad_medida_id' => $unidad->id, 'valor_instrumento' => '1', 'emp' => '1', 'incertidumbre' => '1'],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.id');
});
