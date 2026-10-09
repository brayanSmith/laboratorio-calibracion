<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\Despacho;
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
