<?php

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\AlcancesMedicion\Models\DetalleMedicionAlcance;
use App\Modules\Calibraciones\Actions\GraficoErrorCalibracion;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Calibraciones\Models\DetalleMedicionCalibracion;
use App\Modules\Calibraciones\Models\Laboratorio;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Catalogos\Models\UnidadMedida;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\OrdenesTrabajo\Models\TiempoServicio;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('public');

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
        'temperatura' => 20,
        'humedad' => 50,
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
    $novedad = Novedad::create(['nombre' => 'Fuera de tolerancia', 'categoria' => 'CALIBRACION', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'temperatura' => '21.50',
            'humedad' => '45.30',
            'ajustes_requeridos' => '1',
            'novedad_id' => $novedad->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('calibraciones.index'));

    expect($calibracion->refresh())
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
            'temperatura' => 20,
            'humedad' => 50,
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

    // error_encontrado = |valor_referencia - valor_instrumento| = |10.00 - 9.00|
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
            'temperatura' => 20,
            'humedad' => 50,
            'detalles' => [
                ['id' => $detalle->id, 'valor_instrumento' => '9.60'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // error_encontrado = |10.00 - 9.60| = 0.40, que no supera el emp (0.50)
    expect($detalle->refresh())
        ->error_encontrado->toBe('0.40')
        ->resultado_calibracion->toBe('APROBADO');
});

test('el error encontrado es siempre positivo y el resultado considera el EMP en ambos sentidos', function (string $instrumento, string $errorEsperado, string $resultadoEsperado) {
    $calibracion = crearCalibracion($this->bahia);
    $detalle = crearDetalleMedicion($calibracion, ['valor_referencia' => 10, 'emp' => 0.5]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'temperatura' => 20,
            'humedad' => 50,
            'detalles' => [['id' => $detalle->id, 'valor_instrumento' => $instrumento]],
        ])
        ->assertSessionHasNoErrors();

    expect($detalle->refresh())
        ->error_encontrado->toBe($errorEsperado)
        ->resultado_calibracion->toBe($resultadoEsperado);
})->with([
    'instrumento por debajo, dentro del EMP' => ['9.60', '0.40', 'APROBADO'],
    'instrumento por encima, dentro del EMP' => ['10.40', '0.40', 'APROBADO'],
    'instrumento por debajo, fuera del EMP' => ['9.00', '1.00', 'NO_APROBADO'],
    'instrumento por encima, fuera del EMP' => ['12.00', '2.00', 'NO_APROBADO'],
]);

test('deja error_encontrado y error_porcentaje en null cuando no se llena el valor del instrumento', function () {
    $calibracion = crearCalibracion($this->bahia);
    $detalle = crearDetalleMedicion($calibracion, [
        'valor_instrumento' => '9.00',
        'error_encontrado' => '1.00',
        'error_porcentaje' => '0.11',
    ]);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'temperatura' => 20,
            'humedad' => 50,
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
            'temperatura' => 20,
            'humedad' => 50,
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
            'temperatura' => 20,
            'humedad' => 50,
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
            'resultado_calibracion' => '1',
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
            'resultado_calibracion' => '1',
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
            'resultado_calibracion' => '1',
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

test('la fecha y el tecnico no se pueden cambiar al actualizar una calibracion', function () {
    $calibracion = crearCalibracion($this->bahia);
    $fechaOriginal = $calibracion->fecha_calibracion->toDateString();
    $tecnicoOriginal = $calibracion->tecnico_id;

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [
            'fecha_calibracion' => '2030-01-01',
            'tecnico_id' => User::factory()->forTenant($this->tenant)->create()->id,
            'temperatura' => 20,
            'humedad' => 50,
        ])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh())
        ->fecha_calibracion->toDateString()->toBe($fechaOriginal)
        ->tecnico_id->toBe($tecnicoOriginal);
});

test('el solicitante es el area del equipo y no se puede cambiar', function () {
    $calibracion = crearCalibracion($this->bahia);
    $areaDelEquipo = $calibracion->ordenTrabajo->equipoProgramacion->equipo->area_id;
    $otraArea = crearArea($this->tenant);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), ['solicitante_id' => $otraArea->id, 'temperatura' => 20, 'humedad' => 50])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh()->solicitante_id)->toBe($areaDelEquipo);
});

test('el laboratorio es el principal del tenant y no se puede cambiar', function () {
    $calibracion = crearCalibracion($this->bahia);
    $principal = Laboratorio::factory()->for($this->tenant)->create(['principal' => true]);
    $otro = Laboratorio::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), ['laboratorio_id' => $otro->id, 'temperatura' => 20, 'humedad' => 50])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh()->laboratorio_id)->toBe($principal->id);
});

test('sin laboratorio principal la calibracion conserva su laboratorio', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), ['temperatura' => 20, 'humedad' => 50])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh()->laboratorio_id)->toBeNull();
});

test('el listado de calibraciones incluye el area del equipo y el laboratorio principal', function () {
    $calibracion = crearCalibracion($this->bahia);
    $calibracion->ordenTrabajo->equipoProgramacion->equipo->area->update(['nombre' => 'CRC', 'descripcion' => 'Centro de Reparación de Componentes']);
    $principal = Laboratorio::factory()->for($this->tenant)->create(['principal' => true]);

    $this->actingAs($this->admin)
        ->get(route('calibraciones.index'))
        ->assertInertia(fn ($page) => $page
            ->where('calibraciones.0.equipo.area.etiqueta', 'CRC - Centro de Reparación de Componentes')
            ->where('laboratorioPrincipal.id', $principal->id)
            ->where('laboratorioPrincipal.nombre', $principal->nombre));
});

test('un tecnico con permiso de editar calibraciones puede verlas y actualizarlas, pero no eliminarlas', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $calibracion = crearCalibracion($this->bahia, ['tecnico_id' => $tecnico->id]);

    $this->actingAs($tecnico)
        ->get(route('calibraciones.index'))
        ->assertOk();

    $this->actingAs($tecnico)
        ->put(route('calibraciones.update', $calibracion), [
            'temperatura' => 20,
            'humedad' => 50,
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
            'temperatura' => 20,
            'humedad' => 50,
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

test('al iniciar una calibracion su estado pasa a EN_PROCESO', function () {
    $calibracion = crearCalibracion($this->bahia);

    expect($calibracion->estado_calibracion)->toBe('PENDIENTE');

    $this->actingAs($this->admin)
        ->post(route('calibraciones.iniciar', $calibracion))
        ->assertRedirect();

    expect($calibracion->refresh()->estado_calibracion)->toBe('EN_PROCESO');
});

/** Calibración con dos puntos de medición ya medidos y aprobados. */
function crearCalibracionMedida(Bahia $bahia, array $overrides = []): Calibracion
{
    $calibracion = crearCalibracion($bahia, $overrides);

    foreach ([[5, 1.2, 2.0, -2.0], [10, 0.4, 2.0, -2.0]] as [$referencia, $error, $empPositivo, $empNegativo]) {
        crearDetalleMedicion($calibracion, [
            'valor_referencia' => $referencia,
            'valor_instrumento' => $referencia - 0.1,
            'error_encontrado' => 0.1,
            'error_porcentaje' => $error,
            'emp_porcentaje' => $empPositivo,
            'emp_porcentaje_positivo' => $empPositivo,
            'emp_porcentaje_negativo' => $empNegativo,
            'resultado_calibracion' => 'APROBADO',
        ]);
    }

    return $calibracion->refresh();
}

test('al finalizar una calibracion se genera y guarda su certificado PDF', function () {
    $calibracion = crearCalibracionMedida($this->bahia);

    $this->actingAs($this->admin)->post(route('calibraciones.iniciar', $calibracion));
    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'FINALIZADO', 'resultado_calibracion' => '1', 'firmado' => '1'])
        ->assertSessionHasNoErrors();

    $ruta = $calibracion->refresh()->pdf_certificado;

    expect($ruta)->toBe("certificados/calibraciones/{$this->tenant->id}/calibracion-{$calibracion->id}.pdf");
    Storage::disk('public')->assertExists($ruta);
    expect(Storage::disk('public')->get($ruta))->toStartWith('%PDF');
});

test('una calibracion devuelta a mantenimiento no genera certificado', function () {
    $calibracion = crearCalibracionMedida($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'DEVOLVER_MANTENIMIENTO'])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh()->pdf_certificado)->toBeNull();
});

test('muestra el certificado de una calibracion finalizada y lo regenera si falta el archivo', function () {
    $calibracion = crearCalibracionMedida($this->bahia, ['estado_calibracion' => 'FINALIZADO', 'pdf_certificado' => 'certificados/inexistente.pdf']);

    $this->actingAs($this->admin)
        ->get(route('calibraciones.certificado', $calibracion))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename=certificado-calibracion-'.$calibracion->ordenTrabajo->codigo.'.pdf');

    Storage::disk('public')->assertExists($calibracion->refresh()->pdf_certificado);
    expect($calibracion->pdf_certificado)->not->toBe('certificados/inexistente.pdf');
});

test('no permite ver el certificado de una calibracion sin finalizar ni de otro tenant', function () {
    $sinFinalizar = crearCalibracion($this->bahia);
    $ajena = crearCalibracion(Bahia::factory()->create(), ['estado_calibracion' => 'FINALIZADO'], 'EQ-AJENA');

    $this->actingAs($this->admin)->get(route('calibraciones.certificado', $sinFinalizar))->assertNotFound();
    $this->actingAs($this->admin)->get(route('calibraciones.certificado', $ajena))->assertNotFound();
});

test('actualizar el certificado reemplaza el archivo guardado', function () {
    $calibracion = crearCalibracionMedida($this->bahia, ['estado_calibracion' => 'FINALIZADO']);
    Storage::disk('public')->put('certificados/viejo.pdf', 'viejo');
    $calibracion->update(['pdf_certificado' => 'certificados/viejo.pdf']);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.actualizar-certificado', $calibracion))
        ->assertRedirect();

    $calibracion->refresh();

    expect($calibracion->pdf_certificado)->not->toBe('certificados/viejo.pdf');
    Storage::disk('public')->assertExists($calibracion->pdf_certificado);
    Storage::disk('public')->assertMissing('certificados/viejo.pdf');
});

test('no permite actualizar el certificado de una calibracion sin finalizar, de otro tenant ni sin permiso', function () {
    $sinFinalizar = crearCalibracion($this->bahia);
    $ajena = crearCalibracion(Bahia::factory()->create(), ['estado_calibracion' => 'FINALIZADO'], 'EQ-AJENA');
    $finalizada = crearCalibracion($this->bahia, ['estado_calibracion' => 'FINALIZADO'], 'EQ-FIN');
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->post(route('calibraciones.actualizar-certificado', $sinFinalizar))->assertNotFound();
    $this->actingAs($this->admin)->post(route('calibraciones.actualizar-certificado', $ajena))->assertForbidden();
    $this->actingAs($sinRol)->post(route('calibraciones.actualizar-certificado', $finalizada))->assertForbidden();
});

test('un usuario sin permiso de ver calibraciones no puede ver el certificado', function () {
    $calibracion = crearCalibracion($this->bahia, ['estado_calibracion' => 'FINALIZADO']);
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('calibraciones.certificado', $calibracion))->assertForbidden();
});

test('el grafico dibuja EMP +, EMP - y Error y no se genera sin puntos', function () {
    $sinMediciones = crearCalibracion($this->bahia, [], 'EQ-SIN-MED');
    $medida = crearCalibracionMedida($this->bahia);
    $grafico = app(GraficoErrorCalibracion::class);

    expect($grafico->handle($sinMediciones->load('detalleMedicionCalibracion')))->toBeNull();

    $svg = $grafico->handle($medida->load('detalleMedicionCalibracion'));

    expect($svg)->toStartWith('<svg')
        ->and(substr_count($svg, '<circle'))->toBe(2)
        // Solo el error es una polilínea; EMP + y EMP - son líneas punteadas de borde a borde.
        ->and(substr_count($svg, '<polyline'))->toBe(1)
        ->and(substr_count($svg, 'stroke-dasharray="5,3"'))->toBe(2 + 2)
        // Cuadrícula: 7 líneas horizontales y una vertical por cada punto medido.
        ->and(substr_count($svg, 'stroke="#e5e5e5" stroke-width="0.6"'))->toBe(7 + 2)
        // El eje vertical llega hasta el EMP (2.00) con su margen, sin símbolo de porcentaje.
        ->and($svg)->toContain('>Error / EMP</text>')
        ->and($svg)->not->toContain('%</text>');
});

test('el certificado incluye la firma del tecnico solo si la calibracion quedo firmada', function (bool $firmado, bool $apareceFirma) {
    $calibracion = crearCalibracion($this->bahia, ['estado_calibracion' => 'FINALIZADO', 'firmado' => $firmado]);
    Storage::disk('public')->put('firmas-usuarios/firma.png', UploadedFile::fake()->image('firma.png', 120, 60)->getContent());
    $calibracion->tecnico->forceFill(['firma' => 'firmas-usuarios/firma.png'])->save();

    $this->actingAs($this->admin)->get(route('calibraciones.certificado', $calibracion))->assertOk();
    $pdf = Storage::disk('public')->get($calibracion->refresh()->pdf_certificado);

    expect(str_contains($pdf, '/Subtype /Image'))->toBe($apareceFirma);
})->with([
    'firmada' => [true, true],
    'sin firmar' => [false, false],
]);

test('el grafico omite los puntos que todavia no tienen valor del instrumento', function () {
    $calibracion = crearCalibracion($this->bahia);
    $punto = ['emp_porcentaje' => 5, 'emp_porcentaje_positivo' => 5, 'emp_porcentaje_negativo' => -5];
    crearDetalleMedicion($calibracion, ['valor_referencia' => 10, 'valor_instrumento' => 9.8, 'error_encontrado' => 0.2, ...$punto]);
    crearDetalleMedicion($calibracion, ['valor_referencia' => 20, ...$punto]);

    $grafico = app(GraficoErrorCalibracion::class);
    $svg = $grafico->handle($calibracion->load('detalleMedicionCalibracion'));

    // Solo el punto con valor del instrumento: el error queda reducido a un círculo.
    expect($svg)->toStartWith('<svg')->and(substr_count($svg, '<circle'))->toBe(1);

    $sinMedir = crearCalibracion($this->bahia, [], 'EQ-SIN-INSTR');
    crearDetalleMedicion($sinMedir, ['valor_referencia' => 10, ...$punto]);

    expect($grafico->handle($sinMedir->load('detalleMedicionCalibracion')))->toBeNull();
});

test('el grafico usa el emp maximo como rango positivo y negativo y colorea el area entre ambos', function () {
    $calibracion = crearCalibracion($this->bahia);
    foreach ([['valor_referencia' => 50, 'emp' => 5], ['valor_referencia' => 100, 'emp' => 15]] as $punto) {
        crearDetalleMedicion($calibracion, [
            ...$punto,
            'valor_instrumento' => $punto['valor_referencia'],
            'error_encontrado' => 1,
        ]);
    }

    $svg = app(GraficoErrorCalibracion::class)->handle($calibracion->load('detalleMedicionCalibracion'));

    // Con EMP 5 y 15 el rango es ±15 en todos los puntos (con su margen del 10 %: el eje llega a ±18).
    expect($svg)->toContain('>18</text>')
        ->and($svg)->toContain('>-18</text>')
        ->and($svg)->toContain('fill="#16a34a" fill-opacity="0.12"')
        ->and($svg)->toContain('>EMP +</text>')
        ->and($svg)->toContain('>EMP -</text>')
        ->and($svg)->toContain('>Error</text>');
});

test('el area del rango del grafico se pinta de rojo si algun error supera el emp', function () {
    $calibracion = crearCalibracion($this->bahia);
    crearDetalleMedicion($calibracion, ['valor_referencia' => 50, 'valor_instrumento' => 50, 'error_encontrado' => 1, 'emp' => 5]);
    crearDetalleMedicion($calibracion, ['valor_referencia' => 100, 'valor_instrumento' => 100, 'error_encontrado' => 8, 'emp' => 5]);

    $svg = app(GraficoErrorCalibracion::class)->handle($calibracion->load('detalleMedicionCalibracion'));

    expect($svg)->toContain('fill="#dc2626" fill-opacity="0.12"')
        ->and($svg)->not->toContain('#16a34a');
});

test('exige temperatura y humedad al actualizar una calibracion', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('calibraciones.update', $calibracion), [])
        ->assertSessionHasErrors(['temperatura', 'humedad']);
});

test('no permite finalizar una calibracion sin temperatura ni humedad registradas', function () {
    $calibracion = crearCalibracion($this->bahia, ['temperatura' => null, 'humedad' => null]);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'FINALIZADO'])
        ->assertSessionHasErrors(['temperatura', 'humedad']);

    expect($calibracion->refresh()->estado_calibracion)->not->toBe('FINALIZADO');
});

test('no permite finalizar mientras algun resultado de la medicion no tenga valor del instrumento', function () {
    $calibracion = crearCalibracion($this->bahia);
    crearDetalleMedicion($calibracion, ['resultado_calibracion' => 'APROBADO']);
    crearDetalleMedicion($calibracion, ['resultado_calibracion' => null]);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'FINALIZADO'])
        ->assertSessionHasErrors('detalles');

    expect($calibracion->refresh())
        ->estado_calibracion->not->toBe('FINALIZADO')
        ->resultado_calibracion->toBeNull();
});

test('el resultado de la calibracion se calcula a partir de los resultados de la medicion', function (array $resultados, bool $esperado) {
    $calibracion = crearCalibracion($this->bahia);

    foreach ($resultados as $resultado) {
        crearDetalleMedicion($calibracion, ['resultado_calibracion' => $resultado]);
    }

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
            'resultado_calibracion' => $esperado ? '0' : '1',
        ])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh()->resultado_calibracion)->toBe($esperado);
})->with([
    'todos aprobados' => [['APROBADO', 'APROBADO'], true],
    'alguno no aprobado' => [['APROBADO', 'NO_APROBADO', 'APROBADO'], false],
    'todos no aprobados' => [['NO_APROBADO', 'NO_APROBADO'], false],
    'sin resultados de medicion' => [[], true],
]);

test('finalizar guarda el formulario y finaliza en una sola peticion', function () {
    $calibracion = crearCalibracion($this->bahia, ['temperatura' => null, 'humedad' => null]);
    $aprobado = crearDetalleMedicion($calibracion);
    $rechazado = crearDetalleMedicion($calibracion);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
            'firmado' => '1',
            'temperatura' => '21.5',
            'humedad' => '40',
            'ajustes_requeridos' => '1',
            'detalles' => [
                ['id' => $aprobado->id, 'valor_instrumento' => '10'],
                ['id' => $rechazado->id, 'valor_instrumento' => '5'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('calibraciones.index'));

    expect($calibracion->refresh())
        ->estado_calibracion->toBe('FINALIZADO')
        ->temperatura->toBe('21.50')
        ->humedad->toBe('40.00')
        ->ajustes_requeridos->toBeTrue()
        ->resultado_calibracion->toBeFalse()
        ->and($aprobado->refresh())
        ->valor_instrumento->toBe('10.00')
        ->resultado_calibracion->toBe('APROBADO')
        ->and($rechazado->refresh()->resultado_calibracion)->toBe('NO_APROBADO');
});

test('finalizar con el formulario aprueba cuando todos los valores enviados estan dentro del EMP', function () {
    $calibracion = crearCalibracion($this->bahia, ['temperatura' => null, 'humedad' => null]);
    $detalle = crearDetalleMedicion($calibracion);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
            'temperatura' => '20',
            'humedad' => '50',
            'detalles' => [['id' => $detalle->id, 'valor_instrumento' => '10']],
        ])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh()->resultado_calibracion)->toBeTrue();
});

test('finalizar con el formulario exige temperatura, humedad y valor del instrumento y no guarda nada si falla', function () {
    $calibracion = crearCalibracion($this->bahia, ['temperatura' => null, 'humedad' => null]);
    $detalle = crearDetalleMedicion($calibracion);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
            'temperatura' => '',
            'humedad' => '',
            'detalles' => [['id' => $detalle->id, 'valor_instrumento' => '']],
        ])
        ->assertSessionHasErrors(['temperatura', 'humedad', 'detalles.0.valor_instrumento']);

    expect($calibracion->refresh())
        ->estado_calibracion->not->toBe('FINALIZADO')
        ->temperatura->toBeNull()
        ->and($detalle->refresh()->valor_instrumento)->toBeNull();
});

test('devolver a mantenimiento con el formulario no exige temperatura ni valores del instrumento', function () {
    $calibracion = crearCalibracion($this->bahia, ['temperatura' => null, 'humedad' => null]);
    $detalle = crearDetalleMedicion($calibracion);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'DEVOLVER_MANTENIMIENTO',
            'temperatura' => '',
            'humedad' => '',
            'detalles' => [['id' => $detalle->id, 'valor_instrumento' => '']],
        ])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh())
        ->estado_calibracion->toBe('DEVOLVER_MANTENIMIENTO')
        ->resultado_calibracion->toBeNull();
});

test('finalizar no acepta un detalle que no pertenece a la calibracion', function () {
    $calibracion = crearCalibracion($this->bahia);
    $otra = crearCalibracion($this->bahia, [], 'EQ-6002');
    $ajeno = crearDetalleMedicion($otra);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), [
            'estado_calibracion' => 'FINALIZADO',
            'temperatura' => '20',
            'humedad' => '50',
            'detalles' => [['id' => $ajeno->id, 'valor_instrumento' => '10']],
        ])
        ->assertSessionHasErrors('detalles.0.id');

    expect($ajeno->refresh()->valor_instrumento)->toBeNull();
});

test('una calibracion devuelta a mantenimiento no necesita ni guarda resultado', function () {
    $calibracion = crearCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'DEVOLVER_MANTENIMIENTO', 'resultado_calibracion' => '1'])
        ->assertSessionHasNoErrors();

    expect($calibracion->refresh()->resultado_calibracion)->toBeNull();
});

test('el listado de calibraciones incluye su resultado', function () {
    crearCalibracion($this->bahia, ['estado_calibracion' => 'FINALIZADO', 'resultado_calibracion' => false]);

    $this->actingAs($this->admin)
        ->get(route('calibraciones.index'))
        ->assertInertia(fn ($page) => $page->where('calibraciones.0.resultado_calibracion', false));
});

test('iniciar, finalizar y ver el PDF de una calibracion dependen de su propio permiso, no del de editar', function () {
    $usuario = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    $usuario->syncRoles([]);
    $usuario->givePermissionTo([TenantPermission::CalibracionesEditar->value, TenantPermission::CalibracionesVerTodos->value]);
    $calibracion = crearCalibracionMedida($this->bahia, ['estado_calibracion' => 'FINALIZADO']);

    $this->actingAs($usuario)->post(route('calibraciones.iniciar', $calibracion))->assertForbidden();
    $this->actingAs($usuario)->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'FINALIZADO'])->assertForbidden();
    $this->actingAs($usuario)->get(route('calibraciones.certificado', $calibracion))->assertForbidden();

    $usuario->givePermissionTo([
        TenantPermission::CalibracionesIniciar->value,
        TenantPermission::CalibracionesPdf->value,
    ]);

    $this->actingAs($usuario)->get(route('calibraciones.certificado', $calibracion))->assertOk();
    $this->actingAs($usuario)->post(route('calibraciones.iniciar', $calibracion))->assertRedirect();
    $this->actingAs($usuario)->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'FINALIZADO'])->assertForbidden();
});
