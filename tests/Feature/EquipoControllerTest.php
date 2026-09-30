<?php

use App\Models\Area;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\DocumentoEquipo;
use App\Models\Equipo;
use App\Models\EquipoEspecificacionTecnica;
use App\Models\EquipoProgramacion;
use App\Models\Fabricante;
use App\Models\Tenant;
use App\Models\TipoMagnitud;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

/**
 * @return array<string, mixed>
 */
function datosEquipoBase(Tenant $tenant, array $overrides = []): array
{
    $tenantId = $tenant->id;
    $area = Area::create(['nombre' => 'Área 1', 'tenant_id' => $tenantId]);

    return array_merge([
        'codigo' => 'EQ-'.fake()->unique()->numerify('####'),
        'tipo_equipo_id' => crearTipoEquipo($tenant)->id,
        'tipo_tecnologia' => 'DIGITAL',
        'modelo' => 'Modelo X',
        'fabricante_id' => Fabricante::create(['nombre' => fake()->unique()->company(), 'tenant_id' => $tenantId])->id,
        'numero_serie' => 'SN-1',
        'area_id' => $area->id,
        'bahia_id' => Bahia::create(['nombre' => 'Bahía 1', 'area_id' => $area->id, 'tenant_id' => $tenantId])->id,
        'condicion_actual' => 'Bueno',
        'cliente_id' => Cliente::create(['nombre' => 'Cliente 1', 'email' => fake()->unique()->safeEmail(), 'tenant_id' => $tenantId])->id,
        'pais_procedencia' => 'Perú',
        'numero_activo' => 'ACT-0001',
        'proveedor' => 'Proveedor 1',
        'costo_usd' => 100,
        'fecha_adquisicion' => '2026-01-01',
    ], $overrides);
}

test('crea un equipo con solo los campos base', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant))
        ->assertRedirect();

    $equipo = Equipo::firstOrFail();

    expect($equipo->tenant_id)->toBe($this->tenant->id)
        ->and(EquipoEspecificacionTecnica::where('equipo_id', $equipo->id)->exists())->toBeFalse()
        ->and(EquipoProgramacion::where('equipo_id', $equipo->id)->exists())->toBeFalse()
        ->and(DocumentoEquipo::where('equipo_id', $equipo->id)->exists())->toBeFalse();
});

test('crea un equipo con su ficha tecnica', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'pais_procedencia' => 'Estados Unidos',
            'numero_activo' => 'ACT-9999',
            'proveedor' => 'Fluke Corp',
            'costo_usd' => 1250.5,
            'fecha_adquisicion' => '2025-06-15',
        ]))
        ->assertRedirect();

    $equipo = Equipo::firstOrFail();

    expect($equipo->ficha_tecnica)->toBe([
        'pais_procedencia' => 'Estados Unidos',
        'numero_activo' => 'ACT-9999',
        'proveedor' => 'Fluke Corp',
        'costo_usd' => 1250.5,
        'fecha_adquisicion' => '2025-06-15',
    ]);
});

test('rechaza un equipo sin los datos de la ficha tecnica', function () {
    $datos = datosEquipoBase($this->tenant);
    unset($datos['pais_procedencia'], $datos['numero_activo'], $datos['proveedor'], $datos['costo_usd'], $datos['fecha_adquisicion']);

    $this->actingAs($this->admin)
        ->post(route('equipos.store'), $datos)
        ->assertSessionHasErrors([
            'pais_procedencia', 'numero_activo', 'proveedor', 'costo_usd', 'fecha_adquisicion',
        ]);

    expect(Equipo::count())->toBe(0);
});

test('actualiza la ficha tecnica del equipo', function () {
    $this->actingAs($this->admin)->post(route('equipos.store'), datosEquipoBase($this->tenant));
    $equipo = Equipo::firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('equipos.update', $equipo), datosEquipoBase($this->tenant, [
            'codigo' => $equipo->codigo,
            'tipo_equipo_id' => $equipo->tipo_equipo_id,
            'fabricante_id' => $equipo->fabricante_id,
            'area_id' => $equipo->area_id,
            'bahia_id' => $equipo->bahia_id,
            'cliente_id' => $equipo->cliente_id,
            'proveedor' => 'Nuevo proveedor',
            'costo_usd' => 999.99,
        ]))
        ->assertRedirect();

    $fichaTecnica = $equipo->fresh()->ficha_tecnica;

    expect($fichaTecnica['proveedor'])->toBe('Nuevo proveedor')
        ->and($fichaTecnica['costo_usd'])->toBe(999.99);
});

test('crea un equipo junto con su especificacion tecnica en la misma peticion', function () {
    $tipoMagnitud = TipoMagnitud::factory()->for($this->tenant)->create();
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'tipo_magnitud_id' => $tipoMagnitud->id,
            'unidad_medida_id' => $unidadMedida->id,
            'alcance_indicacion' => '100'.$unidadMedida->simbolo,
            'precision' => '±0.1',
            'resolucion' => '0.01'.$unidadMedida->simbolo,
        ]))
        ->assertRedirect();

    $equipo = Equipo::firstOrFail();
    $especificacion = EquipoEspecificacionTecnica::where('equipo_id', $equipo->id)->firstOrFail();

    expect($especificacion->tipo_magnitud_id)->toBe($tipoMagnitud->id)
        ->and($especificacion->unidad_medida_id)->toBe($unidadMedida->id)
        ->and($especificacion->alcance_indicacion)->toBe('100'.$unidadMedida->simbolo)
        ->and($especificacion->precision)->toBe('±0.1')
        ->and($especificacion->resolucion)->toBe('0.01'.$unidadMedida->simbolo)
        ->and($especificacion->tenant_id)->toBe($this->tenant->id);
});

test('rechaza una especificacion tecnica incompleta al crear el equipo', function () {
    $tipoMagnitud = TipoMagnitud::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'tipo_magnitud_id' => $tipoMagnitud->id,
        ]))
        ->assertSessionHasErrors(['unidad_medida_id', 'alcance_indicacion', 'precision', 'resolucion']);

    expect(Equipo::count())->toBe(0);
});

test('crea un equipo junto con varias programaciones de servicio en la misma peticion', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'programaciones' => [
                [
                    'tipo_servicio' => 'MANTENIMIENTO',
                    'dias_plazo_vencimiento' => 30,
                    'estado_vencimiento' => 'AL_DIA',
                    'fecha_ultimo_servicio' => '2026-01-10',
                ],
                [
                    'tipo_servicio' => 'CALIBRACION',
                    'dias_plazo_vencimiento' => 90,
                    'estado_vencimiento' => 'PROXIMO_A_VENCER',
                ],
            ],
        ]))
        ->assertRedirect();

    $equipo = Equipo::firstOrFail();
    $programaciones = EquipoProgramacion::where('equipo_id', $equipo->id)->get();

    expect($programaciones)->toHaveCount(2)
        ->and($programaciones->pluck('tipo_servicio')->all())->toBe(['MANTENIMIENTO', 'CALIBRACION'])
        ->and($programaciones->first()->fecha_ultimo_servicio->toDateString())->toBe('2026-01-10')
        ->and($programaciones->first()->tenant_id)->toBe($this->tenant->id);
});

test('rechaza una programacion incompleta al crear el equipo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'programaciones' => [
                ['tipo_servicio' => 'MANTENIMIENTO'],
            ],
        ]))
        ->assertSessionHasErrors(['programaciones.0.dias_plazo_vencimiento', 'programaciones.0.estado_vencimiento']);

    expect(Equipo::count())->toBe(0);
});

test('crea un equipo junto con varios documentos en la misma peticion', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'documentos' => [
                ['nombre' => 'Manual', 'archivo' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf')],
                ['nombre' => 'Ficha técnica', 'archivo' => UploadedFile::fake()->create('ficha.pdf', 10, 'application/pdf')],
            ],
        ]))
        ->assertRedirect();

    $equipo = Equipo::firstOrFail();
    $documentos = DocumentoEquipo::where('equipo_id', $equipo->id)->get();

    expect($documentos)->toHaveCount(2)
        ->and($documentos->pluck('nombre')->all())->toBe(['Manual', 'Ficha técnica']);

    $documentos->each(fn (DocumentoEquipo $documento) => Storage::disk('public')->assertExists($documento->archivo));
});

test('rechaza un documento con nombre pero sin archivo al crear el equipo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'documentos' => [
                ['nombre' => 'Manual'],
            ],
        ]))
        ->assertSessionHasErrors(['documentos.0.archivo']);

    expect(Equipo::count())->toBe(0);
});

test('rechaza un tipo de magnitud de otro tenant al crear el equipo', function () {
    $ajeno = TipoMagnitud::factory()->create();
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'tipo_magnitud_id' => $ajeno->id,
            'unidad_medida_id' => $unidadMedida->id,
            'alcance_indicacion' => '1'.$unidadMedida->simbolo,
            'precision' => '±1',
            'resolucion' => '1'.$unidadMedida->simbolo,
        ]))
        ->assertSessionHasErrors('tipo_magnitud_id');

    expect(Equipo::count())->toBe(0);
});
