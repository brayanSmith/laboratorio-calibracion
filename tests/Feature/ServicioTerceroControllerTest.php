<?php

use App\Models\Bahia;
use App\Models\Despacho;
use App\Models\EmpresaTercero;
use App\Models\OrdenTrabajo;
use App\Models\ServicioTercero;
use App\Models\Tenant;
use App\Models\TiempoServicio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    Storage::fake('public');
});

function pdfServicio(): UploadedFile
{
    return UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf');
}

/** Servicio de tercero de mantenimiento: equipo recibido -> orden de trabajo -> servicio_tercero. */
function crearServicioTercero(Bahia $bahia, array $overrides = [], string $codigo = 'EQ-7001'): ServicioTercero
{
    $programacion = crearEquipoRecibido($bahia, $codigo);
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $bahia->tenant_id,
    ]);

    return ServicioTercero::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'empresa_tercero_id' => EmpresaTercero::factory()->create(['tenant_id' => $bahia->tenant_id])->id,
        'tenant_id' => $bahia->tenant_id,
        ...$overrides,
    ]);
}

test('inicia un servicio de tercero y abre un tiempo_servicio de tercero sin fin', function () {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.iniciar', $servicio))
        ->assertRedirect();

    $tiempo = TiempoServicio::where('orden_trabajo_id', $servicio->orden_trabajo_id)->first();

    expect($tiempo->tipo_servicio)->toBe('MANTENIMIENTO')
        ->and($tiempo->es_tercero)->toBeTrue()
        ->and($tiempo->estado_tiempo)->toBe('INICIO')
        ->and($tiempo->fin)->toBeNull();
});

test('actualiza la empresa y sube el pdf del servicio de tercero', function () {
    Storage::fake('public');
    $servicio = crearServicioTercero($this->bahia);
    $otraEmpresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put(route('servicio-terceros.update', $servicio), [
            'empresa_tercero_id' => $otraEmpresa->id,
            'pdf_servicio' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $servicio->refresh();

    expect($servicio->empresa_tercero_id)->toBe($otraEmpresa->id)
        ->and($servicio->pdf_servicio)->not->toBeNull();
    Storage::disk('public')->assertExists($servicio->pdf_servicio);
});

test('rechaza una empresa tercera de otro tenant al actualizar', function () {
    $servicio = crearServicioTercero($this->bahia);
    $empresaAjena = EmpresaTercero::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('servicio-terceros.update', $servicio), ['empresa_tercero_id' => $empresaAjena->id])
        ->assertSessionHasErrors('empresa_tercero_id');
});

test('finalizar aprobado crea el despacho de la orden de trabajo y cierra el tiempo_servicio', function () {
    $servicio = crearServicioTercero($this->bahia);
    $this->actingAs($this->admin)->post(route('servicio-terceros.iniciar', $servicio));

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio()])
        ->assertRedirect();

    $despacho = Despacho::where('orden_trabajo_id', $servicio->orden_trabajo_id)->first();
    $tiempo = TiempoServicio::where('orden_trabajo_id', $servicio->orden_trabajo_id)->first();

    $servicio->refresh();

    Storage::disk('public')->assertExists($servicio->pdf_servicio);

    expect($servicio->estado_final_equipo)->toBe('APROBADO')
        ->and($despacho)->not->toBeNull()
        ->and($despacho->tecnico_entrega_id)->toBeNull()
        ->and($tiempo->estado_tiempo)->toBe('FIN')
        ->and($tiempo->fin)->not->toBeNull();
});

test('finalizar rechazado con re-agendar crea una nueva orden de trabajo y ningun despacho', function () {
    $servicio = crearServicioTercero($this->bahia);
    $programacionId = $servicio->ordenTrabajo->equipo_programacion_id;

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [
            'estado_final_equipo' => 'RECHAZADO',
            're_agendar' => true,
            'pdf_servicio' => pdfServicio(),
        ])
        ->assertRedirect();

    expect($servicio->refresh()->re_agendar)->toBeTrue()
        ->and(Despacho::count())->toBe(0)
        ->and(OrdenTrabajo::where('equipo_programacion_id', $programacionId)->count())->toBe(2);
});

test('finalizar rechazado sin re-agendar no crea ni orden de trabajo ni despacho', function () {
    $servicio = crearServicioTercero($this->bahia);
    $programacionId = $servicio->ordenTrabajo->equipo_programacion_id;

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'RECHAZADO', 'pdf_servicio' => pdfServicio()])
        ->assertRedirect();

    expect($servicio->refresh()->re_agendar)->toBeFalse()
        ->and(Despacho::count())->toBe(0)
        ->and(OrdenTrabajo::where('equipo_programacion_id', $programacionId)->count())->toBe(1);
});

test('finalizar aprobado una calibracion de tercero marca la calibracion como finalizada y crea el despacho', function () {
    $servicio = crearServicioTercero($this->bahia, ['tipo_servicio' => 'CALIBRACION']);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio()])
        ->assertRedirect();

    expect($servicio->ordenTrabajo->refresh()->calibracion_finalizado)->toBeTrue()
        ->and(Despacho::where('orden_trabajo_id', $servicio->orden_trabajo_id)->count())->toBe(1);
});

test('finalizar rechazado con re-agendar una calibracion de tercero crea la orden de trabajo como devolucion', function () {
    $servicio = crearServicioTercero($this->bahia, ['tipo_servicio' => 'CALIBRACION']);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [
            'estado_final_equipo' => 'RECHAZADO',
            're_agendar' => true,
            'pdf_servicio' => pdfServicio(),
        ])
        ->assertRedirect();

    $nueva = OrdenTrabajo::where('equipo_programacion_id', $servicio->ordenTrabajo->equipo_programacion_id)
        ->where('id', '!=', $servicio->orden_trabajo_id)
        ->first();

    expect($nueva)->not->toBeNull()
        ->and($nueva->devolucion)->toBeTrue()
        ->and(Despacho::count())->toBe(0);
});

test('exige el estado final del equipo y el pdf al finalizar', function () {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [])
        ->assertSessionHasErrors(['estado_final_equipo', 'pdf_servicio']);

    expect(Despacho::count())->toBe(0);
});

test('elimina un servicio de tercero con borrado suave', function () {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->delete(route('servicio-terceros.destroy', $servicio))
        ->assertRedirect();

    expect(ServicioTercero::find($servicio->id))->toBeNull()
        ->and(ServicioTercero::withTrashed()->find($servicio->id))->not->toBeNull();
});

test('no permite operar un servicio de tercero de otro tenant', function () {
    $ajeno = crearServicioTercero(Bahia::factory()->create(), [], 'EQ-AJENO');

    $this->actingAs($this->admin)->post(route('servicio-terceros.iniciar', $ajeno))->assertForbidden();
    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $ajeno), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio()])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('servicio-terceros.destroy', $ajeno))->assertForbidden();
});
