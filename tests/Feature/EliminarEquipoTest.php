<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

test('elimina un equipo sin historial junto con su programacion pendiente', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia, 'EQ-NUEVO');
    $programacion = crearProgramacion($equipo);

    $this->actingAs($this->admin)
        ->delete(route('equipos.destroy', $equipo))
        ->assertRedirect(route('equipos.index'));

    expect(Equipo::find($equipo->id))->toBeNull()
        ->and(EquipoProgramacion::find($programacion->id))->toBeNull();
});

test('no elimina un equipo que ya tiene historial de servicios', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-TRAZA');

    $this->actingAs($this->admin)
        ->from(route('equipos.index'))
        ->delete(route('equipos.destroy', $programacion->equipo_id))
        ->assertRedirect(route('equipos.index'));

    expect(Equipo::find($programacion->equipo_id))->not->toBeNull();
});

test('los servicios de un equipo que ya estaba eliminado siguen mostrando su equipo', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-ELIMINADO');
    crearServiciosDelEquipo($programacion, $this->admin);
    $programacion->equipo->delete();

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('mantenimientos.0.equipo.codigo', 'EQ-ELIMINADO'));

    $this->actingAs($this->admin)->get(route('calibraciones.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('despachos.index'))->assertOk();
});

test('la programacion pendiente de un equipo eliminado no se busca para agendar', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia, 'EQ-ELIMINADO');
    crearProgramacion($equipo, ['fecha_proximo_servicio' => '2026-06-15']);
    $equipo->delete();

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-06-01',
            'hasta' => '2026-06-30',
            'bahia_ids' => [$this->bahia->id],
        ]))
        ->assertOk()
        ->assertExactJson([]);
});
