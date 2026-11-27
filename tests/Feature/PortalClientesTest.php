<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Portal\Mail\AccesoPortalCliente;
use App\Modules\Portal\Mail\RegistroPortalCliente;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
});

/** Cliente del tenant con acceso al portal habilitado y la contraseña "secreto123". */
function clienteConPortal(Tenant $tenant, string $usuario = 'acme'): Cliente
{
    return Cliente::factory()->for($tenant)->create([
        'portal_usuario' => $usuario,
        'portal_password' => 'secreto123',
        'portal_activo' => true,
    ]);
}

test('el laboratorio habilita el acceso al portal y le envia las credenciales al cliente', function () {
    Mail::fake();
    $cliente = Cliente::factory()->for($this->tenant)->create(['email' => 'compras@acme.test']);

    $this->actingAs($this->admin)
        ->put(route('clientes.portal.update', $cliente), [
            'portal_activo' => '1',
            'portal_usuario' => ' ACME.Compras ',
            'contrasena' => 'Clave-1234',
            'enviar_correo' => '1',
        ])
        ->assertSessionHasNoErrors();

    $cliente->refresh();

    expect($cliente->portal_activo)->toBeTrue()
        ->and($cliente->portal_usuario)->toBe('acme.compras')
        ->and(Hash::check('Clave-1234', $cliente->portal_password))->toBeTrue();

    Mail::assertSent(AccesoPortalCliente::class, fn (AccesoPortalCliente $correo): bool => $correo->hasTo('compras@acme.test')
        && $correo->contrasena === 'Clave-1234');
});

test('no se habilita el acceso sin contrasena ni con un usuario que ya tiene otro cliente', function () {
    clienteConPortal(Tenant::factory()->create(), 'repetido');
    $cliente = Cliente::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('clientes.portal.update', $cliente), ['portal_activo' => '1', 'portal_usuario' => 'nuevo'])
        ->assertSessionHasErrors('contrasena');

    $this->actingAs($this->admin)
        ->put(route('clientes.portal.update', $cliente), ['portal_activo' => '1', 'portal_usuario' => 'repetido', 'contrasena' => 'Clave-1234'])
        ->assertSessionHasErrors('portal_usuario');

    expect($cliente->refresh()->portal_activo)->toBeFalse();
});

test('solo quien puede editar clientes del tenant gestiona su acceso al portal', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create();
    $soloVer = usuarioConPermisos($this->tenant, [TenantPermission::ClientesVer]);

    $this->actingAs($soloVer)
        ->put(route('clientes.portal.update', $cliente), ['portal_activo' => '1', 'portal_usuario' => 'acme', 'contrasena' => 'Clave-1234'])
        ->assertForbidden();

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->put(route('clientes.portal.update', $cliente), ['portal_activo' => '1', 'portal_usuario' => 'acme', 'contrasena' => 'Clave-1234'])
        ->assertForbidden();
});

test('el cliente entra con su usuario y contrasena, y no si su acceso esta deshabilitado', function () {
    $cliente = clienteConPortal($this->tenant);

    $this->post(route('portal.login.store'), ['portal_usuario' => 'ACME', 'password' => 'secreto123'])
        ->assertRedirect(route('portal.equipos.index'));

    $this->assertAuthenticatedAs($cliente, 'cliente');
    $this->assertGuest('web');
    expect($cliente->refresh()->portal_ultimo_acceso)->not->toBeNull();

    $this->post(route('portal.logout'));
    $cliente->update(['portal_activo' => false]);

    $this->post(route('portal.login.store'), ['portal_usuario' => 'acme', 'password' => 'secreto123'])
        ->assertSessionHasErrors('portal_usuario');
    $this->assertGuest('cliente');
});

test('el cliente ve solo sus equipos y los de sus bahias', function () {
    $cliente = clienteConPortal($this->tenant);
    $cliente->bahias()->attach($this->bahia);

    $propio = crearEquipo($this->tenant, 'EQ-PROPIO');
    $propio->update(['cliente_id' => $cliente->id]);
    $deSuBahia = crearEquipoParaBusqueda($this->bahia, 'EQ-BAHIA');
    crearEquipo($this->tenant, 'EQ-AJENO');
    crearEquipo(Tenant::factory()->create(), 'EQ-OTRO-LAB');

    $this->actingAs($cliente, 'cliente')
        ->get(route('portal.equipos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/equipos/index')
            ->has('equipos', 2)
            ->where('equipos.0.codigo', 'EQ-BAHIA')
            ->where('equipos.1.codigo', 'EQ-PROPIO'));

    $this->actingAs($cliente, 'cliente')
        ->get(route('portal.equipos.show', $deSuBahia))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('portal/equipos/show')->where('equipo.codigo', 'EQ-BAHIA'));

    $this->actingAs($cliente, 'cliente')
        ->get(route('portal.equipos.show', Equipo::where('codigo', 'EQ-AJENO')->sole()))
        ->assertNotFound();
});

test('la sesion del cliente y la del laboratorio no se cruzan', function () {
    clienteConPortal($this->tenant);

    // Con la sesión real del portal (actingAs cambiaría el guard por defecto de la prueba).
    $this->post(route('portal.login.store'), ['portal_usuario' => 'acme', 'password' => 'secreto123']);
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('clientes.index'))->assertRedirect(route('login'));
    $this->post(route('portal.logout'));

    $this->actingAs($this->admin, 'web')->get(route('portal.equipos.index'))->assertRedirect(route('portal.login'));
});

test('si le deshabilitan el acceso con la sesion abierta, el cliente sale del portal', function () {
    $cliente = clienteConPortal($this->tenant);
    $cliente->update(['portal_activo' => false]);

    $this->actingAs($cliente, 'cliente')
        ->get(route('portal.equipos.index'))
        ->assertRedirect(route('portal.login'));

    $this->assertGuest('cliente');
});

test('al olvidar la contrasena le llega una nueva al correo, sin revelar si el usuario existe', function () {
    Mail::fake();
    $cliente = clienteConPortal($this->tenant);
    $anterior = $cliente->portal_password;

    $this->post(route('portal.olvide.store'), ['usuario_o_correo' => 'ACME'])
        ->assertRedirect(route('portal.login'))
        ->assertSessionHas('status');

    Mail::assertSent(AccesoPortalCliente::class, function (AccesoPortalCliente $correo) use ($cliente): bool {
        return $correo->hasTo($cliente->email)
            && $correo->motivo === AccesoPortalCliente::MOTIVO_RECUPERACION
            && Hash::check($correo->contrasena, $cliente->refresh()->portal_password);
    });
    expect($cliente->refresh()->portal_password)->not->toBe($anterior);

    $this->post(route('portal.olvide.store'), ['usuario_o_correo' => 'no-existe'])
        ->assertRedirect(route('portal.login'))
        ->assertSessionHas('status');
    Mail::assertSentCount(1);
});

test('el cliente cambia su contrasena dando la actual', function () {
    $cliente = clienteConPortal($this->tenant);

    $this->actingAs($cliente, 'cliente')
        ->put(route('portal.cuenta.contrasena'), ['contrasena_actual' => 'equivocada', 'contrasena' => 'NuevaClave1', 'contrasena_confirmation' => 'NuevaClave1'])
        ->assertSessionHasErrors('contrasena_actual');

    $this->actingAs($cliente, 'cliente')
        ->put(route('portal.cuenta.contrasena'), ['contrasena_actual' => 'secreto123', 'contrasena' => 'NuevaClave1', 'contrasena_confirmation' => 'NuevaClave1'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('NuevaClave1', $cliente->refresh()->portal_password))->toBeTrue();
});

test('un cliente registrado crea su cuenta desde el enlace que le llega a su correo', function () {
    Mail::fake();
    $cliente = Cliente::factory()->for($this->tenant)->create(['email' => 'compras@acme.test']);

    $this->post(route('portal.registro.store'), ['correo' => ' Compras@ACME.test '])
        ->assertRedirect(route('portal.login'))
        ->assertSessionHas('status');

    $enlace = null;
    Mail::assertSent(RegistroPortalCliente::class, function (RegistroPortalCliente $correo) use ($cliente, &$enlace): bool {
        $enlace = $correo->enlace;

        return $correo->hasTo('compras@acme.test') && $correo->cliente->is($cliente);
    });

    $this->get($enlace)->assertOk()->assertInertia(fn ($page) => $page->component('portal/auth/completar-registro'));

    $this->post($enlace, ['portal_usuario' => 'Acme', 'contrasena' => 'Clave-1234', 'contrasena_confirmation' => 'Clave-1234'])
        ->assertRedirect(route('portal.equipos.index'));

    $cliente->refresh();
    expect($cliente->portal_usuario)->toBe('acme')
        ->and($cliente->portal_activo)->toBeTrue()
        ->and(Hash::check('Clave-1234', $cliente->portal_password))->toBeTrue();
    $this->assertAuthenticatedAs($cliente, 'cliente');

    // El enlace sirve una sola vez.
    $this->post(route('portal.logout'));
    $this->post($enlace, ['portal_usuario' => 'otro', 'contrasena' => 'Otra-12345', 'contrasena_confirmation' => 'Otra-12345'])
        ->assertRedirect(route('portal.login'));
    expect($cliente->refresh()->portal_usuario)->toBe('acme');
});

test('no se crea la cuenta con un correo que no es de un cliente o que ya tiene una', function () {
    Mail::fake();
    clienteConPortal($this->tenant)->update(['email' => 'ya@acme.test']);
    Cliente::factory()->for($this->tenant)->create(['email' => 'apagado@acme.test', 'portal_usuario' => 'apagado', 'portal_activo' => false]);

    $this->post(route('portal.registro.store'), ['correo' => 'nadie@acme.test'])
        ->assertSessionHasErrors(['correo' => 'Ese correo no está registrado como cliente. Por favor contacta al administrador del laboratorio.']);
    $this->post(route('portal.registro.store'), ['correo' => 'ya@acme.test'])->assertSessionHasErrors('correo');
    $this->post(route('portal.registro.store'), ['correo' => 'apagado@acme.test'])->assertSessionHasErrors('correo');

    Mail::assertNothingSent();
});

test('el enlace para crear la cuenta no sirve si se altera', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create();
    $otro = Cliente::factory()->for($this->tenant)->create();
    $enlace = URL::temporarySignedRoute('portal.registro.completar', now()->addHour(), ['cliente' => $cliente->id]);

    $this->get(str_replace("/registro/{$cliente->id}", "/registro/{$otro->id}", $enlace))->assertForbidden();
    $this->post(route('portal.registro.guardar', $cliente), ['portal_usuario' => 'intruso', 'contrasena' => 'Clave-1234', 'contrasena_confirmation' => 'Clave-1234'])
        ->assertForbidden();

    expect($cliente->refresh()->portal_usuario)->toBeNull();
});

test('el detalle del equipo trae su paso por cada ingreso', function () {
    $cliente = clienteConPortal($this->tenant);
    $cliente->bahias()->attach($this->bahia);
    $programacion = crearEquipoRecibido($this->bahia, 'EQ-TRAZA');

    $this->actingAs($cliente, 'cliente')
        ->get(route('portal.equipos.show', $programacion->equipo_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('traza', 1)
            ->where('traza.0.ingreso', $programacion->ingreso->codigo)
            ->where('equipo.activo', true));
});
