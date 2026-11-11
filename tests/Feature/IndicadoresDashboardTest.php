<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Catalogos\Models\TipoMagnitud;
use App\Modules\Catalogos\Models\UnidadMedida;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\EquipoEspecificacionTecnica;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\OrdenesTrabajo\Models\TiempoServicio;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

function crearOrdenDeIndicador(EquipoProgramacion $programacion): OrdenTrabajo
{
    return OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $programacion->tenant_id,
    ]);
}

/**
 * Equipo recibido en el ingreso el día $recepcion y entregado en el despacho el día
 * $despacho (o aún sin entregar si $despacho es null).
 */
function crearCicloAtencion(Bahia $bahia, string $codigo, ?string $recepcion, ?string $despacho): OrdenTrabajo
{
    $programacion = crearEquipoRecibido($bahia, $codigo);
    $programacion->ingreso->update(['fecha_recepcion' => $recepcion]);
    $orden = crearOrdenDeIndicador($programacion);

    $registro = Despacho::create([
        'orden_trabajo_id' => $orden->id,
        'entrega_recibida' => $despacho !== null,
        'tenant_id' => $bahia->tenant_id,
    ]);
    $registro->forceFill(['fecha_despacho' => $despacho])->save();

    return $orden;
}

function registrarTiempoServicio(OrdenTrabajo $orden, string $tipoServicio, int $horas, bool $esTercero = false): void
{
    TiempoServicio::create([
        'orden_trabajo_id' => $orden->id,
        'tipo_servicio' => $tipoServicio,
        'inicio' => now()->subHours($horas),
        'fin' => now(),
        'duracion' => sprintf('%02d:00:00', $horas),
        'estado_tiempo' => 'FIN',
        'es_tercero' => $esTercero,
        'tenant_id' => $orden->tenant_id,
    ]);
}

test('los indicadores responden 403 sin su permiso', function (string $ruta) {
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::DashboardVer]);

    $this->actingAs($usuario)
        ->getJson(route($ruta, ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertForbidden();
})->with([
    'dashboard.indicadores.tiempos-atencion',
    'dashboard.indicadores.desempeno-tecnicos',
    'dashboard.indicadores.cumplimiento',
]);

test('los indicadores exigen un rango de fechas valido', function () {
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', ['desde' => '2026-10-31', 'hasta' => '2026-10-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hasta');
});

test('el dashboard envia las opciones de los indicadores solo a quien tiene alguno', function () {
    $sinIndicadores = usuarioConPermisos($this->tenant, [TenantPermission::DashboardVer]);
    $conUno = usuarioConPermisos($this->tenant, [TenantPermission::DashboardVer, TenantPermission::DashboardCumplimiento]);

    $this->actingAs($sinIndicadores)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('indicadores', null));

    $this->actingAs($conUno)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('indicadores.permisos.cumplimiento', true)
            ->where('indicadores.permisos.tiempos_atencion', false)
            ->missing('indicadores.areas'));
});

test('tiempos de atencion cuenta los despachos entregados en el rango y promedia los dias desde el ingreso', function () {
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id, 'nombre' => 'Metrología SAS']);

    crearCicloAtencion($this->bahia, 'EQ-PROPIO', '2026-10-01', '2026-10-03');
    $deTercero = crearCicloAtencion($this->bahia, 'EQ-TERCERO', '2026-10-02', '2026-10-12');
    ServicioTercero::create([
        'orden_trabajo_id' => $deTercero->id,
        'tipo_servicio' => 'CALIBRACION',
        'empresa_tercero_id' => $empresa->id,
        'tenant_id' => $this->tenant->id,
    ]);
    crearCicloAtencion($this->bahia, 'EQ-FUERA-RANGO', '2026-09-01', '2026-09-20');
    crearCicloAtencion($this->bahia, 'EQ-SIN-ENTREGAR', '2026-10-01', null);
    crearCicloAtencion($this->bahia, 'EQ-SIN-RECEPCION', null, '2026-10-05');

    $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', $rango))
        ->assertOk()
        ->assertJsonPath('resumen.cantidad', 3)
        ->assertJsonPath('resumen.con_tiempo', 2)
        ->assertJsonPath('resumen.promedio_dias', 6)
        ->assertJsonPath('resumen.minimo_dias', 2)
        ->assertJsonPath('resumen.maximo_dias', 10)
        ->assertJsonPath('equipos.0.equipo', 'EQ-TERCERO')
        ->assertJsonPath('equipos.0.laboratorio', 'Metrología SAS')
        ->assertJsonPath('equipos.1.laboratorio', 'Laboratorio propio')
        ->assertJsonPath('equipos.2.equipo', 'EQ-SIN-RECEPCION')
        ->assertJsonPath('equipos.2.dias', null);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', [...$rango, 'laboratorios' => ['propio']]))
        ->assertJsonCount(2, 'equipos')
        ->assertJsonPath('equipos.0.equipo', 'EQ-PROPIO');

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', [...$rango, 'laboratorios' => [$empresa->id]]))
        ->assertJsonCount(1, 'equipos')
        ->assertJsonPath('equipos.0.equipo', 'EQ-TERCERO');

    // Selección múltiple: cualquiera de los laboratorios elegidos.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', [...$rango, 'laboratorios' => ['propio', $empresa->id]]))
        ->assertJsonCount(3, 'equipos');

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', [...$rango, 'laboratorios' => ['otro']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('laboratorios.0');
});

test('tiempos de atencion filtra por el area del equipo y por la magnitud de su especificacion tecnica', function () {
    $presion = TipoMagnitud::factory()->create(['tenant_id' => $this->tenant->id, 'nombre' => 'Presión']);
    $otraArea = crearArea($this->tenant, 'Otra área');

    $conMagnitud = crearCicloAtencion($this->bahia, 'EQ-PRESION', '2026-10-01', '2026-10-03');
    EquipoEspecificacionTecnica::create([
        'equipo_id' => $conMagnitud->equipoProgramacion->equipo_id,
        'tipo_magnitud_id' => $presion->id,
        'unidad_medida_id' => UnidadMedida::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'alcance_indicacion' => '0 - 100 bar',
        'precision' => '±0.1',
        'resolucion' => '0.01',
        'tenant_id' => $this->tenant->id,
    ]);
    crearCicloAtencion(Bahia::factory()->for($otraArea)->create(), 'EQ-OTRA-AREA', '2026-10-01', '2026-10-05');

    $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', [...$rango, 'areas' => [$otraArea->id]]))
        ->assertOk()
        ->assertJsonCount(1, 'equipos')
        ->assertJsonPath('equipos.0.equipo', 'EQ-OTRA-AREA');

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', [...$rango, 'magnitudes' => [$presion->id]]))
        ->assertJsonCount(1, 'equipos')
        ->assertJsonPath('equipos.0.equipo', 'EQ-PRESION');

});

test('los filtros de tiempos de atencion son listas dependientes en su orden', function () {
    $presion = TipoMagnitud::factory()->create(['tenant_id' => $this->tenant->id, 'nombre' => 'Presión']);
    $otraArea = crearArea($this->tenant, 'Otra área');
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id, 'nombre' => 'Metrología SAS']);

    $conMagnitud = crearCicloAtencion($this->bahia, 'EQ-PRESION', '2026-10-01', '2026-10-03');
    EquipoEspecificacionTecnica::create([
        'equipo_id' => $conMagnitud->equipoProgramacion->equipo_id,
        'tipo_magnitud_id' => $presion->id,
        'unidad_medida_id' => UnidadMedida::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'alcance_indicacion' => '0 - 100 bar',
        'precision' => '±0.1',
        'resolucion' => '0.01',
        'tenant_id' => $this->tenant->id,
    ]);
    $deTercero = crearCicloAtencion(Bahia::factory()->for($otraArea)->create(), 'EQ-TERCERO', '2026-10-01', '2026-10-05');
    ServicioTercero::create([
        'orden_trabajo_id' => $deTercero->id,
        'tipo_servicio' => 'CALIBRACION',
        'empresa_tercero_id' => $empresa->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];

    // Sin filtros: cada lista tiene todo lo del periodo; el laboratorio propio va primero.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', $rango))
        ->assertJsonCount(2, 'opciones.areas')
        ->assertJsonPath('opciones.laboratorios.0.id', 'propio')
        ->assertJsonPath('opciones.laboratorios.1.nombre', 'Metrología SAS')
        ->assertJsonPath('opciones.magnitudes.0.nombre', 'Presión')
        ->assertJsonCount(2, 'opciones.tipos_equipo');

    // Con un área: las listas siguientes solo ofrecen lo de esa área; el área sigue completa.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', [...$rango, 'areas' => [$otraArea->id]]))
        ->assertJsonCount(2, 'opciones.areas')
        ->assertJsonCount(1, 'opciones.laboratorios')
        ->assertJsonPath('opciones.laboratorios.0.nombre', 'Metrología SAS')
        ->assertJsonCount(0, 'opciones.magnitudes')
        ->assertJsonCount(1, 'opciones.tipos_equipo');

    // Con el laboratorio propio: solo la magnitud y el tipo de sus equipos.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', [...$rango, 'laboratorios' => ['propio']]))
        ->assertJsonCount(1, 'equipos')
        ->assertJsonPath('opciones.magnitudes.0.id', $presion->id)
        ->assertJsonCount(1, 'opciones.tipos_equipo');
});

test('desempeno por tecnico cuenta los servicios terminados y promedia su tiempo registrado', function () {
    $tecnico = User::factory()->forTenant($this->tenant)->create(['name' => 'Ana Técnica']);
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach ([2, 4] as $indice => $horas) {
        $orden = crearOrdenDeIndicador(crearEquipoRecibido($this->bahia, "EQ-MANT-$indice"));
        Mantenimiento::create([
            'orden_trabajo_id' => $orden->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'fecha_mantenimiento' => '2026-10-05',
            'tecnico_id' => $tecnico->id,
            'estado_mantenimiento' => 'FINALIZADO',
            'fecha_realizacion' => '2026-10-05',
            'tenant_id' => $this->tenant->id,
        ]);
        registrarTiempoServicio($orden, 'MANTENIMIENTO', $horas);
    }

    $ordenCalibracion = crearOrdenDeIndicador(crearEquipoRecibido($this->bahia, 'EQ-CAL'));
    $presion = TipoMagnitud::factory()->create(['tenant_id' => $this->tenant->id]);
    EquipoEspecificacionTecnica::create([
        'equipo_id' => $ordenCalibracion->equipoProgramacion->equipo_id,
        'tipo_magnitud_id' => $presion->id,
        'unidad_medida_id' => UnidadMedida::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'alcance_indicacion' => '0 - 100 bar',
        'precision' => '±0.1',
        'resolucion' => '0.01',
        'tenant_id' => $this->tenant->id,
    ]);

    Calibracion::create([
        'orden_trabajo_id' => $ordenCalibracion->id,
        'fecha_calibracion' => '2026-10-06',
        'tecnico_id' => $tecnico->id,
        'estado_calibracion' => 'FINALIZADO',
        'fecha_realizacion' => '2026-10-06',
        'temperatura' => 20,
        'humedad' => 50,
        'tenant_id' => $this->tenant->id,
    ]);

    Mantenimiento::create([
        'orden_trabajo_id' => crearOrdenDeIndicador(crearEquipoRecibido($this->bahia, 'EQ-EN-PROCESO'))->id,
        'tipo_mantenimiento' => 'PREVENTIVO',
        'fecha_mantenimiento' => '2026-10-05',
        'tecnico_id' => $tecnico->id,
        'estado_mantenimiento' => 'EN_PROCESO',
        'tenant_id' => $this->tenant->id,
    ]);

    ServicioTercero::create([
        'orden_trabajo_id' => crearOrdenDeIndicador(crearEquipoRecibido($this->bahia, 'EQ-TERCERO'))->id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'empresa_tercero_id' => $empresa->id,
        'estado_servicio' => 'FINALIZADO',
        'fecha_realizacion' => '2026-10-07',
        'tenant_id' => $this->tenant->id,
    ]);

    $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', $rango))
        ->assertOk()
        ->assertJsonPath('totales.mantenimientos.cantidad', 3)
        ->assertJsonPath('totales.mantenimientos.promedio_horas', 3)
        ->assertJsonPath('totales.calibraciones.cantidad', 1)
        ->assertJsonPath('totales.calibraciones.promedio_horas', null)
        ->assertJsonCount(4, 'servicios')
        ->assertJsonPath('servicios.0.fecha', '2026-10-05')
        ->assertJsonPath('servicios.0.responsable_tipo', 'TECNICO')
        ->assertJsonPath('servicios.0.responsable_id', $tecnico->id)
        ->assertJsonPath('servicios.0.equipo', 'EQ-MANT-0')
        ->assertJsonPath('servicios.0.orden_trabajo', fn (?string $codigo) => $codigo !== null)
        ->assertJsonPath('responsables.0.responsable', 'Ana Técnica')
        ->assertJsonPath('responsables.0.mantenimientos.cantidad', 2)
        ->assertJsonPath('responsables.1.tipo', 'TERCERO')
        ->assertJsonCount(1, 'mensual')
        ->assertJsonPath('mensual.0.mes', '2026-10')
        ->assertJsonPath('mensual.0.mantenimientos.cantidad', 3)
        ->assertJsonPath('mensual.0.calibraciones.cantidad', 1);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'tecnicos' => [$tecnico->id]]))
        ->assertJsonCount(1, 'responsables')
        ->assertJsonPath('totales.mantenimientos.cantidad', 2);

    // Local: solo el laboratorio propio; tercero: solo las empresas terceras.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'origen' => 'LOCAL']))
        ->assertJsonCount(1, 'responsables')
        ->assertJsonPath('responsables.0.tipo', 'TECNICO')
        ->assertJsonPath('totales.mantenimientos.cantidad', 2);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'origen' => 'TERCERO']))
        ->assertJsonCount(1, 'responsables')
        ->assertJsonPath('responsables.0.tipo', 'TERCERO')
        ->assertJsonPath('objetivos_usuarios', []);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'origen' => 'OTRO']))
        ->assertJsonValidationErrors('origen');

    // Solo calibraciones: ni los mantenimientos propios ni los de terceros.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'servicios' => ['CALIBRACION']]))
        ->assertJsonPath('totales.mantenimientos.cantidad', 0)
        ->assertJsonPath('totales.calibraciones.cantidad', 1)
        ->assertJsonCount(1, 'responsables');

    // Por la magnitud del equipo: solo el equipo con esa especificación técnica.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'magnitudes' => [$presion->id]]))
        ->assertJsonPath('totales.mantenimientos.cantidad', 0)
        ->assertJsonPath('totales.calibraciones.cantidad', 1);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'servicios' => ['OTRO']]))
        ->assertJsonValidationErrors('servicios.0');

    // Listas dependientes: el laboratorio propio primero; una empresa tercera no tiene técnicos.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', $rango))
        ->assertJsonPath('opciones.laboratorios.0.id', 'propio')
        ->assertJsonCount(2, 'opciones.laboratorios')
        ->assertJsonCount(1, 'opciones.tecnicos')
        ->assertJsonPath('opciones.tecnicos.0.nombre', 'Ana Técnica');

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'laboratorios' => [$empresa->id]]))
        ->assertJsonCount(2, 'opciones.laboratorios')
        ->assertJsonCount(0, 'opciones.tecnicos')
        ->assertJsonPath('totales.mantenimientos.cantidad', 1)
        ->assertJsonPath('responsables.0.tipo', 'TERCERO');
});

test('desempeno por tecnico compara los totales con el periodo anterior del mismo largo', function () {
    $tecnico = User::factory()->forTenant($this->tenant)->create();

    foreach (['2026-09-30', '2026-10-05', '2026-10-20'] as $indice => $fecha) {
        $orden = crearOrdenDeIndicador(crearEquipoRecibido($this->bahia, "EQ-ANT-$indice"));
        Mantenimiento::create([
            'orden_trabajo_id' => $orden->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'fecha_mantenimiento' => $fecha,
            'tecnico_id' => $tecnico->id,
            'estado_mantenimiento' => 'FINALIZADO',
            'fecha_realizacion' => $fecha,
            'tenant_id' => $this->tenant->id,
        ]);
        registrarTiempoServicio($orden, 'MANTENIMIENTO', 2);
    }

    // Un mes completo: el anterior es el mes de antes, aunque tenga otro número de días.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk()
        ->assertJsonPath('totales.mantenimientos.cantidad', 2)
        ->assertJsonPath('periodo_anterior.desde', '2026-09-01')
        ->assertJsonPath('periodo_anterior.hasta', '2026-09-30')
        ->assertJsonPath('periodo_anterior.totales.mantenimientos.cantidad', 1)
        ->assertJsonPath('periodo_anterior.totales.mantenimientos.promedio_horas', 2)
        ->assertJsonPath('periodo_anterior.totales.calibraciones.cantidad', 0);

    // Un rango suelto: los mismos días justo antes.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', ['desde' => '2026-10-10', 'hasta' => '2026-10-24']))
        ->assertJsonPath('totales.mantenimientos.cantidad', 1)
        ->assertJsonPath('periodo_anterior.desde', '2026-09-25')
        ->assertJsonPath('periodo_anterior.hasta', '2026-10-09')
        ->assertJsonPath('periodo_anterior.totales.mantenimientos.cantidad', 2);
});

test('cumplimiento compara los equipos con preventivo programado contra los que tienen el servicio finalizado', function () {
    $atendido = crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-ATENDIDO'), ['fecha_proximo_servicio' => '2026-10-10']);
    crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-PENDIENTE-1'), ['fecha_proximo_servicio' => '2026-10-15']);
    crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-PENDIENTE-2'), ['fecha_proximo_servicio' => '2026-10-20']);
    crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-OTRO-MES'), ['fecha_proximo_servicio' => '2026-11-20']);
    crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-CORRECTIVO'), ['fecha_proximo_servicio' => '2026-10-10', 'tipo_mantenimiento' => 'CORRECTIVO']);

    Mantenimiento::create([
        'orden_trabajo_id' => crearOrdenDeIndicador($atendido)->id,
        'tipo_mantenimiento' => 'PREVENTIVO',
        'fecha_mantenimiento' => '2026-10-10',
        'tecnico_id' => $this->admin->id,
        'estado_mantenimiento' => 'FINALIZADO',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk()
        ->assertJsonPath('resumen.programados', 3)
        ->assertJsonPath('resumen.atendidos', 1)
        ->assertJsonPath('resumen.pendientes', 2)
        ->assertJsonPath('resumen.porcentaje', 33.3)
        ->assertJsonCount(3, 'por_fecha')
        ->assertJsonPath('por_fecha.0', ['fecha' => '2026-10-10', 'atendido' => true])
        ->assertJsonPath('por_area.0.programados', 3)
        ->assertJsonCount(2, 'pendientes')
        ->assertJsonPath('pendientes.0.equipo', 'EQ-PENDIENTE-1')
        // Listas dependientes: las áreas y los tipos de equipo del periodo.
        ->assertJsonCount(1, 'opciones.areas')
        ->assertJsonCount(3, 'opciones.tipos_equipo');

    $otraArea = crearArea($this->tenant, 'Otra área');
    $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', [...$rango, 'areas' => [$otraArea->id]]))
        ->assertJsonPath('resumen.programados', 0)
        ->assertJsonPath('resumen.porcentaje', null);

    // Tipo de mantenimiento del tipo de equipo: los de la prueba son de tipo A.
    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', [...$rango, 'tipos_mantenimiento' => ['A']]))
        ->assertJsonPath('resumen.programados', 3);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', [...$rango, 'tipos_mantenimiento' => ['B']]))
        ->assertJsonPath('resumen.programados', 0);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', [...$rango, 'tipos_mantenimiento' => ['C']]))
        ->assertJsonValidationErrors('tipos_mantenimiento.0');
});

test('cumplimiento cuenta como atendidos los que quedaron fuera de servicio, propios o de terceros, y dice cuantos son', function () {
    $operativo = crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-OPERATIVO'), ['fecha_proximo_servicio' => '2026-10-10']);
    $fueraEnMantenimiento = crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-FS-MANT'), ['fecha_proximo_servicio' => '2026-10-11']);
    $fueraEnCalibracion = crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-FS-CAL'), ['fecha_proximo_servicio' => '2026-10-12', 'tipo_servicio' => 'CALIBRACION']);
    $enProceso = crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-EN-PROCESO'), ['fecha_proximo_servicio' => '2026-10-13']);

    foreach ([[$operativo, 'FINALIZADO', 'OPERATIVO'], [$fueraEnMantenimiento, 'FINALIZADO', 'FUERA_DE_SERVICIO'], [$enProceso, 'EN_PROCESO', 'FUERA_DE_SERVICIO']] as [$programacion, $estado, $estadoFinal]) {
        Mantenimiento::create([
            'orden_trabajo_id' => crearOrdenDeIndicador($programacion)->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'fecha_mantenimiento' => '2026-10-10',
            'tecnico_id' => $this->admin->id,
            'estado_mantenimiento' => $estado,
            'estado_final_equipo' => $estadoFinal,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    Calibracion::create([
        'orden_trabajo_id' => crearOrdenDeIndicador($fueraEnCalibracion)->id,
        'fecha_calibracion' => '2026-10-12',
        'tecnico_id' => $this->admin->id,
        'estado_calibracion' => 'FUERA_DE_SERVICIO',
        'temperatura' => 20,
        'humedad' => 50,
        'tenant_id' => $this->tenant->id,
    ]);

    ServicioTercero::create([
        'orden_trabajo_id' => crearOrdenDeIndicador(crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-FS-TERCERO'), ['fecha_proximo_servicio' => '2026-10-14']))->id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'empresa_tercero_id' => EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'estado_servicio' => 'FINALIZADO',
        'estado_final_equipo' => 'FUERA_DE_SERVICIO',
        'fecha_realizacion' => '2026-10-14',
        'tenant_id' => $this->tenant->id,
    ]);

    // Con los dos servicios: el mantenimiento terminó y la misma orden ya pasó a calibración.
    $ordenEnCalibracion = crearOrdenDeIndicador(crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-EN-CALIBRACION'), ['fecha_proximo_servicio' => '2026-10-15', 'tipo_servicio' => 'MANTENIMIENTO,CALIBRACION']));
    Mantenimiento::create([
        'orden_trabajo_id' => $ordenEnCalibracion->id,
        'tipo_mantenimiento' => 'PREVENTIVO',
        'fecha_mantenimiento' => '2026-10-15',
        'tecnico_id' => $this->admin->id,
        'estado_mantenimiento' => 'FINALIZADO',
        'estado_final_equipo' => 'OPERATIVO',
        'tenant_id' => $this->tenant->id,
    ]);
    Calibracion::create([
        'orden_trabajo_id' => $ordenEnCalibracion->id,
        'fecha_calibracion' => '2026-10-16',
        'tecnico_id' => $this->admin->id,
        'estado_calibracion' => 'EN_PROCESO',
        'temperatura' => 20,
        'humedad' => 50,
        'tenant_id' => $this->tenant->id,
    ]);

    // Con los dos servicios y sin orden todavía: va primero por el mantenimiento.
    crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-SIN-ORDEN'), ['fecha_proximo_servicio' => '2026-10-16', 'tipo_servicio' => 'MANTENIMIENTO,CALIBRACION']);

    // Programado para noviembre, pero el mantenimiento se hizo en octubre: los fuera de
    // servicio van por la fecha del servicio, no por la programada.
    Mantenimiento::create([
        'orden_trabajo_id' => crearOrdenDeIndicador(crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-FS-ADELANTADO'), ['fecha_proximo_servicio' => '2026-11-20']))->id,
        'tipo_mantenimiento' => 'PREVENTIVO',
        'fecha_mantenimiento' => '2026-10-20',
        'tecnico_id' => $this->admin->id,
        'estado_mantenimiento' => 'FINALIZADO',
        'estado_final_equipo' => 'FUERA_DE_SERVICIO',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk()
        ->assertJsonPath('resumen.programados', 7)
        ->assertJsonPath('resumen.atendidos', 5)
        // El que sigue en proceso no cuenta, aunque su estado final diga fuera de servicio.
        ->assertJsonPath('resumen.fuera_de_servicio', 4)
        ->assertJsonPath('resumen.fuera_de_servicio_por_servicio', ['MANTENIMIENTO' => 3, 'CALIBRACION' => 1])
        ->assertJsonPath('resumen.pendientes', 2)
        // De dónde son: cada equipo una vez, en el servicio en que va (suman los totales). El
        // que ya pasó a calibración no cuenta en mantenimiento.
        ->assertJsonPath('resumen.por_servicio.MANTENIMIENTO', ['atendidos' => 3, 'pendientes' => 2])
        ->assertJsonPath('resumen.por_servicio.CALIBRACION', ['atendidos' => 2, 'pendientes' => 0]);
});

test('cumplimiento responde con fuera de servicio solo en calibracion y ninguno en mantenimiento', function () {
    Calibracion::create([
        'orden_trabajo_id' => crearOrdenDeIndicador(crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-FS-CAL'), ['fecha_proximo_servicio' => '2026-10-12', 'tipo_servicio' => 'CALIBRACION']))->id,
        'fecha_calibracion' => '2026-10-12',
        'tecnico_id' => $this->admin->id,
        'estado_calibracion' => 'FUERA_DE_SERVICIO',
        'temperatura' => 20,
        'humedad' => 50,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk()
        ->assertJsonPath('resumen.fuera_de_servicio_por_servicio', ['MANTENIMIENTO' => 0, 'CALIBRACION' => 1]);
});

test('cumplimiento lista los pendientes de la criticidad mas alta a la mas baja', function () {
    foreach (['EQ-SIN' => null, 'EQ-BAJA' => 'BAJA', 'EQ-ALTA' => 'ALTA', 'EQ-MEDIA' => 'MEDIA'] as $codigo => $criticidad) {
        $equipo = crearEquipoParaBusqueda($this->bahia, $codigo);
        $equipo->update(['criticidad' => $criticidad]);
        crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-10-10']);
    }

    $respuesta = $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk();

    expect(collect($respuesta->json('pendientes'))->pluck('criticidad')->all())->toBe(['ALTA', 'MEDIA', 'BAJA', null]);
});

test('cumplimiento lista los no recibidos de los ingresos del periodo, aunque su servicio fuera despues, y cuenta cuantos hubo por novedad', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-31']);
    $ingresoDeOtroPeriodo = Ingreso::factory()->create(['bahia_id' => $this->bahia->id, 'desde' => '2026-12-01', 'hasta' => '2026-12-31']);
    $sinEquipo = Novedad::factory()->create(['tenant_id' => $this->tenant->id, 'categoria' => 'INGRESO', 'nombre' => 'Cliente no entregó']);
    $danado = Novedad::factory()->create(['tenant_id' => $this->tenant->id, 'categoria' => 'INGRESO', 'nombre' => 'Equipo en uso']);
    $noRecibido = fn (string $codigo, Novedad $novedad, array $datos = []) => crearProgramacion(crearEquipoParaBusqueda($this->bahia, $codigo), [
        'fecha_proximo_servicio' => '2026-10-10',
        'ingreso_id' => $ingreso->id,
        'ingresado' => false,
        'estado_programacion' => 'CANCELADO',
        'novedad_ingreso_id' => $novedad->id,
        ...$datos,
    ]);

    $noRecibido('EQ-NR-1', $sinEquipo, ['observacion_no_ingreso' => 'Lo tiene producción']);
    // Programado para después del rango, pero su ingreso es del periodo: también cuenta.
    $noRecibido('EQ-NR-2', $sinEquipo, ['fecha_proximo_servicio' => '2026-11-20']);
    $noRecibido('EQ-OTRO-INGRESO', $sinEquipo, ['ingreso_id' => $ingresoDeOtroPeriodo->id]);
    $noRecibido('EQ-NR-3', $danado, ['estado_programacion' => 'RE_AGENDADO', 're_agendar' => true, 'datos_re_agendamiento' => ['fecha_proximo_agendamiento' => '2026-11-05']]);
    crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-RECIBIDO'), ['fecha_proximo_servicio' => '2026-10-10', 'ingreso_id' => $ingreso->id]);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk()
        ->assertJsonCount(3, 'no_recibidos')
        ->assertJsonPath('no_recibidos.0.novedad', 'Cliente no entregó')
        ->assertJsonPath('no_recibidos.0.observacion', 'Lo tiene producción')
        ->assertJsonPath('no_recibidos.1.fecha_re_agendamiento', '2026-11-05')
        ->assertJsonPath('no_recibidos.2.equipo', 'EQ-NR-2')
        ->assertJsonPath('novedades_no_recibidos', [
            ['novedad' => 'Cliente no entregó', 'cantidad' => 2, 'porcentaje' => 66.7],
            ['novedad' => 'Equipo en uso', 'cantidad' => 1, 'porcentaje' => 33.3],
        ]);
});
