<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Equipos\Models\Equipo;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

function inactivarEquipo(Equipo $equipo): void
{
    $equipo->update(['activo' => false, 'motivo_inactivo' => 'Dado de baja']);
}

test('el dashboard no cuenta ni lista los equipos inactivos', function () {
    crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-ACTIVO'), ['fecha_proximo_servicio' => now()->subDays(5)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    $inactivo = crearEquipoParaBusqueda($this->bahia, 'EQ-INACTIVO');
    crearProgramacion($inactivo, ['fecha_proximo_servicio' => now()->subDays(5)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    inactivarEquipo($inactivo);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('total_equipos', 1)
            ->where('vencimientos.vencidos', 1)
            ->has('equipos_urgentes', 1)
            ->where('equipos_urgentes.0.codigo', 'EQ-ACTIVO')
            ->has('equipos', 1)
            ->where('equipos.0.codigo', 'EQ-ACTIVO'));
});

test('el listado de equipos sigue mostrando los inactivos, para poder reactivarlos', function () {
    inactivarEquipo(crearEquipoParaBusqueda($this->bahia, 'EQ-INACTIVO'));

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertInertia(fn ($page) => $page->has('equipos', 1)->where('equipos.0.activo', false));
});

test('un equipo inactivo no aparece para agendar ni para ingresar', function () {
    $activo = crearEquipoParaBusqueda($this->bahia, 'EQ-ACTIVO');
    crearProgramacion($activo, ['fecha_proximo_servicio' => '2026-06-15']);
    $inactivo = crearEquipoParaBusqueda($this->bahia, 'EQ-INACTIVO');
    crearProgramacion($inactivo, ['fecha_proximo_servicio' => '2026-06-15']);
    inactivarEquipo($inactivo);

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', ['desde' => '2026-06-01', 'hasta' => '2026-06-30', 'bahia_ids' => [$this->bahia->id]]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.equipo.codigo', 'EQ-ACTIVO');

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.equipos-disponibles', ['bahia_ids' => [$this->bahia->id]]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.codigo', 'EQ-ACTIVO');
});

test('un despacho de un equipo inactivo no aparece para agendar ni se puede agendar', function () {
    $despacho = crearDespachoListo($this->bahia, [], 'EQ-INACTIVO');
    inactivarEquipo($despacho->ordenTrabajo->equipoProgramacion->equipo);

    $this->actingAs($this->admin)
        ->getJson(route('orden-trabajos.despachos-listos'))
        ->assertOk()
        ->assertJsonCount(0);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-despacho'), [
            'despachos_listos' => [$despacho->id => ['tecnico_id' => $this->admin->id, 'fecha_programada' => '2026-10-20']],
        ])
        ->assertSessionHasErrors('despachos_listos.0.id');

    expect($despacho->refresh()->tecnico_entrega_id)->toBeNull();
});

test('los servicios de un equipo inactivo salen de la agenda pero siguen en su historial, marcados', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-INACTIVO');
    crearServiciosDelEquipo($programacion, $this->admin);
    inactivarEquipo($programacion->equipo);

    $this->actingAs($this->admin)
        ->getJson(route('agenda.eventos'))
        ->assertOk()
        ->assertJsonCount(0);

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page
            ->has('mantenimientos', 1)
            ->where('mantenimientos.0.equipo_activo', false));

    $this->actingAs($this->admin)
        ->get(route('ingresos.index'))
        ->assertInertia(fn ($page) => $page->where('ingresos.0.equipo_programaciones.0.equipo.activo', false));
});

test('en el plan de cumplimiento un equipo inactivo sin atender no cuenta como pendiente', function () {
    crearProgramacion(crearEquipoParaBusqueda($this->bahia, 'EQ-ACTIVO'), ['fecha_proximo_servicio' => '2026-10-10', 'tipo_mantenimiento' => 'PREVENTIVO']);
    $inactivo = crearEquipoParaBusqueda($this->bahia, 'EQ-INACTIVO');
    crearProgramacion($inactivo, ['fecha_proximo_servicio' => '2026-10-10', 'tipo_mantenimiento' => 'PREVENTIVO']);
    inactivarEquipo($inactivo);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk()
        ->assertJsonPath('resumen.programados', 1)
        ->assertJsonPath('resumen.pendientes', 1);
});
