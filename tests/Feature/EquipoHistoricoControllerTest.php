<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Equipos\Models\EquipoHistorico;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->equipo = crearEquipo($this->tenant);
    $this->seimet = EmpresaTercero::factory()->create(['nombre' => 'SEIMET', 'tenant_id' => $this->tenant->id]);
});

function datosHistorico(array $sobrescribir = []): array
{
    return [
        'tipo_servicio' => 'CALIBRACION',
        'fecha_servicio' => '2024-06-15',
        'es_tercero' => '1',
        'empresa_tercero_id' => test()->seimet->id,
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
        ->assertJsonPath('empresa_tercero_id', $this->seimet->id)
        ->assertJsonPath('empresa_tercero', 'SEIMET')
        ->assertJsonPath('es_tercero', true)
        ->assertJsonPath('adjunto_nombre', 'certificado.pdf');

    $historico = EquipoHistorico::sole();

    expect($historico->equipo_id)->toBe($this->equipo->id)
        ->and($historico->tenant_id)->toBe($this->tenant->id)
        ->and($historico->adjunto)->toStartWith("historicos/{$this->tenant->id}/{$this->equipo->id}/");
    Storage::disk('public')->assertExists($historico->adjunto);
});

test('el adjunto es opcional y sin marcar tercero queda como propio, sin empresa tercera', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.historicos.store', $this->equipo), datosHistorico(['es_tercero' => null]))
        ->assertCreated()
        ->assertJsonPath('adjunto_url', null)
        ->assertJsonPath('empresa_tercero_id', null);

    expect(EquipoHistorico::sole())
        ->es_tercero->toBeFalse()
        ->empresa_tercero_id->toBeNull();
});

test('si lo hizo un tercero exige una empresa tercera del mismo tenant', function () {
    $ajena = EmpresaTercero::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('equipos.historicos.store', $this->equipo), datosHistorico(['empresa_tercero_id' => null]))
        ->assertJsonValidationErrors(['empresa_tercero_id' => 'Selecciona la empresa tercera que hizo el servicio.']);

    $this->actingAs($this->admin)
        ->postJson(route('equipos.historicos.store', $this->equipo), datosHistorico(['empresa_tercero_id' => $ajena->id]))
        ->assertJsonValidationErrors('empresa_tercero_id');

    expect(EquipoHistorico::count())->toBe(0);
});

test('valida los datos del historico', function () {
    $this->actingAs($this->admin)
        ->postJson(route('equipos.historicos.store', $this->equipo), [])
        ->assertJsonValidationErrors(['tipo_servicio', 'fecha_servicio']);

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
