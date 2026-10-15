<?php

use App\Models\Bahia;
use App\Models\Tenant;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->forTenant(Tenant::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

test('platform administrators are sent to their own panel instead of the laboratory dashboard', function () {
    $platformAdmin = User::factory()->platformAdmin()->create(['tenant_id' => null]);

    $this->actingAs($platformAdmin)
        ->get(route('dashboard'))
        ->assertRedirect(route('plataforma.dashboard'));
});

test('el dashboard cuenta los equipos al dia, proximos a vencer y vencidos del tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $bahia = Bahia::factory()->for(crearArea($tenant))->create();

    crearProgramacion(crearEquipoParaBusqueda($bahia, 'EQ-1'), ['fecha_proximo_servicio' => now()->addDays(200)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    crearProgramacion(crearEquipoParaBusqueda($bahia, 'EQ-2'), ['fecha_proximo_servicio' => now()->addDays(5)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    crearProgramacion(crearEquipoParaBusqueda($bahia, 'EQ-3'), ['fecha_proximo_servicio' => now()->subDays(5)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    crearProgramacion(crearEquipoParaBusqueda(Bahia::factory()->create(), 'EQ-AJENO'), ['fecha_proximo_servicio' => now()->subDays(5)->toDateString()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('total_equipos', 3)
            ->where('vencimientos.al_dia', 1)
            ->where('vencimientos.proximos_a_vencer', 1)
            ->where('vencimientos.vencidos', 1));
});

test('el dashboard envia todos los equipos del tenant para la tabla', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $bahia = Bahia::factory()->for(crearArea($tenant))->create();

    crearProgramacion(crearEquipoParaBusqueda($bahia, 'EQ-1'), ['fecha_proximo_servicio' => now()->addDays(200)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    crearProgramacion(crearEquipoParaBusqueda($bahia, 'EQ-VENCIDO'), ['fecha_proximo_servicio' => now()->subDays(5)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    crearEquipoParaBusqueda(Bahia::factory()->create(), 'EQ-AJENO');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('equipos', 2)
            ->has('options'));
});

test('el dashboard lista hasta cinco equipos urgentes, los vencidos primero', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $bahia = Bahia::factory()->for(crearArea($tenant))->create();

    crearProgramacion(crearEquipoParaBusqueda($bahia, 'EQ-AL-DIA'), ['fecha_proximo_servicio' => now()->addDays(200)->toDateString(), 'dias_plazo_vencimiento' => 30]);

    foreach ([1, 2, 3] as $dias) {
        crearProgramacion(crearEquipoParaBusqueda($bahia, "EQ-PROX-$dias"), ['fecha_proximo_servicio' => now()->addDays($dias)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    }

    foreach ([10, 20, 5] as $dias) {
        crearProgramacion(crearEquipoParaBusqueda($bahia, "EQ-VENC-$dias"), ['fecha_proximo_servicio' => now()->subDays($dias)->toDateString(), 'dias_plazo_vencimiento' => 30]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('equipos_urgentes', 5)
            ->where('equipos_urgentes.0.codigo', 'EQ-VENC-20')
            ->where('equipos_urgentes.1.codigo', 'EQ-VENC-10')
            ->where('equipos_urgentes.2.codigo', 'EQ-VENC-5')
            ->where('equipos_urgentes.3.codigo', 'EQ-PROX-1')
            ->where('equipos_urgentes.3.estado_vencimiento', 'PROXIMO_A_VENCER')
            ->where('equipos_urgentes.4.codigo', 'EQ-PROX-2')
            ->has('equipos_urgentes.0.equipo_programaciones')
            ->has('equipos_urgentes.0.equipo_documentos'));
});
