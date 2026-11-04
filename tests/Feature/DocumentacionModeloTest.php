<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Equipos\Models\DocumentoEquipo;
use App\Modules\Equipos\Models\Equipo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->base = crearEquipo($this->tenant, 'EQ-BASE');
});

/** Otro equipo con el mismo tipo, marca y modelo que el base (o con el modelo dado). */
function copiaDeEquipo(Equipo $base, string $codigo, ?string $modelo = null): Equipo
{
    $copia = $base->replicate();
    $copia->fill([
        'codigo' => $codigo,
        'numero_serie' => "SN-{$codigo}",
        'modelo' => $modelo ?? $base->modelo,
    ])->save();

    return $copia;
}

function datosDocumentacionModelo(Equipo $base, array $documentos): array
{
    return [
        'tipo_equipo_id' => $base->tipo_equipo_id,
        'fabricante_id' => $base->fabricante_id,
        'modelo' => $base->modelo,
        'documentos' => $documentos,
    ];
}

test('agrega la documentacion a todos los equipos del mismo tipo, marca y modelo con un solo archivo', function () {
    $mismoModelo = copiaDeEquipo($this->base, 'EQ-IGUAL');
    $otroModelo = copiaDeEquipo($this->base, 'EQ-OTRO-MODELO', 'Modelo Y');
    $deOtroTenant = crearEquipo(Tenant::factory()->create(), 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->post(route('equipos.documentacion-modelo.store'), datosDocumentacionModelo($this->base, [
            ['nombre' => 'Manual de usuario', 'archivo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')],
            ['nombre' => 'Certificado del fabricante', 'archivo' => UploadedFile::fake()->create('certificado.pdf', 50, 'application/pdf')],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(DocumentoEquipo::where('equipo_id', $this->base->id)->pluck('nombre')->sort()->values()->all())
        ->toBe(['Certificado del fabricante', 'Manual de usuario'])
        ->and(DocumentoEquipo::where('equipo_id', $mismoModelo->id)->count())->toBe(2)
        ->and(DocumentoEquipo::where('equipo_id', $otroModelo->id)->count())->toBe(0)
        ->and(DocumentoEquipo::where('equipo_id', $deOtroTenant->id)->count())->toBe(0)
        ->and(Storage::disk('public')->files("documentos/{$this->tenant->id}/compartidos"))->toHaveCount(2);

    $manualDelBase = DocumentoEquipo::where('equipo_id', $this->base->id)->where('nombre', 'Manual de usuario')->sole();
    $manualDelOtro = DocumentoEquipo::where('equipo_id', $mismoModelo->id)->where('nombre', 'Manual de usuario')->sole();

    expect($manualDelOtro->archivo)->toBe($manualDelBase->archivo);
});

test('el archivo compartido solo se borra cuando ningun equipo lo usa', function () {
    $mismoModelo = copiaDeEquipo($this->base, 'EQ-IGUAL');

    $this->actingAs($this->admin)
        ->post(route('equipos.documentacion-modelo.store'), datosDocumentacionModelo($this->base, [
            ['nombre' => 'Manual', 'archivo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')],
        ]));

    $delBase = DocumentoEquipo::where('equipo_id', $this->base->id)->sole();
    $delOtro = DocumentoEquipo::where('equipo_id', $mismoModelo->id)->sole();

    $this->actingAs($this->admin)->delete(route('documentos.destroy', $delBase))->assertRedirect();

    Storage::disk('public')->assertExists($delOtro->archivo);

    $this->actingAs($this->admin)->delete(route('documentos.destroy', $delOtro))->assertRedirect();

    Storage::disk('public')->assertMissing($delOtro->archivo);
});

test('avisa cuando no hay equipos con ese tipo, marca y modelo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.documentacion-modelo.store'), [
            ...datosDocumentacionModelo($this->base, [
                ['nombre' => 'Manual', 'archivo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')],
            ]),
            'modelo' => 'Modelo inexistente',
        ])
        ->assertSessionHasErrors('modelo');

    expect(DocumentoEquipo::count())->toBe(0);
});

test('exige al menos un documento con su nombre y su archivo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.documentacion-modelo.store'), datosDocumentacionModelo($this->base, []))
        ->assertSessionHasErrors('documentos');

    $this->actingAs($this->admin)
        ->post(route('equipos.documentacion-modelo.store'), datosDocumentacionModelo($this->base, [
            ['nombre' => '', 'archivo' => null],
        ]))
        ->assertSessionHasErrors(['documentos.0.nombre', 'documentos.0.archivo']);
});

test('sin permiso de editar equipos no puede subir documentacion', function () {
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::EquiposVer]);

    $this->actingAs($usuario)
        ->post(route('equipos.documentacion-modelo.store'), datosDocumentacionModelo($this->base, [
            ['nombre' => 'Manual', 'archivo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')],
        ]))
        ->assertForbidden();

    expect(DocumentoEquipo::count())->toBe(0);
});
