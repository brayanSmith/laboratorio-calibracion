<?php

use App\Enums\ConceptoObjetivo;
use App\Enums\ModuloObjetivo;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\ObjetivoDashboard;
use App\Models\ObjetivoUser;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

test('guarda los objetivos de un año del modulo y el modulo sale del concepto', function () {
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::TiemposAtencion), [
            'anio' => 2026,
            'valores' => [
                'TIEMPO_ATENCION_DIAS' => 4.5,
                // De otro módulo: no se guarda.
                'CUMPLIMIENTO_PORCENTAJE' => 95,
            ],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'conceptos')
        ->assertJsonPath('anios.0.anio', 2026)
        ->assertJsonPath('anios.0.valores.TIEMPO_ATENCION_DIAS', 4.5);

    expect(ObjetivoDashboard::where('tenant_id', $this->tenant->id)->sole())
        ->modulo->toBe(ModuloObjetivo::TiemposAtencion)
        ->mes->toBeNull()
        ->tipo_equipo_id->toBeNull();

    // Guardar de nuevo actualiza, y un valor vacío quita ese objetivo.
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::TiemposAtencion), [
            'anio' => 2026,
            'valores' => ['TIEMPO_ATENCION_DIAS' => null],
        ])
        ->assertOk()
        ->assertJsonCount(0, 'anios');

    expect(ObjetivoDashboard::where('tenant_id', $this->tenant->id)->count())->toBe(0);
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
});

test('los objetivos de desempeno se guardan por mes y tipo de equipo, no anuales', function () {
    $balanza = crearTipoEquipo($this->tenant, 'Balanza');
    $manometro = crearTipoEquipo($this->tenant, 'Manómetro');

    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'valores' => ['PROMEDIO_HORAS_MANTENIMIENTO' => 2],
        ])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos-mensuales.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'tipo_equipo_id' => $balanza->id,
            'meses' => [
                1 => ['PROMEDIO_HORAS_MANTENIMIENTO' => 1.5, 'PROMEDIO_HORAS_CALIBRACION' => 3],
                2 => ['PROMEDIO_HORAS_MANTENIMIENTO' => 2],
            ],
        ])
        ->assertOk()
        // Solo los tiempos: las cantidades son los objetivos de cada usuario.
        ->assertJsonCount(2, 'conceptos')
        ->assertJsonPath('registros.0.tipo_equipo', 'Balanza')
        ->assertJsonPath('registros.0.meses.1.PROMEDIO_HORAS_MANTENIMIENTO', 1.5)
        ->assertJsonPath('registros.0.meses.1.PROMEDIO_HORAS_CALIBRACION', 3)
        ->assertJsonPath('registros.0.meses.2.PROMEDIO_HORAS_MANTENIMIENTO', 2);

    // Otro tipo de equipo no pisa los de la balanza.
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos-mensuales.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'tipo_equipo_id' => $manometro->id,
            'meses' => [1 => ['PROMEDIO_HORAS_MANTENIMIENTO' => 1]],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'registros');

    expect(ObjetivoDashboard::where('tipo_equipo_id', $balanza->id)->count())->toBe(3);

    // Guardar de nuevo actualiza, y lo que queda vacío se quita.
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos-mensuales.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'tipo_equipo_id' => $balanza->id,
            'meses' => [1 => ['PROMEDIO_HORAS_MANTENIMIENTO' => 4]],
        ])
        ->assertOk();

    expect(ObjetivoDashboard::where('tipo_equipo_id', $balanza->id)->sole()->valor)->toBe('4.00');

    $this->actingAs($this->admin)
        ->deleteJson(route('dashboard.objetivos-mensuales.destroy', [ModuloObjetivo::DesempenoTecnicos, 2026, $manometro->id]))
        ->assertOk()
        ->assertJsonCount(1, 'registros');
});

test('valida el tipo de equipo del laboratorio y no guarda cantidades por tipo de equipo', function () {
    $ajeno = crearTipoEquipo(Tenant::factory()->create(), 'Ajeno');
    $propio = crearTipoEquipo($this->tenant, 'Balanza');

    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos-mensuales.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'tipo_equipo_id' => $ajeno->id,
            'meses' => [],
        ])
        ->assertJsonValidationErrors('tipo_equipo_id');

    // Las horas admiten fracciones (1 h 30 min = 1.5); una cantidad ya no se guarda aquí.
    $this->actingAs($this->admin)
        ->putJson(route('dashboard.objetivos-mensuales.store', ModuloObjetivo::DesempenoTecnicos), [
            'anio' => 2026,
            'tipo_equipo_id' => $propio->id,
            'meses' => [3 => ['CANTIDAD_MANTENIMIENTOS' => 10, 'PROMEDIO_HORAS_MANTENIMIENTO' => 1.5]],
        ])
        ->assertOk();

    expect(ObjetivoDashboard::where('tipo_equipo_id', $propio->id)->sole()->concepto)
        ->toBe(ConceptoObjetivo::PromedioHorasMantenimiento);
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
        ->getJson(route('dashboard.objetivos-mensuales.index', ModuloObjetivo::DesempenoTecnicos))
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

test('desempeno trae los objetivos por mes de los tipos de equipo filtrados', function () {
    $balanza = crearTipoEquipo($this->tenant, 'Balanza');
    $manometro = crearTipoEquipo($this->tenant, 'Manómetro');

    foreach ([$balanza, $manometro] as $tipo) {
        ObjetivoDashboard::factory()->create([
            'tenant_id' => $this->tenant->id,
            'anio' => 2026,
            'mes' => 3,
            'tipo_equipo_id' => $tipo->id,
            'concepto' => ConceptoObjetivo::PromedioHorasMantenimiento,
            'valor' => 2,
        ]);
    }

    $rango = ['desde' => '2026-03-01', 'hasta' => '2026-03-31'];

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', $rango))
        ->assertOk()
        ->assertJsonCount(2, 'objetivos')
        ->assertJsonPath('objetivos.0.mes', 3)
        ->assertJsonPath('objetivos.0.valores.PROMEDIO_HORAS_MANTENIMIENTO', 2);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'tipos_equipo' => [$balanza->id]]))
        ->assertJsonCount(1, 'objetivos')
        ->assertJsonPath('objetivos.0.tipo_equipo_id', $balanza->id);
});

test('desempeno trae la cantidad objetivo de cada mes sumada entre los tecnicos filtrados', function () {
    $ana = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $luis = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    foreach ([[$ana, 10], [$luis, 6]] as [$tecnico, $valor]) {
        ObjetivoUser::factory()->for($tecnico)->for($this->tenant)->create(['anio' => 2026, 'mes' => 3, 'tipo' => 'MANTENIMIENTO', 'valor' => $valor]);
    }
    ObjetivoUser::factory()->for($ana)->for($this->tenant)->create(['anio' => 2026, 'mes' => 3, 'tipo' => 'CALIBRACION', 'valor' => 4]);

    $rango = ['desde' => '2026-03-01', 'hasta' => '2026-03-31'];

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', $rango))
        ->assertOk()
        ->assertJsonCount(2, 'objetivos_usuarios')
        ->assertJsonPath('objetivos_usuarios.0.tipo', 'CALIBRACION')
        ->assertJsonPath('objetivos_usuarios.1.tipo', 'MANTENIMIENTO')
        ->assertJsonPath('objetivos_usuarios.1.valor', 16);

    $this->actingAs($this->admin)
        ->getJson(route('dashboard.indicadores.desempeno-tecnicos', [...$rango, 'tecnicos' => [$luis->id]]))
        ->assertJsonCount(1, 'objetivos_usuarios')
        ->assertJsonPath('objetivos_usuarios.0.valor', 6);
});
