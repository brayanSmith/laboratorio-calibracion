<?php

use App\Enums\TenantRole;
use App\Models\EquipoEspecificacionTecnica;
use App\Models\Tenant;
use App\Models\TipoMagnitud;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function registrarEspecificacionConTipoMagnitud(TipoMagnitud $tipoMagnitud): EquipoEspecificacionTecnica
{
    DB::statement('PRAGMA defer_foreign_keys = ON');

    return EquipoEspecificacionTecnica::create([
        'equipo_id' => 1,
        'tipo_magnitud_id' => $tipoMagnitud->id,
        'unidad_medida_id' => 1,
        'alcance_indicacion' => '100mm',
        'precision' => '±0.1',
        'resolucion' => '0.01mm',
        'tenant_id' => $tipoMagnitud->tenant_id,
    ]);
}

test('lista solo los tipos de magnitud del tenant con la cantidad de especificaciones', function () {
    $presion = TipoMagnitud::factory()->for($this->tenant)->create(['nombre' => 'Presión']);
    registrarEspecificacionConTipoMagnitud($presion);
    TipoMagnitud::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)
        ->get(route('tipos-magnitud.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tipos-magnitud/index')
            ->has('tiposMagnitud', 1)
            ->where('tiposMagnitud.0.nombre', 'Presión')
            ->where('tiposMagnitud.0.especificaciones_count', 1));
});

test('un usuario sin permiso de ver tipos de magnitud no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('tipos-magnitud.index'))->assertForbidden();
});

test('un tecnico puede ver pero no crear tipos de magnitud', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('tipos-magnitud.index'))->assertOk();
    $this->actingAs($tecnico)
        ->post(route('tipos-magnitud.store'), ['nombre' => 'Nuevo'])
        ->assertForbidden();

    expect(TipoMagnitud::count())->toBe(0);
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('tipos-magnitud.index'))->assertRedirect(route('login'));
});

test('crea un tipo de magnitud asignado al tenant del usuario', function () {
    $this->actingAs($this->admin)
        ->post(route('tipos-magnitud.store'), [
            'nombre' => 'Temperatura',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('tipos-magnitud.index'));

    $tipoMagnitud = TipoMagnitud::firstOrFail();

    expect($tipoMagnitud->nombre)->toBe('Temperatura')
        ->and($tipoMagnitud->tenant_id)->toBe($this->tenant->id);
});

test('rechaza un tipo de magnitud sin nombre', function () {
    $this->actingAs($this->admin)
        ->post(route('tipos-magnitud.store'), [])
        ->assertSessionHasErrors('nombre');
});

test('el nombre es unico dentro del tenant pero puede repetirse en otro', function () {
    TipoMagnitud::factory()->for($this->tenant)->create(['nombre' => 'Presión']);

    $this->actingAs($this->admin)
        ->post(route('tipos-magnitud.store'), ['nombre' => 'Presión'])
        ->assertSessionHasErrors('nombre');

    $this->actingAs(User::factory()->forTenant(Tenant::factory()->create())->create())
        ->post(route('tipos-magnitud.store'), ['nombre' => 'Presión'])
        ->assertSessionHasNoErrors();
});

test('actualiza el nombre del tipo de magnitud', function () {
    $tipoMagnitud = TipoMagnitud::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('tipos-magnitud.update', $tipoMagnitud), ['nombre' => 'Masa'])
        ->assertRedirect(route('tipos-magnitud.index'));

    expect($tipoMagnitud->fresh()->nombre)->toBe('Masa');
});

test('permite guardar un tipo de magnitud conservando su propio nombre', function () {
    $tipoMagnitud = TipoMagnitud::factory()->for($this->tenant)->create(['nombre' => 'Presión']);

    $this->actingAs($this->admin)
        ->put(route('tipos-magnitud.update', $tipoMagnitud), ['nombre' => 'Presión'])
        ->assertSessionHasNoErrors();
});

test('no permite actualizar ni eliminar tipos de magnitud de otro tenant', function () {
    $ajeno = TipoMagnitud::factory()->create(['nombre' => 'Ajeno']);

    $this->actingAs($this->admin)->put(route('tipos-magnitud.update', $ajeno), ['nombre' => 'Hackeado'])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('tipos-magnitud.destroy', $ajeno))->assertForbidden();

    expect($ajeno->fresh()->nombre)->toBe('Ajeno');
});

test('elimina un tipo de magnitud sin especificaciones asociadas', function () {
    $tipoMagnitud = TipoMagnitud::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->delete(route('tipos-magnitud.destroy', $tipoMagnitud))
        ->assertRedirect(route('tipos-magnitud.index'));

    expect(TipoMagnitud::find($tipoMagnitud->id))->toBeNull()
        ->and(TipoMagnitud::withTrashed()->find($tipoMagnitud->id))->not->toBeNull();
});

test('no elimina un tipo de magnitud que tiene especificaciones asociadas', function () {
    $tipoMagnitud = TipoMagnitud::factory()->for($this->tenant)->create();
    registrarEspecificacionConTipoMagnitud($tipoMagnitud);

    $this->actingAs($this->admin)
        ->from(route('tipos-magnitud.index'))
        ->delete(route('tipos-magnitud.destroy', $tipoMagnitud))
        ->assertRedirect(route('tipos-magnitud.index'));

    expect(TipoMagnitud::find($tipoMagnitud->id))->not->toBeNull();
});
