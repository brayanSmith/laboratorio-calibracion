<?php

use App\Enums\TenantRole;
use App\Models\EmpresaTercero;
use App\Models\ServicioTercero;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function registrarServicioDeEmpresaTercero(EmpresaTercero $empresaTercero): ServicioTercero
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return ServicioTercero::create([
        'orden_trabajo_id' => 1,
        'tipo_servicio' => 'CALIBRACION',
        'empresa_tercero_id' => $empresaTercero->id,
        'tenant_id' => $empresaTercero->tenant_id,
    ]);
}

test('lista solo las empresas terceras del tenant con la cantidad de servicios', function () {
    $laboratorio = EmpresaTercero::factory()->for($this->tenant)->create(['nombre' => 'Calibraciones SAC']);
    registrarServicioDeEmpresaTercero($laboratorio);
    EmpresaTercero::factory()->create(['nombre' => 'Ajena']);

    $this->actingAs($this->admin)
        ->get(route('empresas-terceras.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresas-terceras/index')
            ->has('empresasTerceras', 1)
            ->where('empresasTerceras.0.nombre', 'Calibraciones SAC')
            ->where('empresasTerceras.0.servicios_count', 1));
});

test('un usuario sin permiso de ver empresas terceras no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('empresas-terceras.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear empresas terceras', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('empresas-terceras.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('empresas-terceras.store'), ['nombre' => 'Nueva', 'nit' => '900-1'])
        ->assertForbidden();

    expect(EmpresaTercero::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('empresas-terceras.index'))->assertRedirect(route('login'));
});

test('crea una empresa tercera asignada al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('empresas-terceras.store'), [
            'nombre' => 'Calibraciones SAC',
            'nit' => '900123456-7',
            'direccion' => 'Av. Principal 123',
            'telefono' => '987654321',
            'email' => 'contacto@calibraciones.test',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('empresas-terceras.index'));

    $empresaTercero = EmpresaTercero::firstOrFail();

    expect($empresaTercero->nombre)->toBe('Calibraciones SAC')
        ->and($empresaTercero->nit)->toBe('900123456-7')
        ->and($empresaTercero->email)->toBe('contacto@calibraciones.test')
        ->and($empresaTercero->tenant_id)->toBe($this->tenant->id);
});

test('la direccion, el telefono y el correo son opcionales', function () {
    $this->actingAs($this->admin)
        ->post(route('empresas-terceras.store'), ['nombre' => 'Calibraciones SAC', 'nit' => '900-1'])
        ->assertSessionHasNoErrors();

    expect(EmpresaTercero::firstOrFail())
        ->direccion->toBeNull()
        ->telefono->toBeNull()
        ->email->toBeNull();
});

test('rechaza una empresa tercera sin nombre ni NIT', function () {
    $this->actingAs($this->admin)
        ->post(route('empresas-terceras.store'), [])
        ->assertSessionHasErrors(['nombre', 'nit']);
});

test('rechaza un correo con formato invalido', function () {
    $this->actingAs($this->admin)
        ->post(route('empresas-terceras.store'), ['nombre' => 'X', 'nit' => '900-1', 'email' => 'no-es-un-correo'])
        ->assertSessionHasErrors('email');
});

test('el NIT es unico dentro del tenant pero puede repetirse en otro', function () {
    EmpresaTercero::factory()->for($this->tenant)->create(['nit' => '900-1']);

    $this->actingAs($this->admin)
        ->post(route('empresas-terceras.store'), ['nombre' => 'Otra', 'nit' => '900-1'])
        ->assertSessionHasErrors('nit');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('empresas-terceras.store'), ['nombre' => 'Otra', 'nit' => '900-1'])
        ->assertSessionHasNoErrors();

    expect(EmpresaTercero::where('nit', '900-1')->count())->toBe(2);
});

test('actualiza los datos de la empresa tercera', function () {
    $empresaTercero = EmpresaTercero::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('empresas-terceras.update', $empresaTercero), [
            'nombre' => 'Nuevo nombre',
            'nit' => '900-999',
            'direccion' => null,
            'telefono' => null,
            'email' => null,
        ])
        ->assertRedirect(route('empresas-terceras.index'));

    expect($empresaTercero->fresh())
        ->nombre->toBe('Nuevo nombre')
        ->nit->toBe('900-999');
});

test('al actualizar el NIT no puede coincidir con otra empresa del mismo tenant', function () {
    EmpresaTercero::factory()->for($this->tenant)->create(['nit' => '900-1']);
    $empresaTercero = EmpresaTercero::factory()->for($this->tenant)->create(['nit' => '900-2']);
    EmpresaTercero::factory()->create(['nit' => '900-3']);

    $this->actingAs($this->admin)
        ->put(route('empresas-terceras.update', $empresaTercero), ['nombre' => 'X', 'nit' => '900-1'])
        ->assertSessionHasErrors('nit');
    $this->actingAs($this->admin)
        ->put(route('empresas-terceras.update', $empresaTercero), ['nombre' => 'X', 'nit' => '900-3'])
        ->assertSessionHasNoErrors();
});

test('permite guardar una empresa tercera conservando su propio NIT', function () {
    $empresaTercero = EmpresaTercero::factory()->for($this->tenant)->create(['nit' => '900-1']);

    $this->actingAs($this->admin)
        ->put(route('empresas-terceras.update', $empresaTercero), ['nombre' => 'Nuevo nombre', 'nit' => '900-1'])
        ->assertSessionHasNoErrors();

    expect($empresaTercero->fresh()->nombre)->toBe('Nuevo nombre');
});

test('no permite actualizar ni eliminar empresas terceras de otro tenant', function () {
    $ajena = EmpresaTercero::factory()->create(['nombre' => 'Ajena']);

    $this->actingAs($this->admin)
        ->put(route('empresas-terceras.update', $ajena), ['nombre' => 'Hackeada', 'nit' => '1'])
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('empresas-terceras.destroy', $ajena))->assertForbidden();

    expect($ajena->fresh()->nombre)->toBe('Ajena');
});

test('elimina una empresa tercera sin servicios asociados', function () {
    $empresaTercero = EmpresaTercero::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('empresas-terceras.destroy', $empresaTercero))
        ->assertRedirect(route('empresas-terceras.index'));

    expect(EmpresaTercero::find($empresaTercero->id))->toBeNull()
        ->and(EmpresaTercero::withTrashed()->find($empresaTercero->id))->not->toBeNull();
});

test('no elimina una empresa tercera que tiene servicios asociados', function () {
    $empresaTercero = EmpresaTercero::factory()->for($this->tenant)->create();
    registrarServicioDeEmpresaTercero($empresaTercero);

    $this->actingAs($this->admin)
        ->from(route('empresas-terceras.index'))
        ->delete(route('empresas-terceras.destroy', $empresaTercero))
        ->assertRedirect(route('empresas-terceras.index'));

    expect(EmpresaTercero::find($empresaTercero->id))->not->toBeNull();
});
