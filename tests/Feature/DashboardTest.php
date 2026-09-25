<?php

use App\Models\Tenant;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->forTenant(Tenant::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

test('platform administrators are sent to their own panel instead of the laboratory dashboard', function () {
    $platformAdmin = User::factory()->platformAdmin()->create(['tenant_id' => null]);

    $this->actingAs($platformAdmin)
        ->get(route('dashboard'))
        ->assertRedirect(route('plataforma.dashboard'));
});
