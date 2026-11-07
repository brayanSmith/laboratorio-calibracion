<?php

use App\Enums\ConceptoObjetivo;
use App\Enums\ModuloObjetivo;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\ObjetivoDashboard;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

test('guarda los objetivos de un año del modulo y el modulo sale del concepto', function () {
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'valores' => [
                'CANTIDAD_MANTENIMIENTOS' => 480,
                'PROMEDIO_HORAS_MANTENIMIENTO' => 3.5,
                'CANTIDAD_CALIBRACIONES' => 300,
                'PROMEDIO_HORAS_CALIBRACION' => null,
                // De otro módulo: no se guarda.
                'CUMPLIMIENTO_PORCENTAJE' => 95,
            ],
        ])
        ->assertOk()
        ->assertJsonCount(4, 'conceptos')
        ->assertJsonPath('anios.0.anio', 2026)
        ->assertJsonPath('anios.0.valores.CANTIDAD_MANTENIMIENTOS', 480)
        ->assertJsonPath('anios.0.valores.PROMEDIO_HORAS_MANTENIMIENTO', 3.5);

    expect(ObjetivoDashboard::where('tenant_id', $this->tenant->id)->count())->toBe(3)
        ->and(ObjetivoDashboard::where('concepto', ConceptoObjetivo::CantidadMantenimientos)->sole()->modulo)
        ->toBe(ModuloObjetivo::DesempenoTecnicos);

    // Guardar de nuevo actualiza, y un valor vacío quita ese objetivo.
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'valores' => ['CANTIDAD_MANTENIMIENTOS' => 500, 'PROMEDIO_HORAS_MANTENIMIENTO' => null],
        ])
        ->assertOk()
        ->assertJsonPath('anios.0.valores.CANTIDAD_MANTENIMIENTOS', 500);

    expect(ObjetivoDashboard::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('el cumplimiento esperado no puede pasar de 100 ni los objetivos ser negativos', function () {
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::Cumplimiento), [
            'anio' => 2026,
            'valores' => ['CUMPLIMIENTO_PORCENTAJE' => 120],
        ])
        ->assertJsonValidationErrors('valores.CUMPLIMIENTO_PORCENTAJE');

    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::TiemposAtencion), [
            'anio' => 2026,
            'valores' => ['TIEMPO_ATENCION_DIAS' => -1],
        ])
        ->assertJsonValidationErrors('valores.TIEMPO_ATENCION_DIAS');

    // Las cantidades son enteras; las horas sí admiten fracciones (1 h 30 min = 1.5).
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'valores' => ['CANTIDAD_MANTENIMIENTOS' => 10.5, 'PROMEDIO_HORAS_MANTENIMIENTO' => 1.5],
        ])
        ->assertJsonValidationErrors('valores.CANTIDAD_MANTENIMIENTOS')
        ->assertJsonMissingValidationErrors('valores.PROMEDIO_HORAS_MANTENIMIENTO');
});

test('quita los objetivos de un año del modulo', function () {
    ObjetivoDashboard::factory()->create(['tenant_id' => $this->tenant->id, 'anio' => 2025, 'concepto' => ConceptoObjetivo::TiempoAtencionDias]);
    ObjetivoDashboard::factory()->create(['tenant_id' => $this->tenant->id, 'anio' => 2025, 'concepto' => ConceptoObjetivo::CumplimientoPorcentaje]);

    $this->actingAs($this->admin)
        ->deleteJson(route('dashboard.objetivos.destroy', [ModuloObjetivo::TiemposAtencion, 2025]))
        ->assertOk()
        ->assertJsonCount(0, 'anios');

    expect(ObjetivoDashboard::pluck('concepto')->all())->toBe([ConceptoObjetivo::CumplimientoPorcentaje]);
});

test('solo quien tiene el permiso define objetivos; el tecnico no lo recibe por defecto', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $conPermiso = usuarioConPermisos($this->tenant, [TenantPermission::DashboardObjetivos]);

    expect(TenantRole::Tecnico->permissions())->not->toContain(TenantPermission::DashboardObjetivos)
        ->and(TenantRole::Administrador->permissions())->toContain(TenantPermission::DashboardObjetivos);

    $this->actingAs($tecnico)
        ->getJson(route('dashboard.objetivos.index', ModuloObjetivo::Cumplimiento))
        ->assertForbidden();

    $this->actingAs($tecnico)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::Cumplimiento), [
            'anio' => 2026,
            'valores' => ['CUMPLIMIENTO_PORCENTAJE' => 90],
        ])
        ->assertForbidden();

    $this->actingAs($conPermiso)
        ->getJson(route('dashboard.objetivos.index', ModuloObjetivo::Cumplimiento))
        ->assertOk()
        ->assertJsonPath('conceptos.0.concepto', 'CUMPLIMIENTO_PORCENTAJE')
        ->assertJsonPath('conceptos.0.maximo', 100)
        ->assertJsonPath('conceptos.0.formato', 'porcentaje');
});

test('cada indicador trae los objetivos de cada año del periodo y su serie mensual', function () {
    ObjetivoDashboard::factory()->create(['tenant_id' => $this->tenant->id, 'anio' => 2025, 'concepto' => ConceptoObjetivo::CumplimientoPorcentaje, 'valor' => 85]);
    ObjetivoDashboard::factory()->create(['tenant_id' => $this->tenant->id, 'anio' => 2026, 'concepto' => ConceptoObjetivo::CumplimientoPorcentaje, 'valor' => 90]);
    ObjetivoDashboard::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'anio' => 2026, 'concepto' => ConceptoObjetivo::CumplimientoPorcentaje, 'valor' => 10]);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.cumplimiento', ['desde' => '2025-12-01', 'hasta' => '2026-01-31']))
        ->assertOk()
        ->assertJsonCount(2, 'objetivos')
        ->assertJsonPath('objetivos.0.anio', 2025)
        ->assertJsonPath('objetivos.0.valores.CUMPLIMIENTO_PORCENTAJE', 85)
        ->assertJsonPath('objetivos.1.valores.CUMPLIMIENTO_PORCENTAJE', 90)
        ->assertJsonCount(2, 'mensual')
        ->assertJsonPath('mensual.0.mes', '2025-12')
        ->assertJsonPath('mensual.1.mes', '2026-01');

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.tiempos-atencion', ['desde' => '2026-01-01', 'hasta' => '2026-03-31']))
        ->assertOk()
        ->assertJsonCount(3, 'mensual')
        ->assertJsonPath('mensual.0.cantidad', 0)
        ->assertJsonPath('objetivos.0.anio', 2026);
});
