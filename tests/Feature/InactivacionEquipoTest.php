<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Ingresos\Models\Ingreso;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

function novedadQueInactiva(Tenant $tenant, string $categoria, bool $inactivar = true): Novedad
{
    return Novedad::create([
        'nombre' => "Equipo destruido ({$categoria})",
        'categoria' => $categoria,
        'inactivar_equipo' => $inactivar,
        'tenant_id' => $tenant->id,
    ]);
}

test('una novedad que inactiva el equipo, reportada al recibir el ingreso, lo deja inactivo con su motivo', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id, 'estado_ingreso' => 'PENDIENTE']);
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $programacion = crearProgramacion($equipo, ['ingreso_id' => $ingreso->id, 'estado_programacion' => 'AGENDADO', 'agendar' => true]);
    $novedad = novedadQueInactiva($this->tenant, 'INGRESO');

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => Cliente::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'equipos_recibidos' => [
                $programacion->id => ['ingresado' => '0', 'novedad_ingreso_id' => $novedad->id],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($equipo->refresh())
        ->activo->toBeFalse()
        ->motivo_inactivo->toBe('Novedad en ingreso: Equipo destruido (INGRESO)');
});

test('una novedad que no inactiva el equipo lo deja activo', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $mantenimiento->update(['novedad_id' => novedadQueInactiva($this->tenant, 'MANTENIMIENTO', inactivar: false)->id]);

    expect($mantenimiento->ordenTrabajo->equipoProgramacion->equipo->refresh()->activo)->toBeTrue();
});

test('las novedades que inactivan el equipo en mantenimiento, calibracion y despacho lo dejan inactivo', function (string $categoria, string $modulo, Closure $reportar) {
    $equipo = $reportar($this->bahia, novedadQueInactiva($this->tenant, $categoria));

    expect($equipo->refresh())
        ->activo->toBeFalse()
        ->motivo_inactivo->toBe("Novedad en {$modulo}: Equipo destruido ({$categoria})");
})->with([
    'mantenimiento' => ['MANTENIMIENTO', 'mantenimiento', function (Bahia $bahia, Novedad $novedad): Equipo {
        $mantenimiento = crearMantenimiento($bahia);
        $mantenimiento->update(['novedad_id' => $novedad->id]);

        return $mantenimiento->ordenTrabajo->equipoProgramacion->equipo;
    }],
    'calibracion' => ['CALIBRACION', 'calibración', function (Bahia $bahia, Novedad $novedad): Equipo {
        $calibracion = crearCalibracion($bahia);
        $calibracion->update(['novedad_id' => $novedad->id]);

        return $calibracion->ordenTrabajo->equipoProgramacion->equipo;
    }],
    'despacho' => ['SALIDA', 'despacho', function (Bahia $bahia, Novedad $novedad): Equipo {
        $despacho = crearDespachoListo($bahia);
        $despacho->update(['novedad_id' => $novedad->id]);

        return $despacho->ordenTrabajo->equipoProgramacion->equipo;
    }],
]);

test('finalizar un mantenimiento con el equipo fuera de servicio lo deja inactivo', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)->post(route('mantenimientos.iniciar', $mantenimiento));
    $this->actingAs($this->admin)
        ->post(route('mantenimientos.finalizar', $mantenimiento), ['estado_final_equipo' => 'FUERA_DE_SERVICIO', 'firmado' => '1'])
        ->assertSessionHasNoErrors();

    expect($mantenimiento->ordenTrabajo->equipoProgramacion->equipo->refresh())
        ->activo->toBeFalse()
        ->motivo_inactivo->toBe('Mantenimiento: Fuera de servicio');
});

test('finalizar un mantenimiento con el equipo operativo lo deja activo', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)->post(route('mantenimientos.iniciar', $mantenimiento));
    $this->actingAs($this->admin)
        ->post(route('mantenimientos.finalizar', $mantenimiento), ['estado_final_equipo' => 'OPERATIVO', 'firmado' => '1'])
        ->assertSessionHasNoErrors();

    expect($mantenimiento->ordenTrabajo->equipoProgramacion->equipo->refresh()->activo)->toBeTrue();
});

test('finalizar una calibracion como fuera de servicio deja el equipo inactivo y no crea despacho', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)->post(route('calibraciones.iniciar', $calibracion));
    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'FUERA_DE_SERVICIO'])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh()->estado_calibracion)->toBe('FUERA_DE_SERVICIO')
        ->and(Despacho::count())->toBe(0)
        ->and($calibracion->ordenTrabajo->equipoProgramacion->equipo->refresh())
        ->activo->toBeFalse()
        ->motivo_inactivo->toBe('Calibración: Fuera de servicio');
});

test('un servicio de tercero fuera de servicio sin re-agendar deja el equipo inactivo', function () {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'FUERA_DE_SERVICIO', 'pdf_servicio' => pdfServicio()])
        ->assertSessionHasNoErrors();

    expect($servicio->ordenTrabajo->equipoProgramacion->equipo->refresh())
        ->activo->toBeFalse()
        ->motivo_inactivo->toBe('Mantenimiento de tercero: Fuera de servicio');
});

test('un servicio de tercero fuera de servicio con re-agendar o aprobado deja el equipo activo', function (array $datos) {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [...$datos, 'pdf_servicio' => pdfServicio()])
        ->assertSessionHasNoErrors();

    expect($servicio->ordenTrabajo->equipoProgramacion->equipo->refresh()->activo)->toBeTrue();
})->with([
    'fuera de servicio con re-agendar' => [['estado_final_equipo' => 'FUERA_DE_SERVICIO', 're_agendar' => true]],
    'aprobado' => [['estado_final_equipo' => 'APROBADO']],
]);

test('al volver a activar el equipo se borra el motivo de inactividad', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $equipo->inactivar('Calibración: Fuera de servicio');

    $equipo->update(['activo' => true]);

    expect($equipo->refresh()->motivo_inactivo)->toBeNull();
});

test('crea y edita una novedad marcada para inactivar el equipo', function () {
    $this->actingAs($this->admin)
        ->post(route('novedades.store'), ['categoria' => 'MANTENIMIENTO', 'nombre' => 'Daño irreparable', 'inactivar_equipo' => '1'])
        ->assertSessionHasNoErrors();

    $novedad = Novedad::sole();
    expect($novedad->inactivar_equipo)->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('novedades.update', $novedad), ['categoria' => 'MANTENIMIENTO', 'nombre' => 'Daño irreparable'])
        ->assertSessionHasNoErrors();

    expect($novedad->refresh()->inactivar_equipo)->toBeFalse();
});

test('inactiva y activa un equipo a mano con su motivo, y lo deja en el historial de estado', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia);

    $this->actingAs($this->admin)
        ->patch(route('equipos.estado.update', $equipo), ['activo' => '0', 'motivo' => 'Dado de baja por daño irreparable'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($equipo->refresh())
        ->activo->toBeFalse()
        ->motivo_inactivo->toBe('Dado de baja por daño irreparable');

    $this->actingAs($this->admin)
        ->patch(route('equipos.estado.update', $equipo), ['activo' => '1', 'motivo' => 'Reparado en planta'])
        ->assertSessionHasNoErrors();

    expect($equipo->refresh())
        ->activo->toBeTrue()
        ->motivo_inactivo->toBeNull()
        ->and($equipo->cambiosEstado()->orderBy('id')->get()->map->only(['activo', 'motivo', 'user_id'])->all())->toBe([
            ['activo' => false, 'motivo' => 'Dado de baja por daño irreparable', 'user_id' => $this->admin->id],
            ['activo' => true, 'motivo' => 'Reparado en planta', 'user_id' => $this->admin->id],
        ]);
});

test('cambiar el estado del equipo exige el motivo y el permiso de editar equipos', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia);

    $this->actingAs($this->admin)
        ->patch(route('equipos.estado.update', $equipo), ['activo' => '0', 'motivo' => ''])
        ->assertSessionHasErrors('motivo');

    $soloVer = usuarioConPermisos($this->tenant, [TenantPermission::EquiposVer]);

    $this->actingAs($soloVer)
        ->patch(route('equipos.estado.update', $equipo), ['activo' => '0', 'motivo' => 'Baja'])
        ->assertForbidden();

    expect($equipo->refresh()->activo)->toBeTrue()
        ->and($equipo->cambiosEstado()->count())->toBe(0);
});

test('la inactivacion automatica tambien queda en el historial de estado', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)->post(route('calibraciones.iniciar', $calibracion));
    $this->actingAs($this->admin)->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'FUERA_DE_SERVICIO']);

    expect($calibracion->ordenTrabajo->equipoProgramacion->equipo->cambiosEstado()->sole())
        ->activo->toBeFalse()
        ->motivo->toBe('Calibración: Fuera de servicio')
        ->user_id->toBe($this->admin->id);
});

test('al recibir, una novedad que inactiva el equipo no se acepta si el equipo ingreso ni con re-agendar', function (array $recibido, string $campoConError) {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id, 'estado_ingreso' => 'PENDIENTE']);
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $programacion = crearProgramacion($equipo, ['ingreso_id' => $ingreso->id, 'estado_programacion' => 'AGENDADO', 'agendar' => true]);
    $novedad = novedadQueInactiva($this->tenant, 'INGRESO');

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => Cliente::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'equipos_recibidos' => [$programacion->id => [...$recibido, 'novedad_ingreso_id' => $novedad->id]],
        ])
        ->assertSessionHasErrors("equipos_recibidos.0.{$campoConError}");

    expect($equipo->refresh()->activo)->toBeTrue();
})->with([
    'equipo ingresado' => [['ingresado' => '1'], 'novedad_ingreso_id'],
    'no ingresado pero re-agendado' => [['ingresado' => '0', 're_agendar' => '1', 'datos_re_agendamiento' => ['fecha_proximo_agendamiento' => '2026-12-01']], 're_agendar'],
]);

test('las novedades de ingreso llegan con si inactivan el equipo, para avisarlo al recibir', function () {
    novedadQueInactiva($this->tenant, 'INGRESO');

    $this->actingAs($this->admin)
        ->get(route('ingresos.index'))
        ->assertInertia(fn ($page) => $page->where('novedadesIngreso.0.inactivar_equipo', true));
});

test('un equipo inactivo no aparece ni se puede agendar para mantenimiento', function () {
    $programacion = crearEquipoRecibido($this->bahia);
    $programacion->equipo->inactivar('Dado de baja');

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos'))
        ->assertOk()
        ->assertJsonCount(0);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), ['equipos_listos' => [
            $programacion->id => ['listo_para_mantenimiento' => '1', 'mantenimiento_asignado_tercero' => '0', 'tecnico_id' => $this->admin->id],
        ]])
        ->assertSessionHasErrors('equipos_listos.0.id');
});

test('crea y edita una novedad marcada como motivo de cancelacion', function () {
    $this->actingAs($this->admin)
        ->post(route('novedades.store'), ['categoria' => 'INGRESO', 'nombre' => 'Cliente no entregó el equipo', 'es_motivo_cancelacion' => '1'])
        ->assertSessionHasNoErrors();

    $novedad = Novedad::sole();
    expect($novedad->es_motivo_cancelacion)->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('novedades.index'))
        ->assertInertia(fn ($page) => $page->where('novedades.0.es_motivo_cancelacion', true));

    $this->actingAs($this->admin)
        ->put(route('novedades.update', $novedad), ['categoria' => 'INGRESO', 'nombre' => 'Cliente no entregó el equipo'])
        ->assertSessionHasNoErrors();

    expect($novedad->refresh()->es_motivo_cancelacion)->toBeFalse();
});

test('al recibir, un motivo de cancelacion solo se acepta si el equipo no ingreso', function (string $ingresado, bool $aceptado) {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id, 'estado_ingreso' => 'PENDIENTE']);
    $programacion = crearProgramacion(crearEquipoParaBusqueda($this->bahia), ['ingreso_id' => $ingreso->id, 'estado_programacion' => 'AGENDADO', 'agendar' => true]);
    $novedad = Novedad::create([
        'nombre' => 'Cliente no entregó el equipo',
        'categoria' => 'INGRESO',
        'es_motivo_cancelacion' => true,
        'tenant_id' => $this->tenant->id,
    ]);

    $respuesta = $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => Cliente::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'equipos_recibidos' => [$programacion->id => ['ingresado' => $ingresado, 'novedad_ingreso_id' => $novedad->id]],
        ]);

    if ($aceptado) {
        $respuesta->assertSessionHasNoErrors();
        expect($programacion->refresh()->novedad_ingreso_id)->toBe($novedad->id);
    } else {
        $respuesta->assertSessionHasErrors('equipos_recibidos.0.novedad_ingreso_id');
    }
})->with([
    'equipo cancelado' => ['0', true],
    'equipo ingresado' => ['1', false],
]);
