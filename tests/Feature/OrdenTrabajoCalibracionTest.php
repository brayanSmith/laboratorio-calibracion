<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Calibracion;
use App\Models\DetalleMedicionAlcance;
use App\Models\DetalleMedicionCalibracion;
use App\Models\EmpresaTercero;
use App\Models\EquipoEspecificacionTecnica;
use App\Models\MedicionAlcance;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\ServicioTercero;
use App\Models\Tenant;
use App\Models\TipoMagnitud;
use App\Models\UnidadMedida;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    $this->novedadCalibracion = Novedad::create(['nombre' => 'Desviación detectada', 'categoria' => 'CALIBRACION', 'tenant_id' => $this->tenant->id]);
});

/** Orden de trabajo con el mantenimiento ya finalizado, lista para agendar
 * calibración (ver crearEquipoRecibido() en OrdenTrabajoControllerTest.php). */
function crearOrdenListaParaCalibracion(Bahia $bahia, string $codigo = 'EQ-5001'): OrdenTrabajo
{
    $programacion = crearEquipoRecibido($bahia, $codigo);

    return OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'mantenimiento_finalizado' => true,
        'tenant_id' => $bahia->tenant_id,
    ]);
}

test('lista las ordenes con mantenimiento finalizado para agendar calibracion', function () {
    $lista = crearOrdenListaParaCalibracion($this->bahia, 'EQ-LISTO');

    $sinFinalizar = crearOrdenListaParaCalibracion($this->bahia, 'EQ-SIN-FINALIZAR');
    $sinFinalizar->update(['mantenimiento_finalizado' => false]);

    $yaFinalizada = crearOrdenListaParaCalibracion($this->bahia, 'EQ-YA-FINALIZADA');
    $yaFinalizada->update(['calibracion_finalizado' => true]);

    $yaAgendada = crearOrdenListaParaCalibracion($this->bahia, 'EQ-YA-AGENDADA');
    $yaAgendada->update(['listo_para_calibracion' => true]);

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos-calibracion'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $lista->id)
        ->assertJsonPath('0.equipo.codigo', 'EQ-LISTO');
});

test('no incluye ordenes de otro tenant en la lista para agendar calibracion', function () {
    $ajena = Bahia::factory()->create();
    crearOrdenListaParaCalibracion($ajena, 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.equipos-listos-calibracion'))
        ->assertOk()
        ->assertJsonCount(0);
});

test('agenda calibracion con un tecnico propio y crea la calibracion con la novedad elegida', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'calibracion_asignado_tercero' => '0',
                    'tecnico_id' => $this->admin->id,
                    'novedad_id' => $this->novedadCalibracion->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($orden->refresh())
        ->listo_para_calibracion->toBeTrue()
        ->calibracion_asignado_tercero->toBeFalse();

    $calibracion = Calibracion::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($calibracion)
        ->tecnico_id->toBe($this->admin->id)
        ->novedad_id->toBe($this->novedadCalibracion->id)
        ->estado_calibracion->toBe('PENDIENTE')
        ->laboratorio_id->toBeNull()
        ->solicitante_id->toBeNull()
        ->procedimiento_id->toBeNull();

    expect(ServicioTercero::where('orden_trabajo_id', $orden->id)->count())->toBe(0);
});

test('agenda calibracion y crea un detalle_medicion_calibracion por cada detalle del alcance del equipo', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);
    $equipo = $orden->equipoProgramacion->equipo;

    EquipoEspecificacionTecnica::create([
        'equipo_id' => $equipo->id,
        'tipo_magnitud_id' => TipoMagnitud::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'unidad_medida_id' => UnidadMedida::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'alcance_indicacion' => '0 - 100 bar',
        'precision' => '±0.1',
        'resolucion' => '0.01',
        'tenant_id' => $this->tenant->id,
    ]);

    $medicionAlcance = MedicionAlcance::factory()->create([
        'tipo_equipo_id' => $equipo->tipo_equipo_id,
        'alcance_indicacion' => '0 - 100 bar',
        'tenant_id' => $this->tenant->id,
    ]);

    $detalleBajo = DetalleMedicionAlcance::factory()->for($medicionAlcance)->create([
        'tenant_id' => $this->tenant->id,
        'valor_instrumento' => 10,
        'emp' => 0.5,
        'incertidumbre' => 0.1,
    ]);
    $detalleAlto = DetalleMedicionAlcance::factory()->for($medicionAlcance)->create([
        'tenant_id' => $this->tenant->id,
        'valor_instrumento' => 50,
        'emp' => 0.6,
        'incertidumbre' => 0.2,
    ]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $calibracion = Calibracion::where('orden_trabajo_id', $orden->id)->firstOrFail();
    $detalles = DetalleMedicionCalibracion::where('calibracion_id', $calibracion->id)
        ->orderBy('detalle_medicion_alcance_id')
        ->get();

    expect($detalles)->toHaveCount(2);

    // emp_porcentaje = emp / valor_referencia = 0.50 / 10.00
    // emp_porcentaje_negativo = emp_porcentaje - (emp_porcentaje * 2), su opuesto
    // emp_porcentaje_positivo = -emp_porcentaje_negativo, es decir, el propio emp_porcentaje
    expect($detalles[0])
        ->detalle_medicion_alcance_id->toBe($detalleBajo->id)
        ->valor_referencia->toBe('10.00')
        ->unidad_medida_id->toBe($detalleBajo->unidad_medida_id)
        ->emp->toBe('0.50')
        ->incertidumbre->toBe('0.10')
        ->emp_porcentaje->toBe('0.05')
        ->emp_porcentaje_positivo->toBe('0.05')
        ->emp_porcentaje_negativo->toBe('-0.05')
        ->valor_instrumento->toBeNull()
        ->error_encontrado->toBeNull()
        ->error_porcentaje->toBeNull()
        ->resultado_calibracion->toBeNull();

    // emp_porcentaje = 0.60 / 50.00
    expect($detalles[1])
        ->detalle_medicion_alcance_id->toBe($detalleAlto->id)
        ->valor_referencia->toBe('50.00')
        ->emp_porcentaje->toBe('0.01')
        ->emp_porcentaje_positivo->toBe('0.01')
        ->emp_porcentaje_negativo->toBe('-0.01');
});

test('agenda calibracion sin crear detalles de medicion si el equipo no tiene especificacion tecnica', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $calibracion = Calibracion::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect(DetalleMedicionCalibracion::where('calibracion_id', $calibracion->id)->count())->toBe(0);
});

test('agenda calibracion con tecnico propio sin novedad, dejando el resto para cuando se realice', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $calibracion = Calibracion::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($calibracion)
        ->tecnico_id->toBe($this->admin->id)
        ->novedad_id->toBeNull()
        ->estado_calibracion->toBe('PENDIENTE');
});

test('agenda calibracion asignada a un tercero y crea el servicio_tercero con la empresa elegida', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'calibracion_asignado_tercero' => '1',
                    'empresa_tercero_id' => $empresa->id,
                ],
            ],
        ])
        ->assertRedirect();

    expect($orden->refresh())->calibracion_asignado_tercero->toBeTrue();

    $servicioTercero = ServicioTercero::where('orden_trabajo_id', $orden->id)->firstOrFail();

    expect($servicioTercero)
        ->tipo_servicio->toBe('CALIBRACION')
        ->empresa_tercero_id->toBe($empresa->id);

    expect(Calibracion::where('orden_trabajo_id', $orden->id)->count())->toBe(0);
});

test('no agenda una orden que no se marco como lista', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => ['listo_para_calibracion' => '0'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($orden->refresh()->listo_para_calibracion)->toBeFalse();
    expect(Calibracion::where('orden_trabajo_id', $orden->id)->count())->toBe(0);
});

test('exige tecnico al agendar calibracion con tecnico propio', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => ['listo_para_calibracion' => '1'],
            ],
        ])
        ->assertSessionHasErrors('ordenes_listas.0.tecnico_id');

    expect($orden->refresh()->listo_para_calibracion)->toBeFalse();
});

test('exige la empresa tercero al agendar calibracion asignada a un tercero', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'calibracion_asignado_tercero' => '1',
                ],
            ],
        ])
        ->assertSessionHasErrors('ordenes_listas.0.empresa_tercero_id');

    expect($orden->refresh()->listo_para_calibracion)->toBeFalse();
});

test('rechaza una orden que no pertenece al tenant al agendar calibracion', function () {
    $ajena = Bahia::factory()->create();
    $ordenAjena = crearOrdenListaParaCalibracion($ajena);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $ordenAjena->id => ['listo_para_calibracion' => '1'],
            ],
        ])
        ->assertSessionHasErrors('ordenes_listas.0.id');
});

test('rechaza una orden sin el mantenimiento finalizado al agendar calibracion', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);
    $orden->update(['mantenimiento_finalizado' => false]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => ['listo_para_calibracion' => '1'],
            ],
        ])
        ->assertSessionHasErrors('ordenes_listas.0.id');
});

test('un usuario sin permiso de editar equipos no puede agendar calibracion, pero si puede listarlas', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($recepcion)
        ->getJson(route('orden-trabajos.equipos-listos-calibracion'))
        ->assertOk()
        ->assertJsonCount(1);

    $this->actingAs($recepcion)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'tecnico_id' => $this->admin->id,
                ],
            ],
        ])
        ->assertForbidden();

    expect($orden->refresh()->listo_para_calibracion)->toBeFalse();
});

test('un invitado no puede agendar calibracion', function () {
    $this->post(route('orden-trabajos.store-calibracion'), ['ordenes_listas' => []])
        ->assertRedirect(route('login'));
});
