<?php

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
    $ingreso = Ingreso::factory()->conBahias($this->bahia)->create(['estado_ingreso' => 'PENDIENTE']);
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

test('un servicio de tercero rechazado sin re-agendar deja el equipo inactivo', function () {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'RECHAZADO', 'pdf_servicio' => pdfServicio()])
        ->assertSessionHasNoErrors();

    expect($servicio->ordenTrabajo->equipoProgramacion->equipo->refresh())
        ->activo->toBeFalse()
        ->motivo_inactivo->toBe('Mantenimiento de tercero: Rechazado');
});

test('un servicio de tercero rechazado con re-agendar o aprobado deja el equipo activo', function (array $datos) {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [...$datos, 'pdf_servicio' => pdfServicio()])
        ->assertSessionHasNoErrors();

    expect($servicio->ordenTrabajo->equipoProgramacion->equipo->refresh()->activo)->toBeTrue();
})->with([
    'rechazado con re-agendar' => [['estado_final_equipo' => 'RECHAZADO', 're_agendar' => true]],
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
