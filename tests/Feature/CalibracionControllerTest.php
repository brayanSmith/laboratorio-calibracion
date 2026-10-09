<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Calibracion;
use App\Models\Despacho;
use App\Models\DetalleMedicionAlcance;
use App\Models\DetalleMedicionCalibracion;
use App\Models\EmpresaTercero;
use App\Models\Laboratorio;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\ServicioTercero;
use App\Models\Tenant;
use App\Models\TiempoServicio;
use App\Models\UnidadMedida;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/** Calibracion completa: equipo recibido -> orden de trabajo -> calibracion. */
function crearCalibracion(Bahia $bahia, array $overrides = [], string $codigo = 'EQ-6001'): Calibracion
{
    $programacion = crearEquipoRecibido($bahia, $codigo);
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $bahia->tenant_id,
    ]);

    return Calibracion::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'fecha_calibracion' => now()->toDateString(),
        'tecnico_id' => User::factory()->forTenant($bahia->tenant)->create()->id,
        'tenant_id' => $bahia->tenant_id,
        ...$overrides,
    ]);
}

/** Detalle de resultados ya creado al agendar (ver
 * OrdenTrabajoController::crearDetallesMedicionCalibracion()), listo para completarse. */
function crearDetalleMedicion(Calibracion $calibracion, array $overrides = []): DetalleMedicionCalibracion
{
    return DetalleMedicionCalibracion::create([
        'calibracion_id' => $calibracion->id,
        'detalle_medicion_alcance_id' => DetalleMedicionAlcance::factory()->create(['tenant_id' => $calibracion->tenant_id])->id,
        'valor_referencia' => 10,
        'unidad_medida_id' => UnidadMedida::factory()->create(['tenant_id' => $calibracion->tenant_id])->id,
        'emp' => 0.5,
        'incertidumbre' => 0.1,
        'tenant_id' => $calibracion->tenant_id,
        ...$overrides,
    ]);
}

test('lista solo los servicios de tercero de calibracion del tenant', function () {
    $calibracion = crearCalibracion($this->bahia);
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);

    $servicio = ServicioTercero::create([
        'orden_trabajo_id' => $calibracion->orden_trabajo_id,
        'tipo_servicio' => 'CALIBRACION',
        'empresa_tercero_id' => $empresa->id,
        'tenant_id' => $this->tenant->id,
    ]);
    ServicioTercero::create([
        'orden_trabajo_id' => $calibracion->orden_trabajo_id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'empresa_tercero_id' => $empresa->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('calibraciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('serviciosTerceros', 1)
            ->where('serviciosTerceros.0.id', $servicio->id)
            ->where('serviciosTerceros.0.empresa_tercero_nombre', $empresa->nombre)
            ->has('empresasTerceras', 1));
});

test('lista solo las calibraciones del tenant', function () {
    $calibracion = crearCalibracion($this->bahia);
    $detalle = crearDetalleMedicion($calibracion);
    crearCalibracion(Bahia::factory()->create(), [], 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->get(route('calibraciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calibraciones/index')
            ->has('calibraciones', 1)
            ->where('calibraciones.0.id', $calibracion->id)
            ->where('calibraciones.0.fecha_calibracion', $calibracion->fecha_calibracion->toDateString())
            ->where('calibraciones.0.tecnico_nombre', $calibracion->tecnico->name)
            ->where('calibraciones.0.estado_calibracion', 'PENDIENTE')
            ->where('calibraciones.0.equipo.codigo', $calibracion->ordenTrabajo->equipoProgramacion->equipo->codigo)
            ->has('calibraciones.0.detalles_medicion', 1)
            ->where('calibraciones.0.detalles_medicion.0.id', $detalle->id)
            ->where('calibraciones.0.detalles_medicion.0.valor_referencia', '10.00')
            ->where('calibraciones.0.detalles_medicion.0.unidad_simbolo', $detalle->unidadMedida->simbolo)
            ->where('calibraciones.0.detalles_medicion.0.valor_instrumento', null));
});

test('actualiza una calibracion', function () {
    $calibracion = crearCalibracion($this->bahia);
    $nuevoTecnico = User::factory()->forTenant($this->tenant)->create();
    $laboratorio = Laboratorio::factory()->for($this->tenant)->create();
    $novedad = Novedad::create(['nombre' => 'Fuera de tolerancia', 'categoria' => 'CALIBRACION', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => '2026-11-05',
            'tecnico_id' => $nuevoTecnico->id,
            'laboratorio_id' => $laboratorio->id,
            'temperatura' => '21.50',
            'humedad' => '45.30',
            'ajustes_requeridos' => '1',
            'novedad_id' => $novedad->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('calibraciones.index'));

    expect($calibracion->refresh())
        ->fecha_calibracion->toDateString()->toBe('2026-11-05')
        ->tecnico_id->toBe($nuevoTecnico->id)
        ->laboratorio_id->toBe($laboratorio->id)
        ->temperatura->toBe('21.50')
        ->humedad->toBe('45.30')
        ->ajustes_requeridos->toBeTrue()
        ->novedad_id->toBe($novedad->id);
});

test('actualiza los resultados de los detalles de medicion de una calibracion y rechaza por exceder el emp', function () {
    $calibracion = crearCalibracion($this->bahia);
    $detalle = crearDetalleMedicion($calibracion, [
        'emp_porcentaje' => '5.00',
        'emp_porcentaje_positivo' => '5.00',
        'emp_porcentaje_negativo' => '-5.00',
    ]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
            'tecnico_id' => $calibracion->tecnico_id,
            'detalles' => [
                [
                    'id' => $detalle->id,
                    'valor_instrumento' => '9.00',
                    'emp_porcentaje' => '999.00',
                    'emp_porcentaje_positivo' => '999.00',
                    'emp_porcentaje_negativo' => '999.00',
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // error_encontrado = valor_referencia - valor_instrumento = 10.00 - 9.00
    // error_porcentaje = error_encontrado / valor_instrumento = 1.00 / 9.00
    // resultado = NO_APROBADO porque error_encontrado (1.00) > emp (0.50)
    // emp_porcentaje, emp_porcentaje_positivo y emp_porcentaje_negativo no cambian: son de
    // solo lectura, el servidor ignora lo que mande el cliente para esos tres campos.
    expect($detalle->refresh())
        ->valor_instrumento->toBe('9.00')
        ->error_encontrado->toBe('1.00')
        ->error_porcentaje->toBe('0.11')
        ->resultado_calibracion->toBe('NO_APROBADO')
        ->emp_porcentaje->toBe('5.00')
        ->emp_porcentaje_positivo->toBe('5.00')
        ->emp_porcentaje_negativo->toBe('-5.00');
});

test('aprueba el resultado cuando el error encontrado no supera el emp', function () {
    $calibracion = crearCalibracion($this->bahia);
    $detalle = crearDetalleMedicion($calibracion);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
            'tecnico_id' => $calibracion->tecnico_id,
            'detalles' => [
                ['id' => $detalle->id, 'valor_instrumento' => '9.60'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // error_encontrado = 10.00 - 9.60 = 0.40, que no supera el emp (0.50)
    expect($detalle->refresh())
        ->error_encontrado->toBe('0.40')
        ->resultado_calibracion->toBe('APROBADO');
});

test('deja error_encontrado y error_porcentaje en null cuando no se llena el valor del instrumento', function () {
    $calibracion = crearCalibracion($this->bahia);
    $detalle = crearDetalleMedicion($calibracion, [
        'valor_instrumento' => '9.00',
        'error_encontrado' => '1.00',
        'error_porcentaje' => '0.11',
    ]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
            'tecnico_id' => $calibracion->tecnico_id,
            'detalles' => [
                ['id' => $detalle->id],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($detalle->refresh())
        ->valor_instrumento->toBeNull()
        ->error_encontrado->toBeNull()
        ->error_porcentaje->toBeNull()
        ->resultado_calibracion->toBeNull();
});

test('rechaza un detalle de medicion que no pertenece a la calibracion al actualizar', function () {
    $calibracion = crearCalibracion($this->bahia, [], 'EQ-PROPIA');
    $otraCalibracion = crearCalibracion($this->bahia, [], 'EQ-OTRA');
    $detalleAjeno = crearDetalleMedicion($otraCalibracion);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
            'tecnico_id' => $calibracion->tecnico_id,
            'detalles' => [
                ['id' => $detalleAjeno->id, 'valor_instrumento' => '10.05'],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.id');
});

test('desmarca ajustes_requeridos cuando no se envia en la actualizacion', function () {
    $calibracion = crearCalibracion($this->bahia, ['ajustes_requeridos' => true]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
            'tecnico_id' => $calibracion->tecnico_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($calibracion->refresh()->ajustes_requeridos)->toBeFalse();
});

test('elimina una calibracion con borrado suave', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->delete(route('calibraciones.destroy', $calibracion))
        ->assertRedirect(route('calibraciones.index'));

    expect(Calibracion::find($calibracion->id))->toBeNull()
        ->and(Calibracion::withTrashed()->find($calibracion->id))->not->toBeNull();
});

test('inicia una calibracion y abre un tiempo_servicio sin fin', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.iniciar', $calibracion))
        ->assertRedirect();

    $tiempoServicio = TiempoServicio::where('orden_trabajo_id', $calibracion->orden_trabajo_id)->firstOrFail();

    expect($tiempoServicio)
        ->tipo_servicio->toBe('CALIBRACION')
        ->estado_tiempo->toBe('INICIO')
        ->es_tercero->toBeFalse()
        ->fin->toBeNull()
        ->duracion->toBeNull();
    expect($tiempoServicio->inicio->diffInSeconds(now()))->toBeLessThan(5);
});

test('no permite iniciar una calibracion de otro tenant', function () {
    $ajeno = crearCalibracion(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->post(route('calibraciones.iniciar', $ajeno))
        ->assertForbidden();

    expect(TiempoServicio::where('orden_trabajo_id', $ajeno->orden_trabajo_id)->count())->toBe(0);
});

test('un usuario sin permiso de editar calibraciones no puede iniciarlas', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($recepcion)
        ->post(route('calibraciones.iniciar', $calibracion))
        ->assertForbidden();

    expect(TiempoServicio::where('orden_trabajo_id', $calibracion->orden_trabajo_id)->count())->toBe(0);
});

test('finaliza una calibracion, cierra el tiempo_servicio y marca la orden de trabajo', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.iniciar', $calibracion));

    $tiempoServicio = TiempoServicio::where('orden_trabajo_id', $calibracion->orden_trabajo_id)->firstOrFail();
    $tiempoServicio->update(['inicio' => now()->subMinutes(5)]);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
            'firmado' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('calibraciones.index'));

    expect($calibracion->refresh())
        ->estado_calibracion->toBe('FINALIZADO')
        ->firmado->toBeTrue();
    expect($calibracion->ordenTrabajo->refresh()->calibracion_finalizado)->toBeTrue();

    expect($tiempoServicio->refresh())
        ->estado_tiempo->toBe('FIN')
        ->duracion->toBe('00:05:00');
    expect($tiempoServicio->fin)->not->toBeNull();

    // Crea el despacho de la orden de trabajo, sin autorizar todavía, listo para
    // "Agendar Despachos" (ver OrdenTrabajoController::despachosListos()).
    $despacho = Despacho::where('orden_trabajo_id', $calibracion->orden_trabajo_id)->firstOrFail();

    expect($despacho)
        ->entrega_autorizada->toBeFalse()
        ->tecnico_entrega_id->toBeNull();
});

test('desmarca firmado cuando no se envia al finalizar', function () {
    $calibracion = crearCalibracion($this->bahia, ['firmado' => true]);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($calibracion->refresh()->firmado)->toBeFalse();
});

test('devuelve una calibracion a mantenimiento sin marcar la orden de trabajo como finalizada', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.iniciar', $calibracion));

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'DEVOLVER_MANTENIMIENTO',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($calibracion->refresh()->estado_calibracion)->toBe('DEVOLVER_MANTENIMIENTO');
    expect($calibracion->ordenTrabajo->refresh()->calibracion_finalizado)->toBeFalse();

    $tiempoServicio = TiempoServicio::where('orden_trabajo_id', $calibracion->orden_trabajo_id)->firstOrFail();
    expect($tiempoServicio->estado_tiempo)->toBe('FIN');

    // Crea una nueva orden de trabajo para el mismo equipo, sin agendar todavía, para
    // que reaparezca en "Agendar Mantenimiento" (ver OrdenTrabajoController::equiposListos()).
    $nuevaOrden = OrdenTrabajo::where('equipo_programacion_id', $calibracion->ordenTrabajo->equipo_programacion_id)
        ->where('id', '!=', $calibracion->orden_trabajo_id)
        ->firstOrFail();

    expect($nuevaOrden)
        ->listo_para_mantenimiento->toBeFalse()
        ->mantenimiento_finalizado->toBeFalse()
        ->devolucion->toBeTrue()
        ->estado->toBe('EN_BAHIA');

    expect(Despacho::where('orden_trabajo_id', $calibracion->orden_trabajo_id)->count())->toBe(0);
});

test('no crea una nueva orden de trabajo al finalizar una calibracion', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
        ])
        ->assertSessionHasNoErrors();

    expect(OrdenTrabajo::where('equipo_programacion_id', $calibracion->ordenTrabajo->equipo_programacion_id)->count())->toBe(1);
});

test('no permite finalizar una calibracion de otro tenant', function () {
    $ajeno = crearCalibracion(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $ajeno), [
            'estado_calibracion' => 'FINALIZADO',
        ])
        ->assertForbidden();

    expect($ajeno->refresh()->estado_calibracion)->not->toBe('FINALIZADO');
});

test('exige el estado de la calibracion al finalizar', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [])
        ->assertSessionHasErrors('estado_calibracion');
});

test('un usuario sin permiso de editar calibraciones no puede finalizarlas', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($recepcion)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
        ])
        ->assertForbidden();

    expect($calibracion->refresh()->estado_calibracion)->not->toBe('FINALIZADO');
});

test('no permite actualizar ni eliminar calibraciones de otro tenant', function () {
    $ajeno = crearCalibracion(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $ajeno), [
            'fecha_calibracion' => $ajeno->fecha_calibracion->toDateString(),
            'tecnico_id' => $ajeno->tecnico_id,
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('calibraciones.destroy', $ajeno))
        ->assertForbidden();

    expect(Calibracion::find($ajeno->id))->not->toBeNull();
});

test('exige fecha y tecnico al actualizar', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [])
        ->assertSessionHasErrors(['fecha_calibracion', 'tecnico_id']);
});

test('rechaza un tecnico que no pertenece al tenant al actualizar una calibracion', function () {
    $calibracion = crearCalibracion($this->bahia);
    $tecnicoAjeno = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
            'tecnico_id' => $tecnicoAjeno->id,
        ])
        ->assertSessionHasErrors('tecnico_id');
});

test('un tecnico con permiso de editar calibraciones puede verlas y actualizarlas, pero no eliminarlas', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($tecnico)
        ->get(route('calibraciones.index'))
        ->assertOk();

    $this->actingAs($tecnico)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
            'tecnico_id' => $calibracion->tecnico_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($tecnico)
        ->delete(route('calibraciones.destroy', $calibracion))
        ->assertForbidden();
});

test('un usuario de recepcion puede ver las calibraciones pero no editarlas ni eliminarlas', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($recepcion)
        ->get(route('calibraciones.index'))
        ->assertOk();

    $this->actingAs($recepcion)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => $calibracion->fecha_calibracion->toDateString(),
            'tecnico_id' => $calibracion->tecnico_id,
        ])
        ->assertForbidden();

    $this->actingAs($recepcion)
        ->delete(route('calibraciones.destroy', $calibracion))
        ->assertForbidden();
});

test('un invitado no puede ver las calibraciones', function () {
    $this->get(route('calibraciones.index'))
        ->assertRedirect(route('login'));
});
