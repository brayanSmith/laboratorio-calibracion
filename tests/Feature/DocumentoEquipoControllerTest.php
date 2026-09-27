<?php

use App\Enums\TenantRole;
use App\Models\DocumentoEquipo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->equipo = crearEquipoDeTipo(crearTipoEquipo($this->tenant));
});

test('agrega un documento al equipo y guarda el archivo en el disco publico', function () {
    $archivo = UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf');

    $this->actingAs($this->admin)
        ->post(route('equipos.documentos.store', $this->equipo), [
            'nombre' => 'Manual de usuario',
            'archivo' => $archivo,
        ])
        ->assertRedirect();

    $documento = DocumentoEquipo::where('equipo_id', $this->equipo->id)->firstOrFail();

    expect($documento->nombre)->toBe('Manual de usuario')
        ->and($documento->tenant_id)->toBe($this->tenant->id);

    Storage::disk('public')->assertExists($documento->archivo);
});

test('rechaza un documento sin nombre ni archivo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.documentos.store', $this->equipo), [])
        ->assertSessionHasErrors(['nombre', 'archivo']);
});

test('rechaza un archivo con una extension no permitida', function () {
    $archivo = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');

    $this->actingAs($this->admin)
        ->post(route('equipos.documentos.store', $this->equipo), [
            'nombre' => 'Sospechoso',
            'archivo' => $archivo,
        ])
        ->assertSessionHasErrors('archivo');
});

test('un usuario sin permiso de editar equipos no puede agregar documentos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();

    $this->actingAs($recepcion)
        ->post(route('equipos.documentos.store', $this->equipo), [
            'nombre' => 'Manual',
            'archivo' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('no permite agregar documentos a un equipo de otro tenant', function () {
    $ajeno = crearEquipoDeTipo(crearTipoEquipo(Tenant::factory()->create()), 'EQ-9999');

    $this->actingAs($this->admin)
        ->post(route('equipos.documentos.store', $ajeno), [
            'nombre' => 'Manual',
            'archivo' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('elimina un documento y borra el archivo del disco', function () {
    $this->actingAs($this->admin)->post(route('equipos.documentos.store', $this->equipo), [
        'nombre' => 'Manual',
        'archivo' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
    ]);

    $documento = DocumentoEquipo::where('equipo_id', $this->equipo->id)->firstOrFail();
    $ruta = $documento->archivo;

    $this->actingAs($this->admin)
        ->delete(route('documentos.destroy', $documento))
        ->assertRedirect();

    expect(DocumentoEquipo::find($documento->id))->toBeNull();
    Storage::disk('public')->assertMissing($ruta);
});

test('no permite eliminar un documento de otro tenant', function () {
    $ajeno = crearEquipoDeTipo(crearTipoEquipo(Tenant::factory()->create()), 'EQ-9999');
    $documento = $ajeno->equipoDocumentos()->create([
        'nombre' => 'Manual',
        'archivo' => 'documentos/ajeno/manual.pdf',
        'tenant_id' => $ajeno->tenant_id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('documentos.destroy', $documento))
        ->assertForbidden();

    expect(DocumentoEquipo::find($documento->id))->not->toBeNull();
});

test('un usuario sin permiso de editar equipos no puede eliminar documentos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();

    $this->actingAs($this->admin)->post(route('equipos.documentos.store', $this->equipo), [
        'nombre' => 'Manual',
        'archivo' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
    ]);
    $documento = DocumentoEquipo::where('equipo_id', $this->equipo->id)->firstOrFail();

    $this->actingAs($recepcion)
        ->delete(route('documentos.destroy', $documento))
        ->assertForbidden();
});
