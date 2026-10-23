<?php

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\EquipoHistorico;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/** Orden de trabajo de un equipo recibido, con su mantenimiento, calibración, despacho y servicio de tercero. */
function crearServiciosDelEquipo(EquipoProgramacion $programacion, ?User $tecnico = null): OrdenTrabajo
{
    $tecnicoId = ($tecnico ?? User::factory()->forTenant($programacion->tenant)->create())->id;
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $programacion->tenant_id,
    ]);

    Mantenimiento::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tipo_mantenimiento' => $programacion->refresh()->tipo_mantenimiento,
        'fecha_mantenimiento' => now()->toDateString(),
        'tecnico_id' => $tecnicoId,
        'tenant_id' => $programacion->tenant_id,
    ]);
    Calibracion::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'fecha_calibracion' => now()->toDateString(),
        'tecnico_id' => $tecnicoId,
        'tenant_id' => $programacion->tenant_id,
    ]);
    Despacho::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tecnico_entrega_id' => $tecnicoId,
        'tenant_id' => $programacion->tenant_id,
    ]);
    ServicioTercero::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'empresa_tercero_id' => EmpresaTercero::factory()->for($programacion->tenant)->create()->id,
        'tenant_id' => $programacion->tenant_id,
    ]);

    return $ordenTrabajo;
}

test('el historial incluye los ingresos, mantenimientos, calibraciones, despachos y servicios de terceros del equipo', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-HISTORIAL');
    $ordenTrabajo = crearServiciosDelEquipo($programacion);
    crearServiciosDelEquipo(crearEquipoRecibido($this->bahia, 'EQ-OTRO'));

    $this->actingAs($this->admin)
        ->getJson(route('equipos.historial', $programacion->equipo_id))
        ->assertOk()
        ->assertJsonCount(1, 'ingresos')
        ->assertJsonPath('ingresos.0.estado_ingreso', 'RECIBIDO')
        ->assertJsonCount(1, 'mantenimientos')
        ->assertJsonPath('mantenimientos.0.orden_trabajo_codigo', $ordenTrabajo->codigo)
        ->assertJsonCount(1, 'calibraciones')
        ->assertJsonPath('calibraciones.0.orden_trabajo_codigo', $ordenTrabajo->codigo)
        ->assertJsonCount(1, 'despachos')
        ->assertJsonPath('despachos.0.orden_trabajo_codigo', $ordenTrabajo->codigo)
        ->assertJsonCount(1, 'servicios_terceros')
        ->assertJsonPath('servicios_terceros.0.orden_trabajo_codigo', $ordenTrabajo->codigo)
        ->assertJsonPath('servicios_terceros.0.tipo_servicio', 'MANTENIMIENTO');
});

test('sin el permiso de ver todos, un tecnico solo ve sus propios servicios en el historial', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-HISTORIAL');
    $propia = crearServiciosDelEquipo($programacion, $tecnico);
    crearServiciosDelEquipo($programacion);

    $this->actingAs($tecnico)
        ->getJson(route('equipos.historial', $programacion->equipo_id))
        ->assertOk()
        ->assertJsonCount(1, 'mantenimientos')
        ->assertJsonPath('mantenimientos.0.orden_trabajo_codigo', $propia->codigo)
        ->assertJsonCount(1, 'calibraciones')
        ->assertJsonCount(1, 'despachos');
});

test('no permite ver el historial de un equipo de otro tenant', function () {
    $ajeno = crearEquipoRecibido(Bahia::factory()->create(), 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->getJson(route('equipos.historial', $ajeno->equipo_id))
        ->assertForbidden();
});

test('ver el historial depende de su propio permiso, no del de ver equipos', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-HISTORIAL');
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::EquiposVer]);

    $this->actingAs($usuario)
        ->getJson(route('equipos.historial', $programacion->equipo_id))
        ->assertForbidden();

    $usuario->givePermissionTo(TenantPermission::EquiposHistorial->value);

    $this->actingAs($usuario)
        ->getJson(route('equipos.historial', $programacion->equipo_id))
        ->assertOk();
});

test('el historial solo incluye los servicios de terceros del tipo que el usuario tiene permitido', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-HISTORIAL');
    crearServiciosDelEquipo($programacion);
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::EquiposHistorial, TenantPermission::CalibracionesTerceros]);

    $this->actingAs($usuario)
        ->getJson(route('equipos.historial', $programacion->equipo_id))
        ->assertOk()
        ->assertJsonCount(0, 'servicios_terceros');

    $usuario->givePermissionTo(TenantPermission::MantenimientosTerceros->value);

    $this->actingAs($usuario)
        ->getJson(route('equipos.historial', $programacion->equipo_id))
        ->assertJsonCount(1, 'servicios_terceros');
});

test('el historial incluye los servicios historicos importados del equipo, del mas reciente al mas antiguo', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-HISTORIAL');
    EquipoHistorico::factory()->for($programacion->equipo)->create(['fecha_servicio' => '2022-03-01', 'tipo_servicio' => 'MANTENIMIENTO']);
    EquipoHistorico::factory()->for($programacion->equipo)->create([
        'fecha_servicio' => '2024-06-15',
        'tipo_servicio' => 'CALIBRACION',
        'nombre_laboratorio' => 'SEIMET',
        'es_tercero' => true,
        'adjunto' => 'historicos/1/1/abcd1234-certificado.pdf',
    ]);
    EquipoHistorico::factory()->for(crearEquipoRecibido($this->bahia, 'EQ-OTRO')->equipo)->create();

    $this->actingAs($this->admin)
        ->getJson(route('equipos.historial', $programacion->equipo_id))
        ->assertOk()
        ->assertJsonCount(2, 'historicos')
        ->assertJsonPath('historicos.0.fecha_servicio', '2024-06-15')
        ->assertJsonPath('historicos.0.tipo_servicio', 'CALIBRACION')
        ->assertJsonPath('historicos.0.nombre_laboratorio', 'SEIMET')
        ->assertJsonPath('historicos.0.es_tercero', true)
        ->assertJsonPath('historicos.0.adjunto_nombre', 'certificado.pdf')
        ->assertJsonPath('historicos.0.adjunto_url', '/storage/historicos/1/1/abcd1234-certificado.pdf')
        ->assertJsonPath('historicos.1.fecha_servicio', '2022-03-01')
        ->assertJsonPath('historicos.1.adjunto_url', null);
});
