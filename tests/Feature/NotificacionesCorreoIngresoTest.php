<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Ingresos\Mail\IngresoRecibido;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Ingresos\Models\IngresoEnvio;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/**
 * Un ingreso ya recibido con un equipo de un cliente con correo y otro de un cliente sin
 * correo.
 */
function ingresoRecibidoConClientes(): Ingreso
{
    $ingreso = Ingreso::factory()->create([
        'bahia_id' => test()->bahia->id,
        'tenant_id' => test()->tenant->id,
        'estado_ingreso' => 'RECIBIDO',
        'cliente_entrega_id' => null,
    ]);
    $conCorreo = crearEquipoParaBusqueda(test()->bahia, 'EQ-1');
    $conCorreo->cliente->update(['nombre' => 'Acme', 'email' => 'compras@acme.test']);
    $sinCorreo = crearEquipoParaBusqueda(test()->bahia, 'EQ-2');
    $sinCorreo->cliente->update(['email' => '']);

    foreach ([$conCorreo, $sinCorreo] as $equipo) {
        crearProgramacion($equipo, ['ingreso_id' => $ingreso->id, 'estado_programacion' => 'RECIBIDO']);
    }

    return $ingreso;
}

test('enviar a todos pone en cola el correo de cada cliente con correo y lo anota a nombre de quien lo envia', function () {
    Mail::fake();
    $ingreso = ingresoRecibidoConClientes();

    $this->actingAs($this->admin)
        ->post(route('ingresos.correo.todos', $ingreso))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $envio = IngresoEnvio::sole();
    expect($envio)
        ->correo->toBe('compras@acme.test')
        ->destinatario->toBe('Acme')
        ->estado->toBe('EN_COLA')
        ->user_id->toBe($this->admin->id);
    Mail::assertQueued(IngresoRecibido::class, fn (IngresoRecibido $correo) => $correo->hasTo('compras@acme.test')
        && $correo->envioId === $envio->id);
});

test('al salir el correo queda como enviado y avisa a quien lo envio', function () {
    $ingreso = ingresoRecibidoConClientes();

    $this->actingAs($this->admin)->post(route('ingresos.correo.todos', $ingreso));

    expect(IngresoEnvio::sole())->estado->toBe('ENVIADO')->enviado_at->not->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('ingresos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('notificacionesSinLeer', 1)
            ->where('ingresos.0.clientes_correo.0.ultimo_envio.estado', 'ENVIADO'));
});

test('si el correo en cola falla queda anotado el motivo', function () {
    $envio = IngresoEnvio::factory()->create(['user_id' => $this->admin->id]);
    $correo = new IngresoRecibido($envio->tenant_id, 'IN-1', 'Acme', 'Bahía 1', '2026-11-10', null, []);
    $correo->envioId = $envio->id;

    $correo->failed(new RuntimeException('Connection could not be established with host smtp.gmail.com'));

    expect($envio->fresh())
        ->estado->toBe('FALLIDO')
        ->error->toBe('Connection could not be established with host smtp.gmail.com');
});

test('enviar a todos no envia nada de un ingreso sin recibir ni sin permiso', function () {
    Mail::fake();
    $pendiente = Ingreso::factory()->create(['bahia_id' => $this->bahia->id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'PENDIENTE']);
    $recibido = ingresoRecibidoConClientes();
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($this->admin)->post(route('ingresos.correo.todos', $pendiente))->assertSessionHasErrors('ingreso');
    $this->actingAs($tecnico)->post(route('ingresos.correo.todos', $recibido))->assertForbidden();

    Mail::assertNothingQueued();
    expect(IngresoEnvio::count())->toBe(0);
});

test('las notificaciones muestran solo los correos del usuario y al verlas dejan de contar', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id, 'tenant_id' => $this->tenant->id]);
    $fallido = IngresoEnvio::factory()->fallido('Mailbox unavailable')->create(['ingreso_id' => $ingreso->id, 'user_id' => $this->admin->id]);
    IngresoEnvio::factory()->create(['ingreso_id' => $ingreso->id, 'user_id' => $this->admin->id]);
    IngresoEnvio::factory()->enviado()->create(['ingreso_id' => $ingreso->id, 'user_id' => User::factory()->forTenant($this->tenant)->create()->id]);

    $this->actingAs($this->admin)
        ->getJson(route('notificaciones.correos'))
        ->assertOk()
        ->assertJsonCount(2, 'notificaciones')
        ->assertJsonPath('notificaciones.1.id', $fallido->id)
        ->assertJsonPath('notificaciones.1.ingreso_codigo', $ingreso->codigo)
        ->assertJsonPath('notificaciones.1.error', 'Mailbox unavailable')
        ->assertJsonPath('notificaciones.1.leido', false);

    // El que sigue en cola no cuenta: se avisa cuando sale o falla.
    $this->actingAs($this->admin)->get(route('ingresos.index'))->assertInertia(fn ($page) => $page->where('notificacionesSinLeer', 1));

    $this->actingAs($this->admin)->patchJson(route('notificaciones.correos.leidas'))->assertOk();

    expect($fallido->fresh()->leido_at)->not->toBeNull();
    $this->actingAs($this->admin)->get(route('ingresos.index'))->assertInertia(fn ($page) => $page->where('notificacionesSinLeer', 0));
});

test('sin permiso de ver ingresos no hay campanita', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('notificacionesSinLeer', null));
});
