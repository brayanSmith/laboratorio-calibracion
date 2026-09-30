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

test('actualiza el equipo junto con su especificacion tecnica, programaciones y documentos en la misma peticion', function () {
    $this->actingAs($this->admin)->post(route('equipos.store'), datosEquipoBase($this->tenant));
    $equipo = Equipo::firstOrFail();

    $tipoMagnitud = TipoMagnitud::factory()->for($this->tenant)->create();
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('equipos.update', $equipo), datosEquipoBase($this->tenant, [
            'codigo' => $equipo->codigo,
            'tipo_equipo_id' => $equipo->tipo_equipo_id,
            'fabricante_id' => $equipo->fabricante_id,
            'area_id' => $equipo->area_id,
            'bahia_id' => $equipo->bahia_id,
            'cliente_id' => $equipo->cliente_id,
            'tipo_magnitud_id' => $tipoMagnitud->id,
            'unidad_medida_id' => $unidadMedida->id,
            'alcance_indicacion' => '100'.$unidadMedida->simbolo,
            'precision' => '±0.1',
            'resolucion' => '0.01'.$unidadMedida->simbolo,
            'programaciones' => [
                ['tipo_servicio' => ['MANTENIMIENTO']],
            ],
            'documentos' => [
                ['nombre' => 'Manual', 'archivo' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf')],
            ],
        ]))
        ->assertRedirect();

    $especificacion = EquipoEspecificacionTecnica::where('equipo_id', $equipo->id)->firstOrFail();
    expect($especificacion->tipo_magnitud_id)->toBe($tipoMagnitud->id);

    expect(EquipoProgramacion::where('equipo_id', $equipo->id)->count())->toBe(1);

    $documento = DocumentoEquipo::where('equipo_id', $equipo->id)->firstOrFail();
    expect($documento->nombre)->toBe('Manual');
    Storage::disk('public')->assertExists($documento->archivo);
});

test('elimina la especificacion tecnica del equipo cuando se guarda sin tipo de magnitud', function () {
    $tipoMagnitud = TipoMagnitud::factory()->for($this->tenant)->create();
    $unidadMedida = UnidadMedida::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->post(route('equipos.store'), datosEquipoBase($this->tenant, [
        'tipo_magnitud_id' => $tipoMagnitud->id,
        'unidad_medida_id' => $unidadMedida->id,
        'alcance_indicacion' => '100'.$unidadMedida->simbolo,
        'precision' => '±0.1',
        'resolucion' => '0.01'.$unidadMedida->simbolo,
    ]));
    $equipo = Equipo::firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('equipos.update', $equipo), datosEquipoBase($this->tenant, [
            'codigo' => $equipo->codigo,
            'tipo_equipo_id' => $equipo->tipo_equipo_id,
            'fabricante_id' => $equipo->fabricante_id,
            'area_id' => $equipo->area_id,
            'bahia_id' => $equipo->bahia_id,
            'cliente_id' => $equipo->cliente_id,
        ]))
        ->assertRedirect();

    expect(EquipoEspecificacionTecnica::where('equipo_id', $equipo->id)->exists())->toBeFalse();
});

test('elimina las programaciones y documentos marcados al actualizar el equipo', function () {
    $this->actingAs($this->admin)->post(route('equipos.store'), datosEquipoBase($this->tenant, [
        'programaciones' => [
            ['tipo_servicio' => ['MANTENIMIENTO']],
        ],
        'documentos' => [
            ['nombre' => 'Manual', 'archivo' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf')],
        ],
    ]));
    $equipo = Equipo::firstOrFail();
    $programacion = EquipoProgramacion::where('equipo_id', $equipo->id)->firstOrFail();
    $documento = DocumentoEquipo::where('equipo_id', $equipo->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('equipos.update', $equipo), datosEquipoBase($this->tenant, [
            'codigo' => $equipo->codigo,
            'tipo_equipo_id' => $equipo->tipo_equipo_id,
            'fabricante_id' => $equipo->fabricante_id,
            'area_id' => $equipo->area_id,
            'bahia_id' => $equipo->bahia_id,
            'cliente_id' => $equipo->cliente_id,
            'programaciones_eliminar' => [$programacion->id],
            'documentos_eliminar' => [$documento->id],
        ]))
        ->assertRedirect();

    expect(EquipoProgramacion::find($programacion->id))->toBeNull()
        ->and(DocumentoEquipo::find($documento->id))->toBeNull();
    Storage::disk('public')->assertMissing($documento->archivo);
});

test('rechaza eliminar una programacion o documento de otro equipo al actualizar', function () {
    $this->actingAs($this->admin)->post(route('equipos.store'), datosEquipoBase($this->tenant));
    $equipo = Equipo::firstOrFail();

    $ajeno = crearEquipoDeTipo(crearTipoEquipo(Tenant::factory()->create()), 'EQ-9999');
    $programacionAjena = EquipoProgramacion::create([
        'equipo_id' => $ajeno->id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'tenant_id' => $ajeno->tenant_id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('equipos.update', $equipo), datosEquipoBase($this->tenant, [
            'codigo' => $equipo->codigo,
            'tipo_equipo_id' => $equipo->tipo_equipo_id,
            'fabricante_id' => $equipo->fabricante_id,
            'area_id' => $equipo->area_id,
            'bahia_id' => $equipo->bahia_id,
            'cliente_id' => $equipo->cliente_id,
            'programaciones_eliminar' => [$programacionAjena->id],
        ]))
        ->assertSessionHasErrors('programaciones_eliminar.0');

    expect(EquipoProgramacion::find($programacionAjena->id))->not->toBeNull();
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
                    'tipo_servicio' => ['MANTENIMIENTO'],
                    'fecha_ultimo_servicio' => '2026-01-10',
                ],
                [
                    'tipo_servicio' => ['CALIBRACION'],
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

test('una programacion puede tener varios tipos de servicio a la vez al crear el equipo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'programaciones' => [
                [
                    'tipo_servicio' => ['MANTENIMIENTO', 'CALIBRACION'],
                ],
            ],
        ]))
        ->assertRedirect();

    $equipo = Equipo::firstOrFail();
    $programacion = EquipoProgramacion::where('equipo_id', $equipo->id)->firstOrFail();

    expect($programacion->tipo_servicio)->toBe('MANTENIMIENTO,CALIBRACION');
});

test('rechaza una programacion incompleta al crear el equipo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, [
            'programaciones' => [
                ['tipo_servicio' => ['MANTENIMIENTO'], 'intervalo_servicio' => 30],
            ],
        ]))
        ->assertSessionHasErrors('programaciones.0.intervalo_unidad');

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
