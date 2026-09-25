<?php

use App\Enums\TenantRole;
use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function registrarEmpresa(Tenant $tenant, string $nombre = 'Laboratorio Metrológico SAS', ?string $logo = null): Empresa
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

function datosEmpresa(array $sobrescribir = []): array
{
    return [
        'nit' => '900123456-7',
        'nombre' => 'Laboratorio Metrológico SAS',
        'direccion' => 'Calle 1 # 2-3',
        'telefono' => '3001234567',
        ...$sobrescribir,
    ];
}

test('muestra la opcion de registrar cuando el tenant no tiene empresa', function () {
    registrarEmpresa(Tenant::factory()->create(), 'Ajena');

    $this->actingAs($this->admin)
        ->get(route('empresa.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('empresa/show')->where('empresa', null));
});

test('muestra unicamente la empresa del tenant con la ruta publica de su logo', function () {
    Storage::disk('public')->put('logos/1/logo.png', 'contenido');
    registrarEmpresa($this->tenant, 'Mi empresa', 'logos/1/logo.png');
    registrarEmpresa(Tenant::factory()->create(), 'Ajena');

    $this->actingAs($this->admin)
        ->get(route('empresa.show'))
        ->assertInertia(fn ($page) => $page
            ->where('empresa.nombre', 'Mi empresa')
            ->where('empresa.logo_url', '/storage/logos/1/logo.png'));
});

test('un tecnico puede ver la empresa pero no gestionarla', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $empresa = registrarEmpresa($this->tenant);

    $this->actingAs($tecnico)->get(route('empresa.show'))->assertOk();
    $this->actingAs($tecnico)->put(route('empresa.update'), datosEmpresa(['nombre' => 'Cambiada']))->assertForbidden();
    $this->actingAs($tecnico)->delete(route('empresa.destroy'))->assertForbidden();

    expect($empresa->fresh()->nombre)->toBe('Laboratorio Metrológico SAS');
});

test('un tecnico no puede registrar la empresa', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->post(route('empresa.store'), datosEmpresa())->assertForbidden();

    expect(Empresa::count())->toBe(0);
});

test('un usuario sin rol no puede ver la empresa', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('empresa.show'))->assertForbidden();
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('empresa.show'))->assertRedirect(route('login'));
});

test('registra la empresa asignada al tenant del usuario y guarda el logo', function () {
    $this->actingAs($this->admin)
        ->post(route('empresa.store'), datosEmpresa([
            'tenant_id' => Tenant::factory()->create()->id,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]))
        ->assertRedirect(route('empresa.show'));

    $empresa = Empresa::firstOrFail();

    expect($empresa->tenant_id)->toBe($this->tenant->id)
        ->and($empresa->nombre)->toBe('Laboratorio Metrológico SAS')
        ->and($empresa->logo)->toStartWith("logos/{$this->tenant->id}/");

    Storage::disk('public')->assertExists($empresa->logo);
});

test('registra la empresa sin logo', function () {
    $this->actingAs($this->admin)->post(route('empresa.store'), datosEmpresa())->assertSessionHasNoErrors();

    expect(Empresa::firstOrFail()->logo)->toBeNull();
});

test('no permite registrar una segunda empresa en el mismo tenant', function () {
    registrarEmpresa($this->tenant, 'Primera');

    $this->actingAs($this->admin)
        ->post(route('empresa.store'), datosEmpresa(['nombre' => 'Segunda']))
        ->assertRedirect(route('empresa.show'));

    expect(Empresa::count())->toBe(1)
        ->and(Empresa::first()->nombre)->toBe('Primera');
});

test('cada tenant puede registrar su propia empresa', function () {
    registrarEmpresa($this->tenant, 'Primera');
    $otroAdmin = User::factory()->forTenant(Tenant::factory()->create())->create();

    $this->actingAs($otroAdmin)
        ->post(route('empresa.store'), datosEmpresa(['nombre' => 'De otro tenant']))
        ->assertSessionHasNoErrors();

    expect(Empresa::count())->toBe(2);
});

test('la base de datos rechaza dos empresas para el mismo tenant', function () {
    registrarEmpresa($this->tenant, 'Primera');

    registrarEmpresa($this->tenant, 'Segunda');
})->throws(UniqueConstraintViolationException::class);

test('rechaza registrar una empresa sin datos', function () {
    $this->actingAs($this->admin)
        ->post(route('empresa.store'), [])
        ->assertSessionHasErrors(['nit', 'nombre', 'direccion', 'telefono']);
});

test('rechaza un logo que no es una imagen permitida o supera los 2 MB', function (UploadedFile $logo) {
    $this->actingAs($this->admin)
        ->post(route('empresa.store'), datosEmpresa(['logo' => $logo]))
        ->assertSessionHasErrors('logo');

    expect(Empresa::count())->toBe(0);
})->with([
    'un pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
    'un svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    'una imagen de mas de 2 MB' => fn () => UploadedFile::fake()->image('logo.png')->size(2049),
]);

test('actualiza los datos de la empresa conservando el logo', function () {
    Storage::disk('public')->put('logos/1/logo.png', 'contenido');
    $empresa = registrarEmpresa($this->tenant, 'Vieja', 'logos/1/logo.png');

    $this->actingAs($this->admin)
        ->put(route('empresa.update'), datosEmpresa(['nombre' => 'Nueva', 'telefono' => '3110000000']))
        ->assertRedirect(route('empresa.show'));

    expect($empresa->fresh())
        ->nombre->toBe('Nueva')
        ->telefono->toBe('3110000000')
        ->logo->toBe('logos/1/logo.png');
    Storage::disk('public')->assertExists('logos/1/logo.png');
});

test('reemplaza el logo y borra el archivo anterior', function () {
    Storage::disk('public')->put('logos/1/viejo.png', 'contenido');
    $empresa = registrarEmpresa($this->tenant, logo: 'logos/1/viejo.png');

    $this->actingAs($this->admin)
        ->put(route('empresa.update'), datosEmpresa(['logo' => UploadedFile::fake()->image('nuevo.png')]))
        ->assertSessionHasNoErrors();

    $nuevo = $empresa->fresh()->logo;

    expect($nuevo)->not->toBe('logos/1/viejo.png');
    Storage::disk('public')->assertMissing('logos/1/viejo.png');
    Storage::disk('public')->assertExists($nuevo);
});

test('quita el logo cuando se pide eliminarlo', function () {
    Storage::disk('public')->put('logos/1/logo.png', 'contenido');
    $empresa = registrarEmpresa($this->tenant, logo: 'logos/1/logo.png');

    $this->actingAs($this->admin)
        ->put(route('empresa.update'), datosEmpresa(['eliminar_logo' => true]))
        ->assertSessionHasNoErrors();

    expect($empresa->fresh()->logo)->toBeNull();
    Storage::disk('public')->assertMissing('logos/1/logo.png');
});

test('rechaza actualizar con datos invalidos', function () {
    $empresa = registrarEmpresa($this->tenant);

    $this->actingAs($this->admin)
        ->put(route('empresa.update'), datosEmpresa(['nit' => '']))
        ->assertSessionHasErrors('nit');

    expect($empresa->fresh()->nit)->toBe('900123456-7');
});

test('devuelve 404 al actualizar cuando el tenant aun no tiene empresa', function () {
    $this->actingAs($this->admin)->put(route('empresa.update'), datosEmpresa())->assertNotFound();
});

test('actualizar solo afecta la empresa del tenant del usuario', function () {
    $ajena = registrarEmpresa(Tenant::factory()->create(), 'Ajena');
    $propia = registrarEmpresa($this->tenant, 'Propia');

    $this->actingAs($this->admin)->put(route('empresa.update'), datosEmpresa(['nombre' => 'Editada']));

    expect($propia->fresh()->nombre)->toBe('Editada')
        ->and($ajena->fresh()->nombre)->toBe('Ajena');
});

test('elimina la empresa y su logo', function () {
    Storage::disk('public')->put('logos/1/logo.png', 'contenido');
    $empresa = registrarEmpresa($this->tenant, logo: 'logos/1/logo.png');

    $this->actingAs($this->admin)->delete(route('empresa.destroy'))->assertRedirect(route('empresa.show'));

    expect(Empresa::find($empresa->id))->toBeNull()
        ->and(Empresa::withTrashed()->find($empresa->id)->logo)->toBeNull();
    Storage::disk('public')->assertMissing('logos/1/logo.png');
});

test('tras eliminar la empresa se puede registrar una nueva sin duplicar filas', function () {
    registrarEmpresa($this->tenant, 'Vieja')->delete();

    $this->actingAs($this->admin)
        ->post(route('empresa.store'), datosEmpresa(['nombre' => 'Nueva']))
        ->assertSessionHasNoErrors();

    expect(Empresa::withTrashed()->count())->toBe(1)
        ->and(Empresa::firstOrFail()->nombre)->toBe('Nueva');
});

test('devuelve 404 al eliminar cuando el tenant aun no tiene empresa', function () {
    $this->actingAs($this->admin)->delete(route('empresa.destroy'))->assertNotFound();
});
