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
        'tipo_servicio' => 'MANTENIMIENTO',
        'dias_plazo_vencimiento' => 30,
        'estado_vencimiento' => 'AL_DIA',
    ], $overrides);
}

test('crea una programacion de servicio del equipo', function () {
    $datos = datosProgramacion([
        'intervalo_servicio' => 90,
        'fecha_ultimo_servicio' => '2026-01-10',
        'fecha_proximo_servicio' => '2026-04-10',
    ]);

    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), $datos)
        ->assertRedirect();

    $programacion = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($programacion->tipo_servicio)->toBe('MANTENIMIENTO')
        ->and($programacion->estado_vencimiento)->toBe('AL_DIA')
        ->and($programacion->fecha_ultimo_servicio->toDateString())->toBe('2026-01-10')
        ->and($programacion->tenant_id)->toBe($this->tenant->id);
});

test('un equipo puede tener mas de una programacion de servicio', function () {
    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion(['tipo_servicio' => 'MANTENIMIENTO']),
    );

    $this->actingAs($this->admin)->post(
        route('equipos.programaciones.store', $this->equipo),
        datosProgramacion(['tipo_servicio' => 'CALIBRACION']),
    );

    expect(EquipoProgramacion::where('equipo_id', $this->equipo->id)->count())->toBe(2)
        ->and($this->equipo->equipoProgramaciones()->pluck('tipo_servicio')->all())
        ->toBe(['MANTENIMIENTO', 'CALIBRACION']);
});

test('las fechas y el intervalo de servicio son opcionales', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion())
        ->assertSessionHasNoErrors();

    $programacion = EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($programacion->intervalo_servicio)->toBeNull()
        ->and($programacion->fecha_ultimo_servicio)->toBeNull();
});

test('rechaza la programacion sin los campos requeridos', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), [])
        ->assertSessionHasErrors(['tipo_servicio', 'dias_plazo_vencimiento', 'estado_vencimiento']);
});

test('rechaza un tipo de servicio o estado de vencimiento que no exista', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion([
            'tipo_servicio' => 'OTRO',
            'estado_vencimiento' => 'OTRO',
        ]))
        ->assertSessionHasErrors(['tipo_servicio', 'estado_vencimiento']);
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
        'tenant_id' => $ajeno->tenant_id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('programaciones.destroy', $programacion))
        ->assertForbidden();

    expect(EquipoProgramacion::find($programacion->id))->not->toBeNull();
});
