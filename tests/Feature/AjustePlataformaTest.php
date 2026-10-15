<?php

use App\Models\AjustePlataforma;
use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->platformAdmin()->create(['tenant_id' => $this->tenant->id]);
});

test('la bienvenida usa el nombre de la aplicacion cuando no hay ajustes', function () {
    config(['app.name' => 'Mi App']);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('plataforma.nombre', 'Mi App')
            ->where('plataforma.logo_url', null));
});

test('la bienvenida publica el nombre y el logo configurados por el super admin', function () {
    Storage::disk('public')->put('plataforma/logo.png', 'contenido');
    AjustePlataforma::factory()->create(['nombre' => 'Metrolab', 'logo' => 'plataforma/logo.png']);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('plataforma.nombre', 'Metrolab')
            ->where('plataforma.logo_url', '/storage/plataforma/logo.png'));
});

test('solo el administrador de plataforma accede a los ajustes', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('plataforma.ajustes.edit'))->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('plataforma.ajustes.edit'))->assertForbidden();
    $this->actingAs($user)->post(route('plataforma.ajustes.update'), ['nombre' => 'Hack'])->assertForbidden();

    expect(AjustePlataforma::query()->count())->toBe(0);
});

test('el administrador de plataforma guarda el nombre y el logo', function () {
    $this->actingAs($this->admin)
        ->get(route('plataforma.ajustes.edit'))
        ->assertInertia(fn ($page) => $page->component('plataforma/ajustes'));

    $this->actingAs($this->admin)
        ->post(route('plataforma.ajustes.update'), [
            'nombre' => 'Metrolab',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertRedirect(route('plataforma.ajustes.edit'));

    $ajuste = AjustePlataforma::query()->firstOrFail();

    expect($ajuste->nombre)->toBe('Metrolab')
        ->and($ajuste->logo)->toStartWith('plataforma/');
    Storage::disk('public')->assertExists($ajuste->logo);
});

test('reemplazar o quitar el logo borra el archivo anterior', function () {
    Storage::disk('public')->put('plataforma/viejo.png', 'contenido');
    AjustePlataforma::factory()->create(['logo' => 'plataforma/viejo.png']);

    $this->actingAs($this->admin)->post(route('plataforma.ajustes.update'), [
        'nombre' => 'Metrolab',
        'logo' => UploadedFile::fake()->image('nuevo.png'),
    ]);

    Storage::disk('public')->assertMissing('plataforma/viejo.png');
    $nuevo = AjustePlataforma::query()->firstOrFail()->logo;
    Storage::disk('public')->assertExists($nuevo);

    $this->actingAs($this->admin)->post(route('plataforma.ajustes.update'), [
        'nombre' => 'Metrolab',
        'eliminar_logo' => '1',
    ]);

    Storage::disk('public')->assertMissing($nuevo);
    expect(AjustePlataforma::query()->firstOrFail()->logo)->toBeNull();
});

test('el logo debe ser una imagen valida', function () {
    $this->actingAs($this->admin)
        ->post(route('plataforma.ajustes.update'), [
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('logo');
});

test('la pestana del navegador usa el logo y el nombre de la plataforma', function () {
    Storage::disk('public')->put('plataforma/logo.png', 'contenido');
    AjustePlataforma::factory()->create(['nombre' => 'Metrolab', 'logo' => 'plataforma/logo.png']);

    $this->get(route('login'))
        ->assertSee('Metrolab</title>', false)
        ->assertSee('<link rel="icon" href="/storage/plataforma/logo.png"', false);
});

test('el logo y el nombre de la empresa del tenant tienen prioridad en la pestana', function () {
    Storage::disk('public')->put('plataforma/logo.png', 'contenido');
    AjustePlataforma::factory()->create(['nombre' => 'Metrolab', 'logo' => 'plataforma/logo.png']);
    Empresa::create([
        'nit' => '900123456-7',
        'nombre' => 'Lab Norte',
        'direccion' => 'Calle 1 # 2-3',
        'telefono' => '3001234567',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->get('/')
        ->assertSee('Lab Norte</title>', false)
        ->assertDontSee('Metrolab</title>', false);
});
