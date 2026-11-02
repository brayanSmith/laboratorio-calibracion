<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

test('al agendar un mantenimiento guarda la fecha programada elegida, con tecnico propio o tercero', function () {
    $propio = crearEquipoRecibido($this->bahia, 'EQ-PROPIO');
    $tercero = crearEquipoRecibido($this->bahia, 'EQ-TERCERO');
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), [
            'equipos_listos' => [
                $propio->id => [
                    'listo_para_mantenimiento' => '1',
                    'mantenimiento_asignado_tercero' => '0',
                    'tecnico_id' => $this->admin->id,
                    'fecha_programada' => '2026-11-15',
                ],
                $tercero->id => [
                    'listo_para_mantenimiento' => '1',
                    'mantenimiento_asignado_tercero' => '1',
                    'empresa_tercero_id' => $empresa->id,
                    'fecha_programada' => '2026-11-20',
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    $ordenDe = fn ($programacion) => OrdenTrabajo::where('equipo_programacion_id', $programacion->id)->sole();

    expect(Mantenimiento::where('orden_trabajo_id', $ordenDe($propio)->id)->sole())
        ->fecha_programada->toDateString()->toBe('2026-11-15')
        ->fecha_realizacion->toBeNull()
        ->and(ServicioTercero::where('orden_trabajo_id', $ordenDe($tercero)->id)->sole()->fecha_programada->toDateString())->toBe('2026-11-20');
});

test('al agendar una calibracion guarda la fecha programada elegida', function () {
    $orden = crearOrdenListaParaCalibracion($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store-calibracion'), [
            'ordenes_listas' => [
                $orden->id => [
                    'listo_para_calibracion' => '1',
                    'calibracion_asignado_tercero' => '0',
                    'tecnico_id' => $this->admin->id,
                    'fecha_programada' => '2026-12-01',
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(Calibracion::where('orden_trabajo_id', $orden->id)->sole()->fecha_programada->toDateString())->toBe('2026-12-01');
});

test('sin fecha programada se agenda para hoy, y una fecha invalida se rechaza', function () {
    $programacion = crearEquipoRecibido($this->bahia);
    $datos = ['listo_para_mantenimiento' => '1', 'mantenimiento_asignado_tercero' => '0', 'tecnico_id' => $this->admin->id];

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), ['equipos_listos' => [$programacion->id => [...$datos, 'fecha_programada' => 'no-es-fecha']]])
        ->assertSessionHasErrors('equipos_listos.0.fecha_programada');

    $this->actingAs($this->admin)
        ->post(route('orden-trabajos.store'), ['equipos_listos' => [$programacion->id => $datos]])
        ->assertSessionHasNoErrors();

    expect(Mantenimiento::sole()->fecha_programada->toDateString())->toBe(now()->toDateString());
});

test('al terminar el servicio se fija sola la fecha real, una sola vez', function () {
    $this->travelTo('2026-11-18 10:00');
    $mantenimiento = crearMantenimiento($this->bahia, ['fecha_programada' => '2026-11-15']);

    $this->actingAs($this->admin)->post(route('mantenimientos.iniciar', $mantenimiento));
    $this->actingAs($this->admin)
        ->post(route('mantenimientos.finalizar', $mantenimiento), ['estado_final_equipo' => 'OPERATIVO', 'firmado' => '1'])
        ->assertSessionHasNoErrors();

    expect($mantenimiento->refresh()->fecha_realizacion->toDateString())->toBe('2026-11-18');

    $this->travelTo('2026-11-25 10:00');
    $mantenimiento->update(['descripcion' => 'Cambio posterior']);

    expect($mantenimiento->refresh()->fecha_realizacion->toDateString())->toBe('2026-11-18');

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('mantenimientos.0.fecha_programada', '2026-11-15')
            ->where('mantenimientos.0.fecha_realizacion', '2026-11-18'));
});

test('la calibracion y el servicio de tercero tambien guardan su fecha real al terminar', function () {
    $this->travelTo('2026-11-18 10:00');
    $calibracion = crearCalibracion($this->bahia, ['fecha_programada' => '2026-11-20']);
    $servicio = crearServicioTercero($this->bahia, ['fecha_programada' => '2026-11-10'], 'EQ-7002');

    $this->actingAs($this->admin)->post(route('calibraciones.iniciar', $calibracion));
    $this->actingAs($this->admin)->post(route('calibraciones.finalizar', $calibracion), ['estado_calibracion' => 'DEVOLVER_MANTENIMIENTO']);
    $this->actingAs($this->admin)->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio()]);

    expect($calibracion->refresh()->fecha_realizacion->toDateString())->toBe('2026-11-18')
        ->and($servicio->refresh()->fecha_realizacion->toDateString())->toBe('2026-11-18');
});
