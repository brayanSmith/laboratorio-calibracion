<?php

use App\Enums\TenantRole;
use App\Models\Calibracion;
use App\Models\Laboratorio;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function registrarCalibracionEnLaboratorio(Laboratorio $laboratorio): Calibracion
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return Calibracion::create([
        'orden_trabajo_id' => 1,
        'fecha_calibracion' => now()->toDateString(),
        'laboratorio_id' => $laboratorio->id,
        'solicitante_id' => 1,
        'tecnico_id' => 1,
        'procedimiento_id' => 1,
        'estado_calibracion' => 'PENDIENTE',
        'novedad_id' => 1,
        'tenant_id' => $laboratorio->tenant_id,
    ]);
}

test('lista solo los laboratorios del tenant con la cantidad de calibraciones', function () {
    $laboratorio = Laboratorio::factory()->for($this->tenant)->create(['nombre' => 'Presión']);
    registrarCalibracionEnLaboratorio($laboratorio);
    Laboratorio::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->get(route('laboratorios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('laboratorios/index')
            ->has('laboratorios', 1)
            ->where('laboratorios.0.nombre', 'Presión')
            ->where('laboratorios.0.calibraciones_count', 1));
});

test('un usuario sin permiso de ver laboratorios no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('laboratorios.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear laboratorios', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('laboratorios.index'))->assertOk();
    $this->actingAs($tecnico)->post(route('laboratorios.store'), ['nombre' => 'Nuevo'])->assertForbidden();

    expect(Laboratorio::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('laboratorios.index'))->assertRedirect(route('login'));
});

test('crea un laboratorio asignado al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('laboratorios.store'), [
            'nombre' => 'Temperatura',
            'descripcion' => 'Termómetros y termocuplas',
            'direccion' => 'Piso 2',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('laboratorios.index'));

    $laboratorio = Laboratorio::firstOrFail();

    expect($laboratorio->nombre)->toBe('Temperatura')
        ->and($laboratorio->descripcion)->toBe('Termómetros y termocuplas')
        ->and($laboratorio->direccion)->toBe('Piso 2')
        ->and($laboratorio->tenant_id)->toBe($this->tenant->id);
});

test('la descripcion y la direccion son opcionales', function () {
    $this->actingAs($this->admin)
        ->post(route('laboratorios.store'), ['nombre' => 'Masa'])
        ->assertSessionHasNoErrors();

    expect(Laboratorio::firstOrFail())->descripcion->toBeNull()->direccion->toBeNull();
});

test('rechaza un laboratorio sin nombre', function () {
    $this->actingAs($this->admin)
        ->post(route('laboratorios.store'), [])
        ->assertSessionHasErrors('nombre');
});

test('el nombre es unico dentro del tenant pero puede repetirse en otro', function () {
    Laboratorio::factory()->for($this->tenant)->create(['nombre' => 'Presión']);

    $this->actingAs($this->admin)
        ->post(route('laboratorios.store'), ['nombre' => 'Presión'])
        ->assertSessionHasErrors('nombre');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('laboratorios.store'), ['nombre' => 'Presión'])
        ->assertSessionHasNoErrors();
});

test('actualiza los datos del laboratorio', function () {
    $laboratorio = Laboratorio::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('laboratorios.update', $laboratorio), ['nombre' => 'Masa', 'descripcion' => 'Balanzas', 'direccion' => null])
        ->assertRedirect(route('laboratorios.index'));

    expect($laboratorio->fresh())
        ->nombre->toBe('Masa')
        ->descripcion->toBe('Balanzas');
});

test('permite guardar un laboratorio conservando su propio nombre', function () {
    $laboratorio = Laboratorio::factory()->for($this->tenant)->create(['nombre' => 'Presión']);

    $this->actingAs($this->admin)
        ->put(route('laboratorios.update', $laboratorio), ['nombre' => 'Presión', 'direccion' => 'Piso 1'])
        ->assertSessionHasNoErrors();

    expect($laboratorio->fresh()->direccion)->toBe('Piso 1');
});

test('no permite actualizar ni eliminar laboratorios de otro tenant', function () {
    $ajeno = Laboratorio::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)->put(route('laboratorios.update', $ajeno), ['nombre' => 'Hackeado'])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('laboratorios.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('elimina un laboratorio sin calibraciones', function () {
    $laboratorio = Laboratorio::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('laboratorios.destroy', $laboratorio))
        ->assertRedirect(route('laboratorios.index'));

    expect(Laboratorio::find($laboratorio->id))->toBeNull()
        ->and(Laboratorio::withTrashed()->find($laboratorio->id))->not->toBeNull();
});

test('no elimina un laboratorio que tiene calibraciones asociadas', function () {
    $laboratorio = Laboratorio::factory()->for($this->tenant)->create();
    registrarCalibracionEnLaboratorio($laboratorio);

    $this->actingAs($this->admin)
        ->from(route('laboratorios.index'))
        ->delete(route('laboratorios.destroy', $laboratorio))
        ->assertRedirect(route('laboratorios.index'));

    expect(Laboratorio::find($laboratorio->id))->not->toBeNull();
});

test('permite volver a usar el nombre de un laboratorio eliminado', function () {
    Laboratorio::factory()->for($this->tenant)->create(['nombre' => 'Presión'])->delete();

    $this->actingAs($this->admin)
        ->post(route('laboratorios.store'), ['nombre' => 'Presión'])
        ->assertSessionHasNoErrors();

    expect(Laboratorio::where('nombre', 'Presión')->count())->toBe(1);
});
