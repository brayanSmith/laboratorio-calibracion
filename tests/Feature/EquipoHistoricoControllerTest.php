<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Equipos\Models\EquipoHistorico;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->equipo = crearEquipo($this->tenant);
});

function datosHistorico(array $sobrescribir = []): array
{
    return [
        'tipo_servicio' => 'CALIBRACION',
        'fecha_servicio' => '2024-06-15',
        'nombre_laboratorio' => 'SEIMET',
        'es_tercero' => '1',
        ...$sobrescribir,
    ];
}

test('agrega un historico al equipo con su adjunto y lo devuelve para mostrarlo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.historicos.store', $this->equipo), datosHistorico([
            'adjunto' => UploadedFile::fake()->createWithContent('certificado.pdf', 'pdf'),
        ]))
        ->assertCreated()
        ->assertJsonPath('tipo_servicio', 'CALIBRACION')
        ->assertJsonPath('fecha_servicio', '2024-06-15')
        ->assertJsonPath('nombre_laboratorio', 'SEIMET')
        ->assertJsonPath('es_tercero', true)
        ->assertJsonPath('adjunto_nombre', 'certificado.pdf');

    $historico = EquipoHistorico::sole();

    expect($historico->equipo_id)->toBe($this->equipo->id)
        ->and($historico->tenant_id)->toBe($this->tenant->id)
        ->and($historico->adjunto)->toStartWith("historicos/{$this->tenant->id}/{$this->equipo->id}/");
    Storage::disk('public')->assertExists($historico->adjunto);
});

test('el adjunto es opcional y sin marcar tercero queda como propio', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.historicos.store', $this->equipo), datosHistorico(['es_tercero' => null]))
        ->assertCreated()
        ->assertJsonPath('adjunto_url', null);

    expect(EquipoHistorico::sole()->es_tercero)->toBeFalse();
});

test('valida los datos del historico', function () {
    $this->actingAs($this->admin)
        ->postJson(route('equipos.historicos.store', $this->equipo), [])
        ->assertJsonValidationErrors(['tipo_servicio', 'fecha_servicio', 'nombre_laboratorio']);

    $this->actingAs($this->admin)
        ->postJson(route('equipos.historicos.store', $this->equipo), datosHistorico([
            'tipo_servicio' => 'VERIFICACION',
            'fecha_servicio' => now()->addDay()->toDateString(),
            'adjunto' => UploadedFile::fake()->create('script.php', 1),
        ]))
        ->assertJsonValidationErrors([
            'tipo_servicio' => 'El tipo de servicio debe ser Mantenimiento o Calibración.',
            'fecha_servicio' => 'La fecha del servicio no puede ser futura.',
            'adjunto' => 'El adjunto debe ser un archivo pdf, jpg, jpeg, png, webp, doc, docx, xls, xlsx.',
        ]);

    expect(EquipoHistorico::count())->toBe(0);
});

test('agregar historico depende de su propio permiso', function () {
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::EquiposVer, TenantPermission::EquiposEditar, TenantPermission::EquiposHistorial]);

    $this->actingAs($usuario)
        ->post(route('equipos.historicos.store', $this->equipo), datosHistorico())
        ->assertForbidden();

    $usuario->givePermissionTo(TenantPermission::EquiposHistoricoAgregar->value);

    $this->actingAs($usuario)
        ->post(route('equipos.historicos.store', $this->equipo), datosHistorico())
        ->assertCreated();
});

test('no permite agregar historico a un equipo de otro tenant', function () {
    $ajeno = crearEquipo(Tenant::factory()->create(), 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->post(route('equipos.historicos.store', $ajeno), datosHistorico())
        ->assertForbidden();

    expect(EquipoHistorico::count())->toBe(0);
});
