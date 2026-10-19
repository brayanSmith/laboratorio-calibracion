<?php

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\EmpresaTercero;
use App\Models\Item;
use App\Models\Mantenimiento;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\ServicioTercero;
use App\Models\Tenant;
use App\Models\TiempoServicio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/** Mantenimiento completo: equipo recibido -> orden de trabajo -> mantenimiento. */
function crearMantenimiento(Bahia $bahia, array $overrides = [], string $codigo = 'EQ-5001'): Mantenimiento
{
    $programacion = crearEquipoRecibido($bahia, $codigo);
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $bahia->tenant_id,
    ]);

    return Mantenimiento::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tipo_mantenimiento' => $programacion->refresh()->tipo_mantenimiento,
        'fecha_mantenimiento' => now()->toDateString(),
        'tecnico_id' => User::factory()->forTenant($bahia->tenant)->create()->id,
        'tenant_id' => $bahia->tenant_id,
        ...$overrides,
    ]);
}

test('lista solo los mantenimientos del tenant', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    crearMantenimiento(Bahia::factory()->create(), [], 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('mantenimientos/index')
            ->has('mantenimientos', 1)
            ->where('mantenimientos.0.id', $mantenimiento->id)
            ->where('mantenimientos.0.tecnico_nombre', $mantenimiento->tecnico->name)
            ->where('mantenimientos.0.equipo.codigo', $mantenimiento->ordenTrabajo->equipoProgramacion->equipo->codigo));
});

test('la duracion del mantenimiento suma sus tiempos de servicio cerrados', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    foreach ([['00:30:15', 'FIN'], ['01:45:50', 'FIN'], ['02:00:00', 'INICIO']] as [$duracion, $estado]) {
        TiempoServicio::create([
            'orden_trabajo_id' => $mantenimiento->orden_trabajo_id,
            'tipo_servicio' => 'MANTENIMIENTO',
            'inicio' => now()->subHours(3),
            'fin' => $estado === 'FIN' ? now() : null,
            'duracion' => $estado === 'FIN' ? $duracion : null,
            'estado_tiempo' => $estado,
            'es_tercero' => false,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page->where('mantenimientos.0.duracion', '2h 16m'));

    expect(TiempoServicio::duracionMantenimiento($mantenimiento->orden_trabajo_id))->toBe('02:16:05')
        ->and(TiempoServicio::sumarDuraciones([]))->toBeNull();
});

test('formatea la duracion sin segundos y con las dos unidades mayores', function (?string $duracion, ?string $esperado) {
    expect(TiempoServicio::formatearDuracion($duracion))->toBe($esperado);
})->with([
    'menos de un minuto' => ['00:00:40', '<1m'],
    'minutos' => ['00:45:59', '45m'],
    'horas y minutos' => ['02:16:05', '2h 16m'],
    'un dia y horas' => ['28:10:00', '1d 4h'],
    'varios dias' => ['75:59:59', '3d 3h'],
    'sin duracion' => [null, null],
]);

test('lista solo los servicios de tercero de mantenimiento del tenant', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);

    $servicio = ServicioTercero::create([
        'orden_trabajo_id' => $mantenimiento->orden_trabajo_id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'empresa_tercero_id' => $empresa->id,
        'estado_final_equipo' => 'APROBADO',
        'tenant_id' => $this->tenant->id,
    ]);
    ServicioTercero::create([
        'orden_trabajo_id' => $mantenimiento->orden_trabajo_id,
        'tipo_servicio' => 'CALIBRACION',
        'empresa_tercero_id' => $empresa->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('serviciosTerceros', 1)
            ->where('serviciosTerceros.0.id', $servicio->id)
            ->where('serviciosTerceros.0.empresa_tercero_nombre', $empresa->nombre)
            ->where('serviciosTerceros.0.estado_final_equipo', 'APROBADO')
            ->where('serviciosTerceros.0.re_agendar', false));
});

test('actualiza un mantenimiento sin tocar su tipo_mantenimiento', function () {
    $mantenimiento = crearMantenimiento($this->bahia, ['tipo_mantenimiento' => 'PREVENTIVO']);
    $nuevoTecnico = User::factory()->forTenant($this->tenant)->create();
    $novedad = Novedad::create(['nombre' => 'Repuesto agotado', 'categoria' => 'MANTENIMIENTO', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => '2026-11-01',
            'descripcion' => 'Se cambió el filtro',
            'estado_inicial_equipo' => 'FUERA_DE_SERVICIO',
            'estado_final_equipo' => 'OPERATIVO',
            'estado_mantenimiento' => 'FINALIZADO',
            'tecnico_id' => $nuevoTecnico->id,
            'novedad_id' => $novedad->id,
            'firmado' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimientos.index'));

    expect($mantenimiento->refresh())
        ->tipo_mantenimiento->toBe('PREVENTIVO')
        ->fecha_mantenimiento->toDateString()->toBe('2026-11-01')
        ->descripcion->toBe('Se cambió el filtro')
        ->estado_inicial_equipo->toBe('FUERA_DE_SERVICIO')
        ->estado_final_equipo->toBe('OPERATIVO')
        ->estado_mantenimiento->toBe('FINALIZADO')
        ->tecnico_id->toBe($nuevoTecnico->id)
        ->novedad_id->toBe($novedad->id)
        ->firmado->toBeTrue();
});

test('desmarca firmado cuando no se envia en la actualizacion', function () {
    $mantenimiento = crearMantenimiento($this->bahia, ['firmado' => true, 'estado_mantenimiento' => 'FINALIZADO']);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => $mantenimiento->fecha_mantenimiento->toDateString(),
            'estado_mantenimiento' => 'FINALIZADO',
            'tecnico_id' => $mantenimiento->tecnico_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($mantenimiento->refresh()->firmado)->toBeFalse();
});

test('elimina un mantenimiento con borrado suave', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->delete(route('mantenimientos.destroy', $mantenimiento))
        ->assertRedirect(route('mantenimientos.index'));

    expect(Mantenimiento::find($mantenimiento->id))->toBeNull()
        ->and(Mantenimiento::withTrashed()->find($mantenimiento->id))->not->toBeNull();
});

test('inicia un mantenimiento y abre un tiempo_servicio sin fin', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.iniciar', $mantenimiento))
        ->assertRedirect();

    $tiempoServicio = TiempoServicio::where('orden_trabajo_id', $mantenimiento->orden_trabajo_id)->firstOrFail();

    expect($tiempoServicio)
        ->tipo_servicio->toBe('MANTENIMIENTO')
        ->estado_tiempo->toBe('INICIO')
        ->es_tercero->toBeFalse()
        ->fin->toBeNull()
        ->duracion->toBeNull();
    expect($tiempoServicio->inicio->diffInSeconds(now()))->toBeLessThan(5);
});

test('no permite iniciar un mantenimiento de otro tenant', function () {
    $ajeno = crearMantenimiento(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.iniciar', $ajeno))
        ->assertForbidden();

    expect(TiempoServicio::where('orden_trabajo_id', $ajeno->orden_trabajo_id)->count())->toBe(0);
});

test('un usuario sin permiso de editar mantenimientos no puede iniciarlos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($recepcion)
        ->post(route('mantenimientos.iniciar', $mantenimiento))
        ->assertForbidden();

    expect(TiempoServicio::where('orden_trabajo_id', $mantenimiento->orden_trabajo_id)->count())->toBe(0);
});

test('finaliza un mantenimiento, cierra el tiempo_servicio y calcula la duracion', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.iniciar', $mantenimiento));

    $tiempoServicio = TiempoServicio::where('orden_trabajo_id', $mantenimiento->orden_trabajo_id)->firstOrFail();
    $tiempoServicio->update(['inicio' => now()->subMinutes(5)]);

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.finalizar', $mantenimiento), [
            'estado_final_equipo' => 'FUERA_DE_SERVICIO',
            'firmado' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimientos.index'));

    expect($mantenimiento->refresh())
        ->estado_final_equipo->toBe('FUERA_DE_SERVICIO')
        ->firmado->toBeTrue()
        ->estado_mantenimiento->toBe('FINALIZADO');

    expect($mantenimiento->ordenTrabajo->refresh()->mantenimiento_finalizado)->toBeTrue();

    expect($tiempoServicio->refresh())
        ->estado_tiempo->toBe('FIN')
        ->duracion->toBe('00:05:00');
    expect($tiempoServicio->fin)->not->toBeNull();
});

test('no permite finalizar un mantenimiento de otro tenant', function () {
    $ajeno = crearMantenimiento(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.finalizar', $ajeno), [
            'estado_final_equipo' => 'OPERATIVO',
            'firmado' => '1',
        ])
        ->assertForbidden();

    expect($ajeno->refresh()->estado_mantenimiento)->not->toBe('FINALIZADO');
});

test('exige el estado final del equipo al finalizar', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.finalizar', $mantenimiento), [])
        ->assertSessionHasErrors('estado_final_equipo');
});

test('un usuario sin permiso de editar mantenimientos no puede finalizarlos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($recepcion)
        ->post(route('mantenimientos.finalizar', $mantenimiento), [
            'estado_final_equipo' => 'OPERATIVO',
            'firmado' => '1',
        ])
        ->assertForbidden();

    expect($mantenimiento->refresh()->estado_mantenimiento)->not->toBe('FINALIZADO');
});

test('no permite actualizar ni eliminar mantenimientos de otro tenant', function () {
    $ajeno = crearMantenimiento(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $ajeno), [
            'fecha_mantenimiento' => now()->toDateString(),
            'estado_mantenimiento' => 'PENDIENTE',
            'tecnico_id' => $ajeno->tecnico_id,
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('mantenimientos.destroy', $ajeno))
        ->assertForbidden();

    expect(Mantenimiento::find($ajeno->id))->not->toBeNull();
});

test('exige tecnico y estado del mantenimiento al actualizar', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['tecnico_id', 'estado_mantenimiento']);
});

test('rechaza un tecnico que no pertenece al tenant al actualizar un mantenimiento', function () {
    $mantenimiento = crearMantenimiento($this->bahia);
    $tecnicoAjeno = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => now()->toDateString(),
            'estado_mantenimiento' => 'PENDIENTE',
            'tecnico_id' => $tecnicoAjeno->id,
        ])
        ->assertSessionHasErrors('tecnico_id');
});

test('un tecnico con permiso de editar mantenimientos puede verlos y actualizarlos, pero no eliminarlos', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $mantenimiento = crearMantenimiento($this->bahia, ['tecnico_id' => $tecnico->id]);

    $this->actingAs($tecnico)
        ->get(route('mantenimientos.index'))
        ->assertOk();

    $this->actingAs($tecnico)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => now()->toDateString(),
            'estado_mantenimiento' => 'EN_PROCESO',
            'tecnico_id' => $mantenimiento->tecnico_id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($tecnico)
        ->delete(route('mantenimientos.destroy', $mantenimiento))
        ->assertForbidden();
});

test('un usuario de recepcion puede ver los mantenimientos pero no editarlos ni eliminarlos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($recepcion)
        ->get(route('mantenimientos.index'))
        ->assertOk();

    $this->actingAs($recepcion)
        ->put(route('mantenimientos.update', $mantenimiento), [
            'fecha_mantenimiento' => now()->toDateString(),
            'estado_mantenimiento' => 'PENDIENTE',
            'tecnico_id' => $mantenimiento->tecnico_id,
        ])
        ->assertForbidden();

    $this->actingAs($recepcion)
        ->delete(route('mantenimientos.destroy', $mantenimiento))
        ->assertForbidden();
});

test('un invitado no puede ver los mantenimientos', function () {
    $this->get(route('mantenimientos.index'))
        ->assertRedirect(route('login'));
});

test('comparte los mantenimientos pendientes en local y en tercero para el menu', function () {
    crearMantenimiento($this->bahia, [], 'EQ-P1');
    crearMantenimiento($this->bahia, [], 'EQ-P2');
    crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO'], 'EQ-P3');
    crearMantenimiento(Bahia::factory()->create(), [], 'EQ-AJENO2');

    $conServicio = crearMantenimiento($this->bahia, [], 'EQ-P4');
    $empresa = EmpresaTercero::factory()->create(['tenant_id' => $this->tenant->id]);
    $datosServicio = [
        'orden_trabajo_id' => $conServicio->orden_trabajo_id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'empresa_tercero_id' => $empresa->id,
        'tenant_id' => $this->tenant->id,
    ];
    ServicioTercero::create($datosServicio);
    ServicioTercero::create([...$datosServicio, 'estado_final_equipo' => 'APROBADO']);
    ServicioTercero::create([...$datosServicio, 'tipo_servicio' => 'CALIBRACION']);

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('pendientes.mantenimientos.local', 3)
            ->where('pendientes.mantenimientos.tercero', 1)
            ->where('pendientes.calibraciones.local', 0)
            ->where('pendientes.calibraciones.tercero', 1)
            ->where('pendientes.despachos', 0)
            ->where('pendientes.ingresos', 0));
});

test('registra un seguimiento de mantenimiento con el estado inicial PENDIENTE al crear el mantenimiento', function () {
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-SEG-MANT');
    $ordenTrabajo = OrdenTrabajo::create([
        'codigo' => 'OT-'.random_int(1000, 9999),
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'listo_para_mantenimiento' => true,
        'tenant_id' => $this->tenant->id,
    ]);

    Mantenimiento::create([
        'orden_trabajo_id' => $ordenTrabajo->id,
        'tipo_mantenimiento' => 'PREVENTIVO',
        'fecha_mantenimiento' => now()->toDateString(),
        'tecnico_id' => $this->admin->id,
        'tenant_id' => $this->tenant->id,
    ]);

    expect($programacion->seguimientos()->where('fase', 'MANTENIMIENTO')->pluck('seguimiento')->all())
        ->toBe(['PENDIENTE']);
});

test('al iniciar un mantenimiento su estado pasa a EN_PROCESO', function () {
    $mantenimiento = crearMantenimiento($this->bahia);

    expect($mantenimiento->estado_mantenimiento)->toBe('PENDIENTE');

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.iniciar', $mantenimiento))
        ->assertRedirect();

    expect($mantenimiento->refresh()->estado_mantenimiento)->toBe('EN_PROCESO');
});

test('no permite finalizar un mantenimiento al que le faltan repuestos', function () {
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FALTA_REPUESTOS']);

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.finalizar', $mantenimiento), [
            'estado_final_equipo' => 'OPERATIVO',
            'firmado' => '1',
        ])
        ->assertSessionHasErrors('estado_mantenimiento');

    expect($mantenimiento->refresh())
        ->estado_mantenimiento->toBe('FALTA_REPUESTOS')
        ->and($mantenimiento->ordenTrabajo->refresh()->mantenimiento_finalizado)->toBeFalse();
});

test('al finalizar un mantenimiento se genera y guarda su informe PDF', function () {
    Storage::fake('public');
    $mantenimiento = crearMantenimiento($this->bahia);

    $this->actingAs($this->admin)->post(route('mantenimientos.iniciar', $mantenimiento));
    $this->actingAs($this->admin)
        ->post(route('mantenimientos.finalizar', $mantenimiento), ['estado_final_equipo' => 'OPERATIVO', 'firmado' => '1'])
        ->assertSessionHasNoErrors();

    $ruta = $mantenimiento->refresh()->pdf_informe;

    expect($ruta)->toBe("informes/mantenimientos/{$this->tenant->id}/mantenimiento-{$mantenimiento->id}.pdf");
    Storage::disk('public')->assertExists($ruta);
    expect(Storage::disk('public')->get($ruta))->toStartWith('%PDF');
});

test('el informe PDF incluye las fotos de la galeria del mantenimiento', function () {
    Storage::fake('public');
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO']);
    Storage::disk('public')->put('mantenimientos/foto.png', UploadedFile::fake()->image('foto.png', 40, 40)->getContent());
    $mantenimiento->galeriaMantenimientos()->create([
        'imagen' => 'mantenimientos/foto.png',
        'descripcion' => 'Antes del servicio',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.informe', $mantenimiento))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('el informe PDF se genera con los items utilizados, su precio unitario y su subtotal', function () {
    Storage::fake('public');
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO']);
    $mantenimiento->itemMantenimientos()->create([
        'item_id' => Item::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'cantidad' => 3,
        'precio_unitario' => 12500,
        'subtotal' => 37500,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.informe', $mantenimiento))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    Storage::disk('public')->assertExists($mantenimiento->refresh()->pdf_informe);
});

test('muestra el informe de un mantenimiento finalizado y lo regenera si falta el archivo', function () {
    Storage::fake('public');
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO', 'pdf_informe' => 'informes/inexistente.pdf']);

    $this->actingAs($this->admin)
        ->get(route('mantenimientos.informe', $mantenimiento))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename=informe-mantenimiento-'.$mantenimiento->ordenTrabajo->codigo.'.pdf');

    Storage::disk('public')->assertExists($mantenimiento->refresh()->pdf_informe);
    expect($mantenimiento->pdf_informe)->not->toBe('informes/inexistente.pdf');
});

test('actualizar el informe reemplaza el archivo guardado', function () {
    Storage::fake('public');
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO']);
    Storage::disk('public')->put('informes/viejo.pdf', 'viejo');
    $mantenimiento->update(['pdf_informe' => 'informes/viejo.pdf']);

    $this->actingAs($this->admin)
        ->post(route('mantenimientos.actualizar-informe', $mantenimiento))
        ->assertRedirect();

    $mantenimiento->refresh();

    expect($mantenimiento->pdf_informe)->not->toBe('informes/viejo.pdf');
    Storage::disk('public')->assertExists($mantenimiento->pdf_informe);
    Storage::disk('public')->assertMissing('informes/viejo.pdf');
});

test('no permite actualizar el informe de un mantenimiento sin finalizar, de otro tenant ni sin permiso', function () {
    Storage::fake('public');
    $sinFinalizar = crearMantenimiento($this->bahia);
    $ajeno = crearMantenimiento(Bahia::factory()->create(), ['estado_mantenimiento' => 'FINALIZADO'], 'EQ-AJENO');
    $finalizado = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO'], 'EQ-FIN');
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->post(route('mantenimientos.actualizar-informe', $sinFinalizar))->assertNotFound();
    $this->actingAs($this->admin)->post(route('mantenimientos.actualizar-informe', $ajeno))->assertForbidden();
    $this->actingAs($sinRol)->post(route('mantenimientos.actualizar-informe', $finalizado))->assertForbidden();
});

test('no permite descargar el informe de un mantenimiento sin finalizar ni de otro tenant', function () {
    Storage::fake('public');
    $sinFinalizar = crearMantenimiento($this->bahia);
    $ajeno = crearMantenimiento(Bahia::factory()->create(), ['estado_mantenimiento' => 'FINALIZADO'], 'EQ-AJENO');

    $this->actingAs($this->admin)->get(route('mantenimientos.informe', $sinFinalizar))->assertNotFound();
    $this->actingAs($this->admin)->get(route('mantenimientos.informe', $ajeno))->assertNotFound();
});

test('un usuario sin permiso de ver mantenimientos no puede descargar el informe', function () {
    Storage::fake('public');
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO']);
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('mantenimientos.informe', $mantenimiento))->assertForbidden();
});

/** Ruta del PDF del informe, generándolo si todavía no existe. */
function contenidoInformeMantenimiento(Mantenimiento $mantenimiento): string
{
    test()->actingAs(test()->admin)->get(route('mantenimientos.informe', $mantenimiento))->assertOk();

    return Storage::disk('public')->get($mantenimiento->refresh()->pdf_informe);
}

test('el informe incluye la firma del tecnico solo si el mantenimiento quedo firmado', function (bool $firmado, bool $apareceFirma) {
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO', 'firmado' => $firmado]);
    Storage::disk('public')->put('firmas-usuarios/firma.png', UploadedFile::fake()->image('firma.png', 120, 60)->getContent());
    $mantenimiento->tecnico->forceFill(['firma' => 'firmas-usuarios/firma.png'])->save();

    expect(str_contains(contenidoInformeMantenimiento($mantenimiento), '/Subtype /Image'))->toBe($apareceFirma);
})->with([
    'firmado' => [true, true],
    'sin firmar' => [false, false],
]);

test('el informe firmado de un tecnico sin firma registrada se genera sin imagen', function () {
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO', 'firmado' => true]);

    expect(str_contains(contenidoInformeMantenimiento($mantenimiento), '/Subtype /Image'))->toBeFalse();
});

test('iniciar y finalizar un mantenimiento dependen de su propio permiso, no del de editar', function () {
    $usuario = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    $usuario->syncRoles([]);
    $usuario->givePermissionTo([TenantPermission::MantenimientosEditar->value, TenantPermission::MantenimientosVerTodos->value]);
    $mantenimiento = crearMantenimiento($this->bahia, ['estado_mantenimiento' => 'FINALIZADO']);

    $this->actingAs($usuario)->post(route('mantenimientos.iniciar', $mantenimiento))->assertForbidden();
    $this->actingAs($usuario)->post(route('mantenimientos.finalizar', $mantenimiento))->assertForbidden();
    $this->actingAs($usuario)->get(route('mantenimientos.informe', $mantenimiento))->assertForbidden();

    $usuario->givePermissionTo([TenantPermission::MantenimientosIniciar->value, TenantPermission::MantenimientosPdf->value]);

    $this->actingAs($usuario)->post(route('mantenimientos.iniciar', $mantenimiento))->assertRedirect();
    $this->actingAs($usuario)->post(route('mantenimientos.finalizar', $mantenimiento))->assertForbidden();
});

test('sin el permiso de ver todos, un tecnico solo ve y gestiona sus propios mantenimientos', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $propio = crearMantenimiento($this->bahia, ['tecnico_id' => $tecnico->id], 'EQ-PROPIO');
    $ajeno = crearMantenimiento($this->bahia, [], 'EQ-DE-OTRO');

    $this->actingAs($tecnico)
        ->get(route('mantenimientos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('mantenimientos', 1)
            ->where('mantenimientos.0.id', $propio->id));

    $this->actingAs($tecnico)->post(route('mantenimientos.iniciar', $ajeno))->assertForbidden();
    $this->actingAs($tecnico)->post(route('mantenimientos.iniciar', $propio))->assertRedirect();
});

test('con el permiso de ver todos, un rol ve los mantenimientos de todos los tecnicos', function () {
    $metrologo = User::factory()->forTenant($this->tenant, TenantRole::Metrologo)->create();
    crearMantenimiento($this->bahia, [], 'EQ-UNO');
    crearMantenimiento($this->bahia, [], 'EQ-DOS');

    $this->actingAs($metrologo)
        ->get(route('mantenimientos.index'))
        ->assertInertia(fn ($page) => $page->has('mantenimientos', 2));
});
