<?php

use App\Enums\TenantRole;
use App\Models\EquipoProgramacion;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->equipo = crearEquipoDeTipo(crearTipoEquipo($this->tenant));
});

function datosProgramacion(array $overrides = []): array
{
    return array_merge([
        'tipo_servicio' => ['MANTENIMIENTO'],
    ], $overrides);
}

test('crea una programacion de servicio del equipo', function () {
    $datos = datosProgramacion([
        'intervalo_servicio' => 90,
        'intervalo_unidad' => 'DIAS',
        'fecha_ultimo_servicio' => '2026-01-10',
    ]);

    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), $datos)
        ->assertRedirect();

    $programacion = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($programacion->tipo_servicio)->toBe('MANTENIMIENTO')
        ->and($programacion->fecha_ultimo_servicio->toDateString())->toBe('2026-01-10')
        ->and($programacion->fecha_proximo_servicio->toDateString())->toBe('2026-04-10')
        ->and($programacion->tenant_id)->toBe($this->tenant->id);
});

test('un equipo puede tener mas de una programacion de servicio', function () {
    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion(['tipo_servicio' => ['MANTENIMIENTO']]),
    );

    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion(['tipo_servicio' => ['CALIBRACION']]),
    );

    expect(EquipoProgramacion::where('equipo_id', $this->equipo->id)->count())->toBe(2)
        ->and($this->equipo->equipoProgramaciones()->pluck('tipo_servicio')->all())
        ->toBe(['MANTENIMIENTO', 'CALIBRACION']);
});

test('una misma programacion puede tener varios tipos de servicio a la vez', function () {
    $this->actingAs($this->admin)
        ->post(
            route('equipos.programaciones.store', $this->equipo),
            datosProgramacion(['tipo_servicio' => ['MANTENIMIENTO', 'CALIBRACION']]),
        )
        ->assertSessionHasNoErrors();

    $programacion = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($programacion->tipo_servicio)->toBe('MANTENIMIENTO,CALIBRACION');
});

test('calcula la fecha del proximo servicio sumando el intervalo en semanas o meses', function () {
    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion([
            'intervalo_servicio' => 2,
            'intervalo_unidad' => 'SEMANAS',
            'fecha_ultimo_servicio' => '2026-01-01',
        ]),
    );

    $enSemanas = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();
    expect($enSemanas->fecha_proximo_servicio->toDateString())->toBe('2026-01-15');

    $enSemanas->delete();

    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion([
            'intervalo_servicio' => 6,
            'intervalo_unidad' => 'MESES',
            'fecha_ultimo_servicio' => '2026-01-01',
        ]),
    );

    $enMeses = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();
    expect($enMeses->fecha_proximo_servicio->toDateString())->toBe('2026-07-01');
});

test('no calcula la fecha del proximo servicio si falta la fecha del ultimo servicio o el intervalo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion())
        ->assertSessionHasNoErrors();

    $programacion = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($programacion->intervalo_servicio)->toBeNull()
        ->and($programacion->fecha_ultimo_servicio)->toBeNull()
        ->and($programacion->fecha_proximo_servicio)->toBeNull();
});

test('el estado de vencimiento se calcula comparando la fecha del proximo servicio con hoy', function () {
    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion([
            'intervalo_servicio' => 10,
            'intervalo_unidad' => 'DIAS',
            'fecha_ultimo_servicio' => now()->subDays(20)->toDateString(),
        ]),
    );
    $vencido = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();
    expect($vencido->estado_vencimiento)->toBe('VENCIDO');
    $vencido->delete();

    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion([
            'intervalo_servicio' => 10,
            'intervalo_unidad' => 'DIAS',
            'fecha_ultimo_servicio' => now()->toDateString(),
        ]),
    );
    $proximoAVencer = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();
    expect($proximoAVencer->estado_vencimiento)->toBe('PROXIMO_A_VENCER');
    $proximoAVencer->delete();

    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion([
            'intervalo_servicio' => 60,
            'intervalo_unidad' => 'DIAS',
            'fecha_ultimo_servicio' => now()->toDateString(),
        ]),
    );
    $alDia = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();
    expect($alDia->estado_vencimiento)->toBe('AL_DIA');
});

test('rechaza la programacion sin tipo de servicio', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), [])
        ->assertSessionHasErrors('tipo_servicio');
});

test('rechaza un tipo de servicio que no exista', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion([
            'tipo_servicio' => ['OTRO'],
        ]))
        ->assertSessionHasErrors('tipo_servicio.0');
});

test('rechaza un intervalo de servicio sin su unidad', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion([
            'intervalo_servicio' => 30,
        ]))
        ->assertSessionHasErrors('intervalo_unidad');
});

test('un usuario sin permiso de editar equipos no puede guardar la programacion', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();

    $this->actingAs($recepcion)
        ->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion())
        ->assertForbidden();
});

test('no permite guardar la programacion de un equipo de otro tenant', function () {
    $ajeno = crearEquipoDeTipo(crearTipoEquipo(Tenant::factory()->create()), 'EQ-9999');

    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $ajeno), datosProgramacion())
        ->assertForbidden();
});

test('elimina una programacion de servicio', function () {
    $this->actingAs($this->admin)->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion());
    $programacion = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('programaciones.destroy', $programacion))
        ->assertRedirect();

    expect(EquipoProgramacion::find($programacion->id))->toBeNull();
});

test('un usuario sin permiso de editar equipos no puede eliminar una programacion', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();

    $this->actingAs($this->admin)->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion());
    $programacion = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();

    $this->actingAs($recepcion)
        ->delete(route('programaciones.destroy', $programacion))
        ->assertForbidden();
});

test('no permite eliminar una programacion de otro tenant', function () {
    $ajeno = crearEquipoDeTipo(crearTipoEquipo(Tenant::factory()->create()), 'EQ-9999');
    $programacion = $ajeno->equipoProgramaciones()->create([
        ...datosProgramacion(),
        'tipo_servicio' => 'MANTENIMIENTO',
        'tenant_id' => $ajeno->tenant_id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('programaciones.destroy', $programacion))
        ->assertForbidden();

    expect(EquipoProgramacion::find($programacion->id))->not->toBeNull();
});
