<?php

use App\Enums\TenantRole;
use App\Enums\TipoDocumentoCliente;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Despachos\Models\Despacho;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Ingresos\Models\Ingreso;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function crearEquipoDeCliente(Cliente $cliente): Equipo
{
    $tenantId = $cliente->tenant_id;
    $area = Area::create(['nombre' => 'Área 1', 'tenant_id' => $tenantId]);

    return Equipo::create([
        'codigo' => 'EQ-0001',
        'tipo_equipo_id' => crearTipoEquipo($cliente->tenant)->id,
        'tipo_tecnologia' => 'DIGITAL',
        'modelo' => 'Modelo X',
        'fabricante_id' => Fabricante::create(['nombre' => 'Fluke', 'tenant_id' => $tenantId])->id,
        'numero_serie' => 'SN-1',
        'area_id' => $area->id,
        'bahia_id' => Bahia::create(['nombre' => 'Bahía 1', 'area_id' => $area->id, 'tenant_id' => $tenantId])->id,
        'condicion_actual' => 'Bueno',
        'cliente_id' => $cliente->id,
        'tenant_id' => $tenantId,
    ]);
}

function registrarDespachoDeCliente(Cliente $cliente): Despacho
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return Despacho::create([
        'tecnico_entrega_id' => 1,
        'cliente_recibe_id' => $cliente->id,
        'orden_trabajo_id' => 1,
        'novedad_id' => 1,
        'tenant_id' => $cliente->tenant_id,
    ]);
}

function registrarIngresoDeCliente(Cliente $cliente): Ingreso
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return Ingreso::create([
        'desde' => now()->toDateString(),
        'hasta' => now()->toDateString(),
        'tecnico_recibe_id' => 1,
        'cliente_entrega_id' => $cliente->id,
        'tenant_id' => $cliente->tenant_id,
    ]);
}

test('lista solo los clientes del tenant con la cantidad de equipos asociados', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create([
        'nombre' => 'Acme',
        'tipo_documento' => TipoDocumentoCliente::CodigoSap,
        'numero_documento' => '900123456',
    ]);
    crearEquipoDeCliente($cliente);
    Cliente::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->get(route('clientes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clientes/index')
            ->has('clientes', 1)
            ->where('clientes.0.nombre', 'Acme')
            ->where('clientes.0.tipo_documento', 'SAP')
            ->where('clientes.0.numero_documento', '900123456')
            ->where('clientes.0.equipos_count', 1)
            ->where('tiposDocumento.0', ['value' => 'DNI', 'label' => 'DNI']));
});

test('un usuario sin permiso de ver clientes no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('clientes.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear clientes', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('clientes.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('clientes.store'), ['nombre' => 'Nuevo', 'email' => 'nuevo@example.com'])
        ->assertForbidden();

    expect(Cliente::count())->toBe(0);
});

/**
 * Tipo y número de documento válidos, ahora obligatorios al crear o editar un cliente.
 *
 * @return array{tipo_documento: string, numero_documento: string}
 */
function documentoClienteValido(string $numero = '900123456'): array
{
    return ['tipo_documento' => 'SAP', 'numero_documento' => $numero];
}

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('clientes.index'))->assertRedirect(route('login'));
});

test('crea un cliente asignado al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('clientes.store'), [
            ...documentoClienteValido(),
            'nombre' => 'Acme SAC',
            'email' => 'contacto@acme.test',
            'telefono' => '987654321',
            'direccion' => 'Av. Principal 123',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('clientes.index'));

    $cliente = Cliente::firstOrFail();

    expect($cliente->nombre)->toBe('Acme SAC')
        ->and($cliente->email)->toBe('contacto@acme.test')
        ->and($cliente->tipo_documento)->toBe(TipoDocumentoCliente::CodigoSap)
        ->and($cliente->numero_documento)->toBe('900123456')
        ->and($cliente->tenant_id)->toBe($this->tenant->id);
});

test('el telefono y la direccion son opcionales', function () {
    $this->actingAs($this->admin)
        ->post(route('clientes.store'), [...documentoClienteValido(), 'nombre' => 'Acme', 'email' => 'acme@example.com'])
        ->assertSessionHasNoErrors();

    expect(Cliente::firstOrFail())->telefono->toBeNull()->direccion->toBeNull();
});

test('rechaza un cliente sin nombre ni correo', function () {
    $this->actingAs($this->admin)
        ->post(route('clientes.store'), [])
        ->assertSessionHasErrors(['nombre', 'tipo_documento', 'numero_documento', 'email']);
});

test('rechaza un correo con formato invalido', function () {
    $this->actingAs($this->admin)
        ->post(route('clientes.store'), ['nombre' => 'Acme', 'email' => 'no-es-un-correo'])
        ->assertSessionHasErrors('email');
});

test('el correo es unico dentro del tenant pero puede repetirse en otro', function () {
    Cliente::factory()->for($this->tenant)->create(['email' => 'acme@example.com']);

    $this->actingAs($this->admin)
        ->post(route('clientes.store'), [...documentoClienteValido(), 'nombre' => 'Otro', 'email' => 'acme@example.com'])
        ->assertSessionHasErrors('email');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('clientes.store'), [...documentoClienteValido(), 'nombre' => 'Otro', 'email' => 'acme@example.com'])
        ->assertSessionHasNoErrors();
});

test('actualiza los datos del cliente', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('clientes.update', $cliente), [
            'tipo_documento' => 'DNI',
            'numero_documento' => '1020304050',
            'nombre' => 'Nuevo nombre',
            'email' => 'nuevo@example.com',
            'telefono' => null,
            'direccion' => null,
        ])
        ->assertRedirect(route('clientes.index'));

    expect($cliente->fresh())
        ->nombre->toBe('Nuevo nombre')
        ->tipo_documento->toBe(TipoDocumentoCliente::Dni)
        ->numero_documento->toBe('1020304050')
        ->email->toBe('nuevo@example.com');
});

test('al actualizar el correo no puede coincidir con el de otro cliente del mismo tenant', function () {
    Cliente::factory()->for($this->tenant)->create(['email' => 'uno@example.com']);
    $cliente = Cliente::factory()->for($this->tenant)->create(['email' => 'dos@example.com']);

    $this->actingAs($this->admin)
        ->put(route('clientes.update', $cliente), ['nombre' => 'X', 'email' => 'uno@example.com'])
        ->assertSessionHasErrors('email');
});

test('permite guardar un cliente conservando su propio correo', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create(['email' => 'acme@example.com']);

    $this->actingAs($this->admin)
        ->put(route('clientes.update', $cliente), [
            'tipo_documento' => $cliente->tipo_documento->value,
            'numero_documento' => $cliente->numero_documento,
            'nombre' => 'Nuevo nombre',
            'email' => 'acme@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($cliente->fresh()->nombre)->toBe('Nuevo nombre');
});

test('rechaza un tipo de documento que no existe', function () {
    $this->actingAs($this->admin)
        ->post(route('clientes.store'), [
            ...documentoClienteValido(),
            'tipo_documento' => 'RUT',
            'nombre' => 'Acme',
            'email' => 'acme@example.com',
        ])
        ->assertSessionHasErrors('tipo_documento');
});

test('el numero de documento es unico dentro del tenant, incluso entre clientes eliminados', function () {
    Cliente::factory()->for($this->tenant)->create(['numero_documento' => '900123456']);
    Cliente::factory()->for($this->tenant)->create(['numero_documento' => '800111222'])->delete();

    $this->actingAs($this->admin)
        ->post(route('clientes.store'), [...documentoClienteValido('900123456'), 'nombre' => 'Otro', 'email' => 'otro@example.com'])
        ->assertSessionHasErrors(['numero_documento' => 'El campo número de documento ya ha sido registrado.']);

    $this->actingAs($this->admin)
        ->post(route('clientes.store'), [...documentoClienteValido('800111222'), 'nombre' => 'Otro', 'email' => 'otro@example.com'])
        ->assertSessionHasErrors('numero_documento');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('clientes.store'), [...documentoClienteValido('900123456'), 'nombre' => 'Otro', 'email' => 'otro@example.com'])
        ->assertSessionHasNoErrors();
});

test('al actualizar el documento no puede coincidir con el de otro cliente del mismo tenant', function () {
    Cliente::factory()->for($this->tenant)->create(['numero_documento' => '900123456']);
    $cliente = Cliente::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('clientes.update', $cliente), [...documentoClienteValido('900123456'), 'nombre' => 'X', 'email' => $cliente->email])
        ->assertSessionHasErrors('numero_documento');
});

test('no permite actualizar ni eliminar clientes de otro tenant', function () {
    $ajeno = Cliente::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->put(route('clientes.update', $ajeno), ['nombre' => 'Hackeado', 'email' => 'hackeado@example.com'])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('clientes.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('elimina un cliente sin equipos, ingresos ni despachos asociados', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('clientes.destroy', $cliente))
        ->assertRedirect(route('clientes.index'));

    expect(Cliente::find($cliente->id))->toBeNull()
        ->and(Cliente::withTrashed()->find($cliente->id))->not->toBeNull();
});

test('no elimina un cliente que tiene equipos asociados', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create();
    crearEquipoDeCliente($cliente);

    $this->actingAs($this->admin)
        ->from(route('clientes.index'))
        ->delete(route('clientes.destroy', $cliente))
        ->assertRedirect(route('clientes.index'));

    expect(Cliente::find($cliente->id))->not->toBeNull();
});

test('no elimina un cliente que tiene despachos asociados', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create();
    registrarDespachoDeCliente($cliente);

    $this->actingAs($this->admin)
        ->from(route('clientes.index'))
        ->delete(route('clientes.destroy', $cliente))
        ->assertRedirect(route('clientes.index'));

    expect(Cliente::find($cliente->id))->not->toBeNull();
});

test('no elimina un cliente que tiene ingresos asociados', function () {
    $cliente = Cliente::factory()->for($this->tenant)->create();
    registrarIngresoDeCliente($cliente);

    $this->actingAs($this->admin)
        ->from(route('clientes.index'))
        ->delete(route('clientes.destroy', $cliente))
        ->assertRedirect(route('clientes.index'));

    expect(Cliente::find($cliente->id))->not->toBeNull();
});
