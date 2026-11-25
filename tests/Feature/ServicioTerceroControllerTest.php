<?php

use App\Actions\Etiquetas\GenerarEtiquetaServicio;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\OrdenesTrabajo\Models\TiempoServicio;
use App\Modules\ServiciosTerceros\Http\Controllers\ServicioTerceroController;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

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

test('registra el numero de certificado y la fecha en que el tercero hizo el servicio', function (string $tipoServicio) {
    $servicio = crearServicioTercero($this->bahia, ['tipo_servicio' => $tipoServicio]);

    $this->actingAs($this->admin)
        ->put(route('servicio-terceros.update', $servicio), [
            'empresa_tercero_id' => $servicio->empresa_tercero_id,
            'numero_certificado' => 'CERT-2026-0154',
            'fecha_servicio' => '2026-11-20',
        ])
        ->assertSessionHasNoErrors();

    expect($servicio->refresh())
        ->numero_certificado->toBe('CERT-2026-0154')
        ->fecha_servicio->toDateString()->toBe('2026-11-20');

    expect(ServicioTerceroController::listar($this->tenant->id, $tipoServicio)->sole())
        ->toMatchArray(['numero_certificado' => 'CERT-2026-0154', 'fecha_servicio' => '2026-11-20']);
})->with(['MANTENIMIENTO', 'CALIBRACION']);

test('la fecha del servicio de tercero no puede ser futura', function () {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('servicio-terceros.update', $servicio), [
            'empresa_tercero_id' => $servicio->empresa_tercero_id,
            'fecha_servicio' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('fecha_servicio');
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
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio(), 'numero_certificado' => 'CERT-1', 'fecha_servicio' => now()->toDateString()])
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

test('finalizar fuera de servicio con re-agendar crea una nueva orden de trabajo y ningun despacho', function () {
    $servicio = crearServicioTercero($this->bahia);
    $programacionId = $servicio->ordenTrabajo->equipo_programacion_id;

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [
            'estado_final_equipo' => 'FUERA_DE_SERVICIO',
            're_agendar' => true,
            'pdf_servicio' => pdfServicio(),
        ])
        ->assertRedirect();

    expect($servicio->refresh()->re_agendar)->toBeTrue()
        ->and(Despacho::count())->toBe(0)
        ->and(OrdenTrabajo::where('equipo_programacion_id', $programacionId)->count())->toBe(2);
});

test('finalizar fuera de servicio sin re-agendar no crea ni orden de trabajo ni despacho', function () {
    $servicio = crearServicioTercero($this->bahia);
    $programacionId = $servicio->ordenTrabajo->equipo_programacion_id;

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'FUERA_DE_SERVICIO', 'pdf_servicio' => pdfServicio()])
        ->assertRedirect();

    expect($servicio->refresh()->re_agendar)->toBeFalse()
        ->and(Despacho::count())->toBe(0)
        ->and(OrdenTrabajo::where('equipo_programacion_id', $programacionId)->count())->toBe(1);
});

test('finalizar aprobado una calibracion de tercero marca la calibracion como finalizada y crea el despacho', function () {
    $servicio = crearServicioTercero($this->bahia, ['tipo_servicio' => 'CALIBRACION']);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio(), 'numero_certificado' => 'CERT-1', 'fecha_servicio' => now()->toDateString()])
        ->assertRedirect();

    expect($servicio->ordenTrabajo->refresh()->calibracion_finalizado)->toBeTrue()
        ->and(Despacho::where('orden_trabajo_id', $servicio->orden_trabajo_id)->count())->toBe(1);
});

test('finalizar fuera de servicio con re-agendar una calibracion de tercero crea la orden de trabajo como devolucion', function () {
    $servicio = crearServicioTercero($this->bahia, ['tipo_servicio' => 'CALIBRACION']);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [
            'estado_final_equipo' => 'FUERA_DE_SERVICIO',
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
        ->post(route('servicio-terceros.finalizar', $ajeno), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio(), 'numero_certificado' => 'CERT-1', 'fecha_servicio' => now()->toDateString()])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('servicio-terceros.destroy', $ajeno))->assertForbidden();
});

test('el servicio de tercero pasa de PENDIENTE a EN_PROCESO a FINALIZADO y registra cada paso en el seguimiento', function () {
    $servicio = crearServicioTercero($this->bahia);
    $servicio->ordenTrabajo->update(['listo_para_mantenimiento' => true]);

    expect($servicio->refresh()->estado_servicio)->toBe('PENDIENTE');

    $this->actingAs($this->admin)->post(route('servicio-terceros.iniciar', $servicio));
    expect($servicio->refresh()->estado_servicio)->toBe('EN_PROCESO');

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio(), 'numero_certificado' => 'CERT-1', 'fecha_servicio' => now()->toDateString()])
        ->assertRedirect();
    expect($servicio->refresh()->estado_servicio)->toBe('FINALIZADO');

    $seguimientos = $servicio->ordenTrabajo->equipoProgramacion->seguimientos()
        ->where('fase', 'MANTENIMIENTO')->orderBy('id')->pluck('seguimiento')->all();

    expect($seguimientos)->toBe(['EN_PROCESO', 'FINALIZADO']);
});

test('registra un seguimiento PENDIENTE al agendar un servicio de tercero de calibracion', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-7002');
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'listo_para_calibracion' => true,
        'tenant_id' => $this->tenant->id,
    ]);

    ServicioTercero::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tipo_servicio' => 'CALIBRACION',
        'empresa_tercero_id' => EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'tenant_id' => $this->tenant->id,
    ]);

    expect($programacion->seguimientos()->where('fase', 'CALIBRACION')->pluck('seguimiento')->all())->toBe(['PENDIENTE']);
});

test('guarda el codigo de compra y de despacho de un servicio de tercero de mantenimiento', function () {
    $servicio = crearServicioTercero($this->bahia);
    $this->actingAs($this->admin)->post(route('servicio-terceros.iniciar', $servicio));

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.guardar-codigos', $servicio), ['codigo_compra' => 'OC-100', 'codigo_despacho' => ''])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($servicio->refresh())
        ->codigo_compra->toBe('OC-100')
        ->codigo_despacho->toBeNull()
        ->estado_servicio->toBe('EN_PROCESO');
});

test('los codigos no son obligatorios pero tienen un maximo de 50 caracteres', function () {
    $servicio = crearServicioTercero($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.guardar-codigos', $servicio), [])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.guardar-codigos', $servicio), ['codigo_compra' => str_repeat('a', 51)])
        ->assertSessionHasErrors('codigo_compra');
});

test('los codigos no aplican a un servicio de tercero de calibracion', function () {
    $servicio = crearServicioTercero($this->bahia, ['tipo_servicio' => 'CALIBRACION']);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.guardar-codigos', $servicio), ['codigo_compra' => 'OC-100'])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [
            'estado_final_equipo' => 'APROBADO',
            'pdf_servicio' => pdfServicio(),
            'numero_certificado' => 'CERT-1',
            'fecha_servicio' => now()->toDateString(),
            'codigo_compra' => 'OC-100',
        ])
        ->assertRedirect();

    expect($servicio->refresh()->codigo_compra)->toBeNull();
});

test('finalizar un servicio de mantenimiento conserva los codigos del agendamiento y sigue exigiendo el pdf', function () {
    $servicio = crearServicioTercero($this->bahia, ['codigo_compra' => 'SOL-1', 'codigo_despacho' => 'OB-9']);
    $this->actingAs($this->admin)->post(route('servicio-terceros.iniciar', $servicio));

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'APROBADO'])
        ->assertSessionHasErrors('pdf_servicio');

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [
            'estado_final_equipo' => 'APROBADO',
            'pdf_servicio' => pdfServicio(),
            'numero_certificado' => 'CERT-1',
            'fecha_servicio' => now()->toDateString(),
            'codigo_compra' => 'OTRO',
        ])
        ->assertRedirect();

    expect($servicio->refresh())->codigo_compra->toBe('SOL-1')->codigo_despacho->toBe('OB-9');
});

test('al editar un servicio de mantenimiento se corrigen el Solped y la OB, pero no en uno de calibracion', function () {
    $mantenimiento = crearServicioTercero($this->bahia, ['codigo_compra' => 'SOL-1']);
    $calibracion = crearServicioTercero($this->bahia, ['tipo_servicio' => 'CALIBRACION'], 'EQ-7002');

    $this->actingAs($this->admin)
        ->put(route('servicio-terceros.update', $mantenimiento), [
            'empresa_tercero_id' => $mantenimiento->empresa_tercero_id,
            'codigo_compra' => 'SOL-2',
            'codigo_despacho' => 'OB-3',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->put(route('servicio-terceros.update', $calibracion), [
            'empresa_tercero_id' => $calibracion->empresa_tercero_id,
            'codigo_compra' => 'SOL-X',
        ])
        ->assertSessionHasNoErrors();

    expect($mantenimiento->refresh())->codigo_compra->toBe('SOL-2')->codigo_despacho->toBe('OB-3')
        ->and($calibracion->refresh()->codigo_compra)->toBeNull();
});

test('un usuario sin permiso de finalizar no puede guardar los codigos', function () {
    $servicio = crearServicioTercero($this->bahia);
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)
        ->post(route('servicio-terceros.guardar-codigos', $servicio), ['codigo_compra' => 'OC-1'])
        ->assertForbidden();
});

test('sin el permiso de terceros del modulo no se ven ni se gestionan sus servicios de tercero', function () {
    $servicio = crearServicioTercero($this->bahia);
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::MantenimientosVer, TenantPermission::MantenimientosIniciar]);

    $this->actingAs($usuario)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page->has('serviciosTerceros', 0));
    $this->actingAs($usuario)->post(route('servicio-terceros.iniciar', $servicio))->assertForbidden();

    $usuario->givePermissionTo(TenantPermission::MantenimientosTerceros->value);

    $this->actingAs($usuario)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page->has('serviciosTerceros', 1));
    $this->actingAs($usuario)->post(route('servicio-terceros.iniciar', $servicio))->assertRedirect();
});

test('el listado de servicios de tercero trae el tipo de equipo con su clase de mantenimiento', function () {
    $servicio = crearServicioTercero($this->bahia);
    $tipoEquipo = $servicio->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo;

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('serviciosTerceros.0.equipo_tipo', $tipoEquipo->nombre)
            ->where('serviciosTerceros.0.equipo_clase', $tipoEquipo->tipo_mantenimiento));
});

/** Servicio de tercero finalizado y aprobado, con su documento, sobre un equipo del tipo dado. */
function crearServicioTerceroAprobado(Bahia $bahia, string $tipoServicio, string $tipoMantenimiento): ServicioTercero
{
    $servicio = crearServicioTercero($bahia, [
        'tipo_servicio' => $tipoServicio,
        'estado_servicio' => 'FINALIZADO',
        'estado_final_equipo' => 'APROBADO',
        'numero_certificado' => 'CERT-TER-88',
        'fecha_servicio' => '2026-11-20',
        'pdf_servicio' => pdfServicio()->store('servicios-terceros', 'public'),
    ]);
    $servicio->ordenTrabajo->equipoProgramacion->equipo->tipoEquipo->update(['tipo_mantenimiento' => $tipoMantenimiento]);

    return $servicio;
}

test('genera la etiqueta de un servicio de tercero con su certificado, fecha y laboratorio, y su QR abre el documento del tercero', function (string $tipoServicio, string $tipoMantenimiento, string $rotuloFecha, string $color) {
    $servicio = crearServicioTerceroAprobado($this->bahia, $tipoServicio, $tipoMantenimiento);

    $vista = null;
    View::creator('pdf.etiqueta', function ($view) use (&$vista): void {
        $vista = $view->getData();
    });

    $respuesta = $this->actingAs($this->admin)
        ->get(route('servicio-terceros.etiqueta', $servicio))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($respuesta->getContent())->toStartWith('%PDF')
        ->and($vista['datos'])->toMatchArray([
            $rotuloFecha => '20/11/2026',
            'Cert. N°' => 'CERT-TER-88',
            'Laboratorio' => $servicio->empresaTercero->nombre,
        ])
        ->and($vista['color'])->toBe($color);

    auth()->logout();

    $this->get(URL::signedRoute('etiqueta.servicio-tercero.documento', ['servicio' => $servicio->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->get(route('etiqueta.servicio-tercero.documento', ['servicio' => $servicio->id]))->assertForbidden();
})->with([
    'mantenimiento de equipo tipo B' => ['MANTENIMIENTO', 'B', 'Fecha mantenimiento', GenerarEtiquetaServicio::COLOR_MANTENIMIENTO],
    'mantenimiento de equipo tipo A' => ['MANTENIMIENTO', 'A', 'Fecha mantenimiento', GenerarEtiquetaServicio::COLOR_MANTENIMIENTO],
    'calibracion de equipo tipo A' => ['CALIBRACION', 'A', 'Fecha calibración', GenerarEtiquetaServicio::COLOR_CALIBRACION],
    'calibracion de equipo tipo B' => ['CALIBRACION', 'B', 'Fecha calibración', GenerarEtiquetaServicio::COLOR_CALIBRACION],
]);

test('no genera etiqueta de tercero si el servicio no quedo aprobado', function (array $cambios) {
    $servicio = crearServicioTerceroAprobado($this->bahia, 'MANTENIMIENTO', 'B');
    $servicio->update($cambios);

    $this->actingAs($this->admin)->get(route('servicio-terceros.etiqueta', $servicio))->assertNotFound();
})->with([
    'fuera de servicio' => [['estado_final_equipo' => 'FUERA_DE_SERVICIO']],
    'sin finalizar' => [['estado_servicio' => 'EN_PROCESO', 'estado_final_equipo' => null]],
]);

test('al finalizar aprobado se registran el numero de certificado y la fecha del servicio, y son obligatorios', function () {
    $servicio = crearServicioTercero($this->bahia);
    $this->actingAs($this->admin)->post(route('servicio-terceros.iniciar', $servicio));

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'APROBADO', 'pdf_servicio' => pdfServicio()])
        ->assertSessionHasErrors(['numero_certificado', 'fecha_servicio']);

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), [
            'estado_final_equipo' => 'APROBADO',
            'pdf_servicio' => pdfServicio(),
            'numero_certificado' => 'CERT-2026-77',
            'fecha_servicio' => '2026-11-21',
        ])
        ->assertSessionHasNoErrors();

    expect($servicio->refresh())
        ->numero_certificado->toBe('CERT-2026-77')
        ->fecha_servicio->toDateString()->toBe('2026-11-21');
});

test('al finalizar fuera de servicio el numero de certificado y la fecha son opcionales', function () {
    $servicio = crearServicioTercero($this->bahia);
    $this->actingAs($this->admin)->post(route('servicio-terceros.iniciar', $servicio));

    $this->actingAs($this->admin)
        ->post(route('servicio-terceros.finalizar', $servicio), ['estado_final_equipo' => 'FUERA_DE_SERVICIO', 'pdf_servicio' => pdfServicio()])
        ->assertSessionHasNoErrors();
});

test('la etiqueta de tercero exige el permiso de pdf del modulo', function () {
    $servicio = crearServicioTerceroAprobado($this->bahia, 'MANTENIMIENTO', 'B');
    $sinPermiso = usuarioConPermisos($this->tenant, [
        TenantPermission::MantenimientosVer, TenantPermission::MantenimientosTerceros,
    ]);

    $this->actingAs($sinPermiso)->get(route('servicio-terceros.etiqueta', $servicio))->assertForbidden();
});
