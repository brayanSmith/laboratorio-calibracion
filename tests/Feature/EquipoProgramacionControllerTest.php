<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\EquipoProgramacion;
use App\Models\Fabricante;
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

test('una programacion de servicio del equipo siempre queda como preventiva', function () {
    $this->actingAs($this->admin)->post(route('equipos.programaciones.store', $this->equipo), datosProgramacion());

    expect(EquipoProgramacion::where('equipo_id', $this->equipo->id)->firstOrFail()->tipo_mantenimiento)
        ->toBe('PREVENTIVO');
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

function crearEquipoParaBusqueda(Bahia $bahia, string $codigo = 'EQ-5001', string $tipoMantenimiento = 'A'): Equipo
{
    $tenantId = $bahia->tenant_id;

    return Equipo::create([
        'codigo' => $codigo,
        'tipo_equipo_id' => crearTipoEquipo($bahia->tenant, $codigo, $tipoMantenimiento)->id,
        'tipo_tecnologia' => 'DIGITAL',
        'modelo' => 'Modelo X',
        'fabricante_id' => Fabricante::factory()->create(['tenant_id' => $tenantId])->id,
        'numero_serie' => 'SN-1',
        'area_id' => $bahia->area_id,
        'bahia_id' => $bahia->id,
        'condicion_actual' => 'Bueno',
        'cliente_id' => Cliente::factory()->create(['tenant_id' => $tenantId, 'nombre' => 'Cliente 1'])->id,
        'tenant_id' => $tenantId,
    ]);
}

function crearProgramacion(Equipo $equipo, array $overrides = []): EquipoProgramacion
{
    return $equipo->equipoProgramaciones()->create(array_merge([
        'tipo_servicio' => 'MANTENIMIENTO',
        'fecha_proximo_servicio' => now()->addDays(5)->toDateString(),
        'dias_plazo_vencimiento' => 30,
        'tenant_id' => $equipo->tenant_id,
    ], $overrides));
}

test('busca programaciones cuya fecha de proximo servicio cae en el rango y bahia dados', function () {
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipo = crearEquipoParaBusqueda($bahia);
    $programacion = crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-06-15']);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_id' => $bahia->id,
        ]))
        ->assertOk()
        ->assertJson([
            [
                'id' => $programacion->id,
                'tipo_servicio' => 'MANTENIMIENTO',
                'equipo' => [
                    'id' => $equipo->id,
                    'codigo' => $equipo->codigo,
                    'modelo' => $equipo->modelo,
                    'cliente' => ['nombre' => 'Cliente 1'],
                ],
            ],
        ]);
});

test('no incluye programaciones fuera del rango de fechas', function () {
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipo = crearEquipoParaBusqueda($bahia);
    crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-05-31']);
    crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-07-01']);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_id' => $bahia->id,
        ]))
        ->assertOk()
        ->assertJsonCount(0);
});

test('no incluye programaciones de otra bahia ni de otro tenant', function () {
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    $otraBahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    crearProgramacion(crearEquipoParaBusqueda($otraBahia, 'EQ-OTRA'), ['fecha_proximo_servicio' => '2026-06-15']);

    $otroTenant = Tenant::factory()->create();
    $bahiaAjena = Bahia::factory()->create(['tenant_id' => $otroTenant->id]);
    crearProgramacion(crearEquipoParaBusqueda($bahiaAjena, 'EQ-AJENO'), ['fecha_proximo_servicio' => '2026-06-15']);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_id' => $bahia->id,
        ]))
        ->assertOk()
        ->assertJsonCount(0);
});

test('no incluye programaciones agendadas, canceladas ni eliminadas', function () {
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipo = crearEquipoParaBusqueda($bahia);
    crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-06-15', 'estado_programacion' => 'AGENDADO']);
    crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-06-16', 'estado_programacion' => 'CANCELADO']);
    crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-06-17'])->delete();

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_id' => $bahia->id,
        ]))
        ->assertOk()
        ->assertJsonCount(0);
});

test('no incluye programaciones de mantenimiento correctivo', function () {
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipo = crearEquipoParaBusqueda($bahia);
    crearProgramacion($equipo, [
        'fecha_proximo_servicio' => '2026-06-15',
        'tipo_mantenimiento' => 'CORRECTIVO',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_id' => $bahia->id,
        ]))
        ->assertOk()
        ->assertJsonCount(0);
});

test('ordena los resultados por fecha de proximo servicio', function () {
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipo = crearEquipoParaBusqueda($bahia);
    $tardio = crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-06-20']);
    $temprano = crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-06-05']);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_id' => $bahia->id,
        ]))
        ->assertOk()
        ->assertJsonPath('0.id', $temprano->id)
        ->assertJsonPath('1.id', $tardio->id);
});

test('rechaza la busqueda sin desde, hasta o bahia', function () {
    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['desde', 'hasta', 'bahia_id']);
});

test('rechaza la busqueda cuando hasta es anterior a desde', function () {
    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-30',
            'hasta' => '2026-06-01',
            'bahia_id' => Bahia::factory()->create(['tenant_id' => $this->tenant->id])->id,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hasta');
});

test('rechaza la busqueda con una bahia de otro tenant', function () {
    $bahiaAjena = Bahia::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_id' => $bahiaAjena->id,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bahia_id');
});

test('un usuario sin permiso de ver equipos no puede buscar programaciones', function () {
    $sinPermiso = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinPermiso)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_id' => $bahia->id,
        ]))
        ->assertForbidden();
});

test('un invitado no puede buscar programaciones', function () {
    $this->getJson(route('equipo-programaciones.buscar'))->assertUnauthorized();
});

test('lista los equipos de una bahia para agendar un mantenimiento correctivo', function () {
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipo = crearEquipoParaBusqueda($bahia);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.equipos-disponibles', ['bahia_id' => $bahia->id]))
        ->assertOk()
        ->assertJson([
            ['id' => $equipo->id, 'codigo' => $equipo->codigo, 'modelo' => $equipo->modelo],
        ]);
});

test('no incluye equipos de otra bahia ni de otro tenant en los disponibles', function () {
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    $otraBahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);
    crearEquipoParaBusqueda($otraBahia, 'EQ-OTRA');

    $otroTenant = Tenant::factory()->create();
    $bahiaAjena = Bahia::factory()->create(['tenant_id' => $otroTenant->id]);
    crearEquipoParaBusqueda($bahiaAjena, 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.equipos-disponibles', ['bahia_id' => $bahia->id]))
        ->assertOk()
        ->assertJsonCount(0);
});

test('rechaza listar equipos disponibles de una bahia de otro tenant', function () {
    $bahiaAjena = Bahia::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.equipos-disponibles', ['bahia_id' => $bahiaAjena->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bahia_id');
});

test('un usuario sin permiso de ver equipos no puede listar equipos disponibles', function () {
    $sinPermiso = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $bahia = Bahia::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinPermiso)
        ->getJson(route('equipo-programaciones.equipos-disponibles', ['bahia_id' => $bahia->id]))
        ->assertForbidden();
});
