<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Despacho;
use App\Models\EquipoProgramacion;
use App\Models\Novedad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    $this->cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('lista solo los despachos del tenant', function () {
    $despacho = crearDespachoListo($this->bahia, [], 'EQ-LISTO');
    crearDespachoListo(Bahia::factory()->create(), [], 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->get(route('despachos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('despachos/index')
            ->has('despachos', 1)
            ->where('despachos.0.id', $despacho->id)
            ->where('despachos.0.entrega_autorizada', true)
            ->where('despachos.0.equipo.codigo', 'EQ-LISTO'));
});

test('actualiza un despacho', function () {
    $despacho = crearDespachoListo($this->bahia, ['entrega_autorizada' => false]);
    $tecnico = User::factory()->forTenant($this->tenant)->create();
    $novedad = Novedad::factory()->create(['tenant_id' => $this->tenant->id, 'categoria' => 'SALIDA']);

    $this->actingAs($this->admin)
        ->put(route('despachos.update', $despacho), [
            'tecnico_entrega_id' => $tecnico->id,
            'entrega_autorizada' => '1',
            'cliente_recibe_id' => $this->cliente->id,
            'entrega_recibida' => '1',
            'novedad_id' => $novedad->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('despachos.index'));

    expect($despacho->refresh())
        ->tecnico_entrega_id->toBe($tecnico->id)
        ->entrega_autorizada->toBeTrue()
        ->cliente_recibe_id->toBe($this->cliente->id)
        ->entrega_recibida->toBeTrue()
        ->estado_despacho->toBe('ENTREGADO')
        ->novedad_id->toBe($novedad->id);

    expect($despacho->ordenTrabajo->equipoProgramacion->refresh())
        ->fase_programacion->toBe('DESPACHO')
        ->subfase_programacion->toBe('Entregado');
});

test('actualiza la firma de un despacho con una imagen', function () {
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('despachos.update', $despacho), [
            'firma_cliente_recibe' => UploadedFile::fake()->image('firma.png'),
        ])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists($despacho->refresh()->firma_cliente_recibe);
});

test('rechaza una firma que no es una imagen al actualizar un despacho', function () {
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($this->admin)
        ->put(route('despachos.update', $despacho), [
            'firma_cliente_recibe' => UploadedFile::fake()->create('firma.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('firma_cliente_recibe');
});

test('reemplaza la firma anterior al volver a actualizar un despacho', function () {
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($this->admin)->put(route('despachos.update', $despacho), [
        'firma_cliente_recibe' => UploadedFile::fake()->image('a.png'),
    ]);
    $firmaAnterior = $despacho->fresh()->firma_cliente_recibe;

    $this->actingAs($this->admin)->put(route('despachos.update', $despacho), [
        'firma_cliente_recibe' => UploadedFile::fake()->image('b.png'),
    ]);

    $despacho->refresh();

    expect($despacho->firma_cliente_recibe)->not->toBe($firmaAnterior);
    Storage::disk('public')->assertMissing($firmaAnterior);
    Storage::disk('public')->assertExists($despacho->firma_cliente_recibe);
});

test('conserva la firma al actualizar sin enviar una nueva y la quita si se pide', function () {
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($this->admin)->put(route('despachos.update', $despacho), [
        'firma_cliente_recibe' => UploadedFile::fake()->image('a.png'),
    ]);
    $firma = $despacho->fresh()->firma_cliente_recibe;

    $this->actingAs($this->admin)->put(route('despachos.update', $despacho), []);

    expect($despacho->fresh()->firma_cliente_recibe)->toBe($firma);

    $this->actingAs($this->admin)->put(route('despachos.update', $despacho), [
        'eliminar_firma' => true,
    ]);

    expect($despacho->fresh()->firma_cliente_recibe)->toBeNull();
    Storage::disk('public')->assertMissing($firma);
});

test('elimina un despacho con borrado suave y borra su firma', function () {
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($this->admin)->put(route('despachos.update', $despacho), [
        'firma_cliente_recibe' => UploadedFile::fake()->image('firma.png'),
    ]);
    $firma = $despacho->fresh()->firma_cliente_recibe;

    $this->actingAs($this->admin)
        ->delete(route('despachos.destroy', $despacho))
        ->assertRedirect(route('despachos.index'));

    expect(Despacho::find($despacho->id))->toBeNull()
        ->and(Despacho::withTrashed()->find($despacho->id))->not->toBeNull();
    Storage::disk('public')->assertMissing($firma);
});

test('no permite actualizar ni eliminar despachos de otro tenant', function () {
    $ajeno = crearDespachoListo(Bahia::factory()->create());

    $this->actingAs($this->admin)
        ->put(route('despachos.update', $ajeno), ['entrega_autorizada' => '1'])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('despachos.destroy', $ajeno))
        ->assertForbidden();

    expect(Despacho::find($ajeno->id))->not->toBeNull();
});

test('rechaza un tecnico que no pertenece al tenant al actualizar un despacho', function () {
    $despacho = crearDespachoListo($this->bahia);
    $tecnicoAjeno = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('despachos.update', $despacho), ['tecnico_entrega_id' => $tecnicoAjeno->id])
        ->assertSessionHasErrors('tecnico_entrega_id');
});

test('rechaza una novedad que no es de categoria salida al actualizar un despacho', function () {
    $despacho = crearDespachoListo($this->bahia);
    $novedadIngreso = Novedad::factory()->create(['tenant_id' => $this->tenant->id, 'categoria' => 'INGRESO']);

    $this->actingAs($this->admin)
        ->put(route('despachos.update', $despacho), ['novedad_id' => $novedadIngreso->id])
        ->assertSessionHasErrors('novedad_id');
});

test('un tecnico con permiso de editar despachos puede verlos y actualizarlos, pero no eliminarlos', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($tecnico)
        ->get(route('despachos.index'))
        ->assertOk();

    $this->actingAs($tecnico)
        ->put(route('despachos.update', $despacho), ['entrega_autorizada' => '1'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($tecnico)
        ->delete(route('despachos.destroy', $despacho))
        ->assertForbidden();
});

test('un usuario de recepcion puede ver los despachos pero no editarlos ni eliminarlos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $despacho = crearDespachoListo($this->bahia);

    $this->actingAs($recepcion)
        ->get(route('despachos.index'))
        ->assertOk();

    $this->actingAs($recepcion)
        ->put(route('despachos.update', $despacho), ['entrega_autorizada' => '1'])
        ->assertForbidden();

    $this->actingAs($recepcion)
        ->delete(route('despachos.destroy', $despacho))
        ->assertForbidden();
});

test('un invitado no puede ver los despachos', function () {
    $this->get(route('despachos.index'))
        ->assertRedirect(route('login'));
});

test('calcula el estado del despacho a partir de entrega autorizada y entrega recibida', function (bool $autorizada, bool $recibida, string $estado) {
    $despacho = crearDespachoListo($this->bahia, ['entrega_autorizada' => $autorizada, 'entrega_recibida' => $recibida]);

    expect($despacho->refresh()->estado_despacho)->toBe($estado);
})->with([
    'sin autorizar' => [false, false, 'PENDIENTE'],
    'autorizado' => [true, false, 'AUTORIZADO'],
    'entregado' => [true, true, 'ENTREGADO'],
]);

test('registra seguimientos de la programacion al crear y autorizar un despacho', function () {
    $despacho = crearDespachoListo($this->bahia, ['entrega_autorizada' => false]);
    $programacion = $despacho->ordenTrabajo->equipoProgramacion;

    expect($programacion->seguimientos()->where('fase', 'DESPACHO')->count())->toBe(0);

    $despacho->update(['entrega_autorizada' => true]);
    $despacho->update(['entrega_recibida' => true]);

    expect($programacion->seguimientos()->where('fase', 'DESPACHO')->orderBy('id')->pluck('seguimiento')->all())
        ->toBe(['AUTORIZADO', 'ENTREGADO']);
});

test('registra un seguimiento de bahia o ingreso segun agendar al crear la programacion', function () {
    $agendada = crearEquipoRecibido($this->bahia, 'EQ-SEG-1');
    $agendada->update(['agendar' => false, 'estado_programacion' => 'PENDIENTE']);

    expect($agendada->seguimientos()->orderBy('id')->get())
        ->not->toBeEmpty()
        ->last()->fase->toBe('BAHIA')
        ->last()->seguimiento->toBe('PENDIENTE');
});

test('al entregar un servicio preventivo con intervalo crea la siguiente programacion del equipo', function () {
    $despacho = crearDespachoListo($this->bahia);
    $programacion = $despacho->ordenTrabajo->equipoProgramacion;
    $programacion->update([
        'tipo_servicio' => 'MANTENIMIENTO,CALIBRACION',
        'intervalo_servicio' => 3,
        'intervalo_unidad' => 'MESES',
        'dias_plazo_vencimiento' => 15,
    ]);

    $despacho->update(['entrega_recibida' => true]);

    $siguiente = EquipoProgramacion::query()
        ->where('equipo_id', $programacion->equipo_id)
        ->whereKeyNot($programacion->id)
        ->firstOrFail();

    expect($siguiente)
        ->tipo_servicio->toBe('MANTENIMIENTO,CALIBRACION')
        ->tipo_mantenimiento->toBe('PREVENTIVO')
        ->estado_programacion->toBe('PENDIENTE')
        ->ingreso_id->toBeNull()
        ->fecha_ultimo_servicio->toDateString()->toBe(now()->toDateString())
        ->fecha_proximo_servicio->toDateString()->toBe(now()->addMonths(3)->toDateString())
        ->and((float) $siguiente->dias_plazo_vencimiento)->toBe(15.0);
});

test('no crea otra programacion si la entrega se vuelve a marcar el mismo dia', function () {
    $despacho = crearDespachoListo($this->bahia);
    $programacion = $despacho->ordenTrabajo->equipoProgramacion;
    $programacion->update(['intervalo_servicio' => 1, 'intervalo_unidad' => 'SEMANAS']);

    $despacho->update(['entrega_recibida' => true]);
    $despacho->update(['entrega_recibida' => false]);
    $despacho->update(['entrega_recibida' => true]);

    expect(EquipoProgramacion::where('equipo_id', $programacion->equipo_id)->count())->toBe(2);
});

test('no crea una siguiente programacion para un servicio correctivo ni para uno preventivo sin intervalo', function () {
    $correctivo = crearDespachoListo($this->bahia, [], 'EQ-CORR');
    $correctivo->ordenTrabajo->equipoProgramacion->update([
        'tipo_mantenimiento' => 'CORRECTIVO',
        'intervalo_servicio' => 3,
        'intervalo_unidad' => 'MESES',
    ]);
    $sinIntervalo = crearDespachoListo($this->bahia, [], 'EQ-SIN');

    $correctivo->update(['entrega_recibida' => true]);
    $sinIntervalo->update(['entrega_recibida' => true]);

    expect(EquipoProgramacion::count())->toBe(2);
});

test('despachar sin enviar entrega_autorizada conserva la autorizacion y el tecnico, y marca la entrega como recibida', function () {
    $tecnico = User::factory()->forTenant($this->tenant)->create();
    $despacho = crearDespachoListo($this->bahia, ['entrega_autorizada' => true, 'tecnico_entrega_id' => $tecnico->id]);

    $this->actingAs($this->admin)
        ->put(route('despachos.update', $despacho), [
            'entrega_recibida' => '1',
            'cliente_recibe_id' => $this->cliente->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('despachos.index'));

    expect($despacho->refresh())
        ->entrega_autorizada->toBeTrue()
        ->tecnico_entrega_id->toBe($tecnico->id)
        ->entrega_recibida->toBeTrue()
        ->cliente_recibe_id->toBe($this->cliente->id)
        ->estado_despacho->toBe('ENTREGADO');
});
