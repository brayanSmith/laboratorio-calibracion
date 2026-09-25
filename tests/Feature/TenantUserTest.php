<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->platformAdmin = User::factory()->platformAdmin()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Ana Perez',
        'email' => 'ana@equivocado.test',
    ]);
});

test('la edicion del tenant lista sus usuarios sin incluir administradores de plataforma', function () {
    $this->actingAs($this->platformAdmin)
        ->get(route('plataforma.tenants.edit', $this->tenant))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/tenants/edit')
            ->has('users', 1)
            ->where('users.0.id', $this->user->id)
            ->where('users.0.email', 'ana@equivocado.test'));
});

test('el administrador de plataforma puede corregir nombre y correo de un usuario del tenant', function () {
    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.update', [$this->tenant, $this->user]), [
            'name' => 'Ana Perez Ruiz',
            'email' => 'ana@correcto.test',
        ])
        ->assertRedirect();

    $this->user->refresh();

    expect($this->user->name)->toBe('Ana Perez Ruiz')
        ->and($this->user->email)->toBe('ana@correcto.test');
});

test('el correo corregido debe ser unico y puede conservar el propio', function () {
    $otro = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.update', [$this->tenant, $this->user]), [
            'name' => 'Ana Perez',
            'email' => $otro->email,
        ])
        ->assertSessionHasErrors('email');

    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.update', [$this->tenant, $this->user]), [
            'name' => 'Ana Perez',
            'email' => 'ana@equivocado.test',
        ])
        ->assertSessionHasNoErrors();
});

test('el administrador de plataforma asigna una nueva contrasena temporal', function () {
    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.password', [$this->tenant, $this->user]), [
            'password' => 'Temporal#2026x',
        ])
        ->assertRedirect();

    $this->user->refresh();

    expect(Hash::check('Temporal#2026x', $this->user->password))->toBeTrue()
        ->and($this->user->must_change_password)->toBeTrue();
});

test('la nueva contrasena temporal es obligatoria y valida', function () {
    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.password', [$this->tenant, $this->user]), ['password' => ''])
        ->assertSessionHasErrors('password');

    expect($this->user->fresh()->must_change_password)->toBeFalse();
});

test('al asignar una contrasena temporal se cierran las sesiones del usuario', function () {
    config(['session.driver' => 'database']);

    DB::table('sessions')->insert([
        'id' => 'sesion-de-ana',
        'user_id' => $this->user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.password', [$this->tenant, $this->user]), [
            'password' => 'Temporal#2026x',
        ]);

    expect(DB::table('sessions')->where('user_id', $this->user->id)->exists())->toBeFalse();
});

test('no se puede gestionar un usuario que pertenece a otro tenant', function () {
    $otroTenant = Tenant::factory()->create();
    $ajeno = User::factory()->create(['tenant_id' => $otroTenant->id]);

    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.password', [$this->tenant, $ajeno]), ['password' => 'Temporal#2026x'])
        ->assertNotFound();

    expect(Hash::check('Temporal#2026x', $ajeno->fresh()->password))->toBeFalse();
});

test('no se puede gestionar a otro administrador de plataforma desde el tenant', function () {
    $otroAdmin = User::factory()->platformAdmin()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.password', [$this->tenant, $otroAdmin]), ['password' => 'Temporal#2026x'])
        ->assertForbidden();

    $this->actingAs($this->platformAdmin)
        ->put(route('plataforma.tenants.users.update', [$this->tenant, $otroAdmin]), ['name' => 'X', 'email' => 'x@x.test'])
        ->assertForbidden();
});

test('un usuario que no es administrador de plataforma no puede gestionar usuarios de un tenant', function () {
    $intruso = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($intruso)
        ->put(route('plataforma.tenants.users.password', [$this->tenant, $this->user]), ['password' => 'Temporal#2026x'])
        ->assertForbidden();

    $this->actingAs($intruso)
        ->put(route('plataforma.tenants.users.update', [$this->tenant, $this->user]), ['name' => 'X', 'email' => 'x@x.test'])
        ->assertForbidden();
});
