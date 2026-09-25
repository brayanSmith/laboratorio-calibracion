<?php

use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function empresaConMarca(Tenant $tenant, string $nombre, ?string $logo = null): Empresa
{
    return Empresa::create([
        'nit' => '900123456-7',
        'nombre' => $nombre,
        'direccion' => 'Calle 1 # 2-3',
        'telefono' => '3001234567',
        'logo' => $logo,
        'tenant_id' => $tenant->id,
    ]);
}

test('comparte el nombre y la ruta del logo de la empresa del usuario', function () {
    empresaConMarca($this->tenant, 'Metrología Andina', 'logos/1/logo.png');

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('empresa.nombre', 'Metrología Andina')
            ->where('empresa.logo_url', '/storage/logos/1/logo.png'));
});

test('comparte la empresa sin logo con la ruta del logo vacia', function () {
    empresaConMarca($this->tenant, 'Metrología Andina');

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertInertia(fn ($page) => $page->where('empresa.logo_url', null));
});

test('no comparte empresa cuando el tenant aun no la registro ni usa la de otro tenant', function () {
    empresaConMarca(Tenant::factory()->create(), 'Ajena', 'logos/2/logo.png');

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertInertia(fn ($page) => $page->where('empresa', null));
});

test('un invitado no recibe datos de empresa', function () {
    empresaConMarca($this->tenant, 'Metrología Andina');

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('empresa', null));
});

test('la primera carga usa el nombre y el logo de la empresa en el titulo y el favicon', function () {
    empresaConMarca($this->tenant, 'Metrología Andina', 'logos/1/logo.png');

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertSee('Metrología Andina</title>', false)
        ->assertSee('<link rel="icon" href="/storage/logos/1/logo.png" data-inertia="favicon">', false);
});

test('la primera carga usa la marca de la aplicacion cuando no hay empresa', function () {
    $this->get(route('login'))
        ->assertSee('<link rel="icon" href="/favicon.svg" data-inertia="favicon">', false)
        ->assertSee(config('app.name'));
});

test('escapa el nombre de la empresa en el titulo de la primera carga', function () {
    empresaConMarca($this->tenant, '</title><script>alert(1)</script>');

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;/title&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false);
});
