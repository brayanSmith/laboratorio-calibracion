<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\EmpresaTercero;
use App\Models\EquipoProgramacion;
use App\Models\Ingreso;
use App\Models\Mantenimiento;
use App\Models\MantenimientoCheckList;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\ServicioTercero;
use App\Models\Tenant;
use App\Models\TipoEquipoCheckList;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    $this->novedadMantenimiento = Novedad::create(['nombre' => 'Equipo con desgaste', 'categoria' => 'MANTENIMIENTO', 'tenant_id' => $this->tenant->id]);
});

/** Equipo_programacion ya recibido (ingresado) y enlazado a un ingreso RECIBIDO. */
function crearEquipoRecibido(Bahia $bahia, string $codigo = 'EQ-5001'): EquipoProgramacion
{
    $equipo = crearEquipoParaBusqueda($bahia, $codigo);
    $ingreso = Ingreso::factory()->create(['bahia_id' => $bahia->id, 'estado_ingreso' => 'RECIBIDO']);

    return crearProgramacion($equipo, [
        'ingreso_id' => $ingreso->id,
        'agendar' => true,
        'ingresado' => true,
        'estado_programacion' => 'AGENDADO',
    ]);
}

test('lista los equipos recibidos sin orden de trabajo para agendar mantenimiento', function () {
    $listo = crearEquipoRecibido($this->bahia, 'EQ-LISTO');

    $noIngresado = crearEquipoRecibido($this->bahia, 'EQ-NO-INGRESADO');
    $noIngresado->update(['ingresado' => false, 'estado_programacion' => 'CANCELADO']);

    $noRecibido = crearEquipoParaBusqueda($this->bahia, 'EQ-PENDIENTE');
    crearProgramacion($noRecibido, ['ingreso_id' => null, 'ingresado' => true]);

    $yaAgendado = crearEquipoRecibido($this->bahia, 'EQ-YA-AGENDADO');
    OrdenTrabajo::create([
        'codigo' => 'OT-0001',
        'equipo_programacion_id' => $yaAgendado->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'listo_para_mantenimiento' => true,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $listo->id)
        ->assertJsonPath('0.devolucion', false)
        ->assertJsonPath('0.equipo.codigo', 'EQ-LISTO');
});

test('un equipo vuelve a aparecer si su orden de trabajo no esta agendada todavia', function () {
    $devuelto = crearEquipoRecibido($this->bahia, 'EQ-DEVUELTO');

    // La orden anterior ya se trabajó (mantenimiento y calibración finalizados) pero la
    // calibración se devolvió a mantenimiento, lo que crea esta orden nueva sin agendar
    // (ver CalibracionController::finalizar()).
    OrdenTrabajo::create([
        'codigo' => 'OT-0001',
        'equipo_programacion_id' => $devuelto->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'FINALIZADO',
        'listo_para_mantenimiento' => true,
        'mantenimiento_finalizado' => true,
        'calibracion_finalizado' => true,
        'tenant_id' => $this->tenant->id,
    ]);
    OrdenTrabajo::create([
        'codigo' => 'OT-0002',
        'equipo_programacion_id' => $devuelto->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'devolucion' => true,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $devuelto->id)
        ->assertJsonPath('0.devolucion', true);
});

test('al agendar una orden por devolucion reutiliza esa orden en vez de crear otra', function () {
    $devuelto = crearEquipoRecibido($this->bahia, 'EQ-DEVUELTO');

    OrdenTrabajo::create([
        'codigo' => 'OT-0001',
        'equipo_programacion_id' => $devuelto->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'FINALIZADO',
        'listo_para_mantenimiento' => true,
        'mantenimiento_finalizado' => true,
        'calibracion_finalizado' => true,
        'tenant_id' => $this->tenant->id,
    ]);
    $ordenDevolucion = OrdenTrabajo::create([
        'codigo' => 'OT-0002',
        'equipo_programacion_id' => $devuelto->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'devolucion' => true,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $devuelto->id => [
                    'listo_para_mantenimiento' => '1',
                    'mantenimiento_asignado_tercero' => '0',
                    'tecnico_id' => $this->admin->id,
                    'novedad_id' => $this->novedadMantenimiento->id,
                ],
            ],
        ])
        ->assertRedirect();

    // Reutiliza la orden de la devolución (mismo id y código) en vez de crear una nueva.
    expect(OrdenTrabajo::where('equipo_programacion_id', $devuelto->id)->count())->toBe(2);
    expect($ordenDevolucion->refresh())
        ->listo_para_mantenimiento->toBeTrue()
        ->devolucion->toBeTrue()
        ->codigo->toBe('OT-0002');

    $mantenimiento = Mantenimiento::where('orden_trabajo_id', $ordenDevolucion->id)->firstOrFail();
    expect($mantenimiento->tecnico_id)->toBe($this->admin->id);

    // Ya agendada, no debe volver a aparecer en "Agendar Mantenimiento".
    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos'))
        ->assertOk()
        ->assertJsonCount(0);
});

test('no incluye equipos recibidos de otro tenant en la lista para agendar mantenimiento', function () {
    $ajeno = Bahia::factory()->create();
    crearEquipoRecibido($ajeno, 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos'))
        ->assertOk()
        ->assertJsonCount(0);
});

test('agenda mantenimiento con un tecnico propio y crea el mantenimiento con la novedad elegida', function () {
    $programacion = crearEquipoRecibido($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => [
                    'listo_para_mantenimiento' => '1',
                    'mantenimiento_asignado_tercero' => '0',
                    'tecnico_id' => $this->admin->id,
                    'novedad_id' => $this->novedadMantenimiento->id,
                ],
            ],
        ])
        ->assertRedirect();

    $orden = OrdenTrabajo::where('equipo_programacion_id', $programacion->id)->firstOrFail();

    expect($orden)
        ->codigo->toBe('OT-0001')
        ->estado->toBe('EN_BAHIA')
        ->listo_para_mantenimiento->toBeTrue()
        ->mantenimiento_asignado_tercero->toBeFalse()
        ->fecha_programada_orden_trabajo->toDateString()->toBe(now()->toDateString());

    $mantenimiento = Mantenimiento::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($mantenimiento)
        ->tipo_mantenimiento->toBe($programacion->refresh()->tipo_mantenimiento)
        ->tecnico_id->toBe($this->admin->id)
        ->novedad_id->toBe($this->novedadMantenimiento->id)
        ->estado_mantenimiento->toBe('PENDIENTE');

    expect(ServicioTercero::where('orden_trabajo_id', $orden->id)->count())->toBe(0);

    expect($programacion->refresh())
        ->fase_programacion->toBe('MANTENIMIENTO')
        ->subfase_programacion->toBe('Pendiente');
});

test('crea el checklist del mantenimiento copiando los items del tipo de equipo', function () {
    $programacion = crearEquipoRecibido($this->bahia);
    $tipoEquipoId = $programacion->equipo->tipo_equipo_id;

    $item1 = TipoEquipoCheckList::create(['tipo_equipo_id' => $tipoEquipoId, 'nombre' => 'Revisar cableado', 'tenant_id' => $this->tenant->id]);
    $item2 = TipoEquipoCheckList::create(['tipo_equipo_id' => $tipoEquipoId, 'nombre' => 'Limpiar sensores', 'tenant_id' => $this->tenant->id]);
    // De otro tipo de equipo: no debe copiarse.
    TipoEquipoCheckList::create(['tipo_equipo_id' => crearTipoEquipo($this->tenant, 'Otro tipo')->id, 'nombre' => 'Ajeno', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => [
                    'listo_para_mantenimiento' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $mantenimiento = Mantenimiento::whereHas(
        'ordenTrabajo',
        fn ($query) => $query->where('equipo_programacion_id', $programacion->id),
    )->firstOrFail();

    $idsCopiados = MantenimientoCheckList::where('mantenimiento_id', $mantenimiento->id)
        ->pluck('tipo_equipo_check_list_id');

    expect($idsCopiados->sort()->values()->all())->toBe([$item1->id, $item2->id])
        ->and(MantenimientoCheckList::where('mantenimiento_id', $mantenimiento->id)->where('cumple', true)->count())->toBe(0);
});

test('no crea checklist cuando el tipo de equipo no tiene items', function () {
    $programacion = crearEquipoRecibido($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => [
                    'listo_para_mantenimiento' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $mantenimiento = Mantenimiento::whereHas(
        'ordenTrabajo',
        fn ($query) => $query->where('equipo_programacion_id', $programacion->id),
    )->firstOrFail();

    expect(MantenimientoCheckList::where('mantenimiento_id', $mantenimiento->id)->count())->toBe(0);
});

test('agenda mantenimiento asignado a un tercero y crea el servicio_tercero con la empresa elegida', function () {
    $programacion = crearEquipoRecibido($this->bahia);
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => [
                    'listo_para_mantenimiento' => '1',
                    'mantenimiento_asignado_tercero' => '1',
                    'empresa_tercero_id' => $empresa->id,
                ],
            ],
        ])
        ->assertRedirect();

    $orden = OrdenTrabajo::where('equipo_programacion_id', $programacion->id)->firstOrFail();

    expect($orden)->mantenimiento_asignado_tercero->toBeTrue();

    $servicioTercero = ServicioTercero::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($servicioTercero)
        ->tipo_servicio->toBe('MANTENIMIENTO')
        ->empresa_tercero_id->toBe($empresa->id);

    expect(Mantenimiento::where('orden_trabajo_id', $orden->id)->count())->toBe(0);
});

test('no crea una orden de trabajo para un equipo que no se marco como listo', function () {
    $programacion = crearEquipoRecibido($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => ['listo_para_mantenimiento' => '0'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(OrdenTrabajo::where('equipo_programacion_id', $programacion->id)->count())->toBe(0);
});

test('genera codigos secuenciales para varias ordenes de trabajo creadas en la misma peticion', function () {
    $primero = crearEquipoRecibido($this->bahia, 'EQ-1');
    $segundo = crearEquipoRecibido($this->bahia, 'EQ-2');

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $primero->id => [
                    'listo_para_mantenimiento' => '1',
                    'tecnico_id' => $this->admin->id,
                    'novedad_id' => $this->novedadMantenimiento->id,
                ],
                $segundo->id => [
                    'listo_para_mantenimiento' => '1',
                    'tecnico_id' => $this->admin->id,
                    'novedad_id' => $this->novedadMantenimiento->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $codigos = OrdenTrabajo::whereIn('equipo_programacion_id', [$primero->id, $segundo->id])
        ->orderBy('equipo_programacion_id')
        ->pluck('codigo');

    expect($codigos->all())->toBe(['OT-0001', 'OT-0002']);
});

test('continua la secuencia de codigos del tenant al crear una nueva orden de trabajo', function () {
    $previo = crearEquipoRecibido($this->bahia, 'EQ-PREVIO');
    OrdenTrabajo::create([
        'codigo' => 'OT-0001',
        'equipo_programacion_id' => $previo->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $this->tenant->id,
    ]);

    $nuevo = crearEquipoRecibido($this->bahia, 'EQ-NUEVO');

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $nuevo->id => [
                    'listo_para_mantenimiento' => '1',
                    'tecnico_id' => $this->admin->id,
                    'novedad_id' => $this->novedadMantenimiento->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(OrdenTrabajo::where('equipo_programacion_id', $nuevo->id)->first()->codigo)->toBe('OT-0002');
});

test('exige tecnico al agendar mantenimiento con tecnico propio', function () {
    $programacion = crearEquipoRecibido($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => ['listo_para_mantenimiento' => '1'],
            ],
        ])
        ->assertSessionHasErrors('equipos_listos.0.tecnico_id');

    expect(OrdenTrabajo::where('equipo_programacion_id', $programacion->id)->count())->toBe(0);
});

test('agenda mantenimiento con tecnico propio sin novedad, dejando el resto para cuando se realice', function () {
    $programacion = crearEquipoRecibido($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => [
                    'listo_para_mantenimiento' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $orden = OrdenTrabajo::where('equipo_programacion_id', $programacion->id)->firstOrFail();
    $mantenimiento = Mantenimiento::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($mantenimiento)
        ->tecnico_id->toBe($this->admin->id)
        ->novedad_id->toBeNull()
        ->descripcion->toBeNull()
        ->estado_inicial_equipo->toBeNull()
        ->estado_final_equipo->toBeNull()
        ->estado_mantenimiento->toBe('PENDIENTE');
});

test('exige la empresa tercero al agendar mantenimiento asignado a un tercero', function () {
    $programacion = crearEquipoRecibido($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => [
                    'listo_para_mantenimiento' => '1',
                    'mantenimiento_asignado_tercero' => '1',
                ],
            ],
        ])
        ->assertSessionHasErrors('equipos_listos.0.empresa_tercero_id');

    expect(OrdenTrabajo::where('equipo_programacion_id', $programacion->id)->count())->toBe(0);
});

test('rechaza un equipo que no pertenece al tenant al agendar mantenimiento', function () {
    $ajeno = Bahia::factory()->create();
    $programacionAjena = crearEquipoRecibido($ajeno);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacionAjena->id => ['listo_para_mantenimiento' => '1'],
            ],
        ])
        ->assertSessionHasErrors('equipos_listos.0.id');
});

test('rechaza un equipo que no esta ingresado al agendar mantenimiento', function () {
    $programacion = crearEquipoRecibido($this->bahia);
    $programacion->update(['ingresado' => false]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => ['listo_para_mantenimiento' => '1'],
            ],
        ])
        ->assertSessionHasErrors('equipos_listos.0.id');
});

test('un usuario sin permiso de editar equipos no puede agendar mantenimiento, pero si puede listarlos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $programacion = crearEquipoRecibido($this->bahia);

    $this->actingAs($recepcion)
        ->getJson(route('orden-trabajos.equipos-listos'))
        ->assertOk()
        ->assertJsonCount(1);

    $this->actingAs($recepcion)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $programacion->id => [
                    'listo_para_mantenimiento' => '1',
                    'tecnico_id' => $this->admin->id,
                    'novedad_id' => $this->novedadMantenimiento->id,
                ],
            ],
        ])
        ->assertForbidden();

    expect(OrdenTrabajo::where('equipo_programacion_id', $programacion->id)->count())->toBe(0);
});

test('un invitado no puede agendar mantenimiento', function () {
    $this->post(route('orden-trabajos.store'), ['equipos_listos' => []])
        ->assertRedirect(route('login'));
});
