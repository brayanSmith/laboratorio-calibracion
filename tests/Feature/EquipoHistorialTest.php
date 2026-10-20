<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Despachos\Models\Despacho;
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
