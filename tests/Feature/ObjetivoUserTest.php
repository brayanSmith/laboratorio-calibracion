<?php

use App\Enums\TenantRole;
use App\Models\ObjetivoUser;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
});

/**
 * Los 12 meses con el mismo valor, salvo los indicados.
 *
 * @param  array<int, int|string|null>  $cambios
 * @return array<int, int|string|null>
 */
function mesesConObjetivo(int|string|null $valor, array $cambios = []): array
{
    return array_replace(array_fill(1, 12, $valor), $cambios);
}

test('lista los objetivos del tenant con mantenimientos y calibraciones en la misma fila', function () {
    foreach ([1 => 10, 2 => 10, 3 => 12] as $mes => $valor) {
        ObjetivoUser::factory()->for($this->tecnico)->for($this->tenant)->create(['anio' => 2026, 'mes' => $mes, 'tipo' => 'MANTENIMIENTO', 'valor' => $valor]);
    }
    ObjetivoUser::factory()->mensual()->create();

    $this->actingAs($this->admin)
        ->get(route('objetivos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('objetivos/index')
            ->has('objetivos', 1)
            ->where('objetivos.0.usuario', $this->tecnico->name)
            ->where('objetivos.0.tipos.MANTENIMIENTO.meses', [1 => 10, 2 => 10, 3 => 12])
            ->where('objetivos.0.tipos.MANTENIMIENTO.total', 32)
            ->where('objetivos.0.tipos.CALIBRACION', null)
            ->has('usuarios', 2)
            ->has('tiposEquipo'));
});

test('guarda mantenimientos y calibraciones de un año y al volver a guardar quita los meses vacios', function () {
    $datos = ['user_id' => $this->tecnico->id, 'anio' => 2026];

    $this->actingAs($this->admin)
        ->put(route('objetivos.store'), [...$datos, 'meses' => [
            'MANTENIMIENTO' => mesesConObjetivo(10),
            'CALIBRACION' => mesesConObjetivo(4),
        ]])
        ->assertRedirect(route('objetivos.index'));

    expect(ObjetivoUser::where('tipo', 'MANTENIMIENTO')->count())->toBe(12)
        ->and(ObjetivoUser::where('tipo', 'CALIBRACION')->count())->toBe(12);

    $this->actingAs($this->admin)
        ->put(route('objetivos.store'), [...$datos, 'meses' => [
            'MANTENIMIENTO' => mesesConObjetivo(10, [7 => 15, 12 => '']),
            'CALIBRACION' => mesesConObjetivo(''),
        ]])
        ->assertRedirect(route('objetivos.index'));

    $mantenimientos = ObjetivoUser::where('tipo', 'MANTENIMIENTO')->get();

    expect($mantenimientos)->toHaveCount(11)
        ->and($mantenimientos->firstWhere('mes', 7)->valor)->toBe('15.00')
        ->and(ObjetivoUser::where('tipo', 'CALIBRACION')->count())->toBe(0);
});

test('valida el usuario del tenant, los valores y que haya al menos un objetivo', function () {
    $ajeno = User::factory()->forTenant(Tenant::factory()->create())->create();

    $this->actingAs($this->admin)
        ->put(route('objetivos.store'), ['user_id' => $ajeno->id, 'anio' => 2026, 'meses' => ['CALIBRACION' => mesesConObjetivo(-1)]])
        ->assertSessionHasErrors(['user_id', 'meses.CALIBRACION.1']);

    $this->actingAs($this->admin)
        ->put(route('objetivos.store'), ['user_id' => $this->tecnico->id, 'anio' => 2026, 'meses' => [
            'MANTENIMIENTO' => mesesConObjetivo(''),
            'CALIBRACION' => mesesConObjetivo(''),
        ]])
        ->assertSessionHasErrors('meses');

    expect(ObjetivoUser::count())->toBe(0);
});

test('elimina todos los objetivos de un usuario en un año sin tocar los de otros años', function () {
    ObjetivoUser::factory()->for($this->tecnico)->for($this->tenant)->count(2)->sequence(['tipo' => 'MANTENIMIENTO'], ['tipo' => 'CALIBRACION'])->create(['anio' => 2026, 'mes' => 1]);
    $otroAnio = ObjetivoUser::factory()->for($this->tecnico)->for($this->tenant)->create(['anio' => 2027, 'mes' => 1]);

    $this->actingAs($this->admin)
        ->delete(route('objetivos.destroy', [$this->tecnico, 2026]))
        ->assertRedirect(route('objetivos.index'));

    expect(ObjetivoUser::sole()->is($otroAnio))->toBeTrue();
});

test('sin el permiso de objetivos no se ve ni se modifica el modulo', function () {
    $this->actingAs($this->tecnico)->get(route('objetivos.index'))->assertForbidden();
    $this->actingAs($this->tecnico)
        ->put(route('objetivos.store'), ['user_id' => $this->tecnico->id, 'anio' => 2026, 'meses' => ['MANTENIMIENTO' => mesesConObjetivo(5)]])
        ->assertForbidden();
    $this->actingAs($this->tecnico)
        ->delete(route('objetivos.destroy', [$this->tecnico, 2026]))
        ->assertForbidden();
});

test('no se eliminan objetivos de usuarios de otro tenant', function () {
    $ajeno = ObjetivoUser::factory()->mensual()->create(['anio' => 2026]);

    $this->actingAs($this->admin)
        ->delete(route('objetivos.destroy', [$ajeno->user_id, 2026]))
        ->assertNotFound();

    expect($ajeno->fresh()->trashed())->toBeFalse();
});
