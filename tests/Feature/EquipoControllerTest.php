<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\AlcancesMedicion\Models\MedicionAlcance;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Catalogos\Models\TipoMagnitud;
use App\Modules\Catalogos\Models\UnidadMedida;
use App\Modules\Equipos\Models\DocumentoEquipo;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoEspecificacionTecnica;
use App\Modules\Equipos\Models\EquipoProgramacion;
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

test('el codigo y nombre del equipo se concatena solo a partir del tipo de equipo y el codigo', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, ['codigo' => 'EQ-0001']))
        ->assertRedirect();

    $equipo = Equipo::firstOrFail();

    expect($equipo->codigo_nombre)->toBe('Manómetro - EQ-0001');

    $equipo->tipoEquipo->update(['nombre' => 'Vacuómetro']);

    expect($equipo->fresh()->codigo_nombre)->toBe('Vacuómetro - EQ-0001');
});

test('acepta una resolucion de hasta 4 decimales', function () {
    $unidadMedida = UnidadMedida::create(['nombre' => 'Milímetro', 'simbolo' => 'mm', 'tenant_id' => $this->tenant->id]);
    $especificacion = fn (string $resolucion): array => datosEquipoBase($this->tenant, [
        'tipo_magnitud_id' => TipoMagnitud::create(['nombre' => fake()->unique()->word(), 'tenant_id' => $this->tenant->id])->id,
        'unidad_medida_id' => $unidadMedida->id,
        'alcance_indicacion' => '0 a 25mm',
        'precision' => '±0.5',
        'resolucion' => $resolucion,
    ]);

    $this->actingAs($this->admin)
        ->post(route('equipos.store'), $especificacion('0.0005mm'))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('equipos.store'), $especificacion('0.00005mm'))
        ->assertSessionHasErrors('resolucion');

    expect(EquipoEspecificacionTecnica::sole()->resolucion)->toBe('0.0005mm');
});

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

test('guarda la criticidad del equipo al crearlo y al editarlo, y la deja vacia si no se indica', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, ['criticidad' => 'ALTA']))
        ->assertSessionHasNoErrors();

    $equipo = Equipo::sole();

    expect($equipo->criticidad)->toBe('ALTA');

    $this->actingAs($this->admin)
        ->put(route('equipos.update', $equipo), datosEquipoBase($this->tenant, ['codigo' => $equipo->codigo, 'criticidad' => 'BAJA']))
        ->assertSessionHasNoErrors();

    expect($equipo->fresh()->criticidad)->toBe('BAJA');

    $this->actingAs($this->admin)
        ->put(route('equipos.update', $equipo), datosEquipoBase($this->tenant, ['codigo' => $equipo->codigo, 'criticidad' => null]))
        ->assertSessionHasNoErrors();

    expect($equipo->fresh()->criticidad)->toBeNull();
});

test('rechaza una criticidad que no sea alta, media o baja', function () {
    $this->actingAs($this->admin)
        ->post(route('equipos.store'), datosEquipoBase($this->tenant, ['criticidad' => 'URGENTE']))
        ->assertSessionHasErrors('criticidad');

    expect(Equipo::count())->toBe(0);
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

test('el listado de equipos incluye el historial de seguimiento de cada programacion, del mas reciente al mas antiguo', function () {
    $this->actingAs($this->admin)->post(route('equipos.store'), datosEquipoBase($this->tenant));

    $programacion = Equipo::firstOrFail()->equipoProgramaciones()->create([
        'tipo_servicio' => 'MANTENIMIENTO',
        'tenant_id' => $this->tenant->id,
    ]);
    $programacion->registrarSeguimiento('MANTENIMIENTO', 'EN_PROCESO');

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('equipos/index')
            ->where('equipos.0.equipo_programaciones.0.seguimientos.0.fase', 'MANTENIMIENTO')
            ->where('equipos.0.equipo_programaciones.0.seguimientos.0.seguimiento', 'EN_PROCESO')
            ->where('equipos.0.equipo_programaciones.0.seguimientos.0.usuario.name', $this->admin->name));
});

test('el listado de equipos incluye los alcances de medicion registrados del tenant, para saber que equipos ya tienen el suyo', function () {
    $alcance = MedicionAlcance::factory()->create(['tenant_id' => $this->tenant->id, 'modelo' => 'Modelo X', 'alcance_indicacion' => '0 - 10 bar']);
    MedicionAlcance::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('options.alcancesMedicion', 1)
            ->where('options.alcancesMedicion.0', [
                'tipo_equipo_id' => $alcance->tipo_equipo_id,
                'modelo' => 'Modelo X',
                'alcance_indicacion' => '0 - 10 bar',
            ]));
});

test('rechaza eliminar una programacion cuyo servicio ya se gestiono', function () {
    $this->actingAs($this->admin)->post(route('equipos.store'), datosEquipoBase($this->tenant));
    $equipo = Equipo::firstOrFail();
    $gestionada = $equipo->equipoProgramaciones()->create([
        'tipo_servicio' => 'MANTENIMIENTO',
        'estado_programacion' => 'RECIBIDO',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('equipos.update', $equipo), datosEquipoBase($this->tenant, [
            'codigo' => $equipo->codigo,
            'tipo_equipo_id' => $equipo->tipo_equipo_id,
            'fabricante_id' => $equipo->fabricante_id,
            'area_id' => $equipo->area_id,
            'bahia_id' => $equipo->bahia_id,
            'cliente_id' => $equipo->cliente_id,
            'programaciones_eliminar' => [$gestionada->id],
        ]))
        ->assertSessionHasErrors('programaciones_eliminar.0');

    expect(EquipoProgramacion::find($gestionada->id))->not->toBeNull();
});

test('las opciones del formulario incluyen el tipo de mantenimiento de cada tipo de equipo', function () {
    $tipo = crearTipoEquipo($this->tenant);

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('equipos/index')
            ->where('options.tipoEquipos.0.id', $tipo->id)
            ->where('options.tipoEquipos.0.tipo_mantenimiento', $tipo->tipo_mantenimiento));
});

test('el listado envia todos los equipos del tenant sin paginar para que la tabla los filtre en el navegador', function () {
    $bahia = Bahia::factory()->for(crearArea($this->tenant))->create();

    foreach (range(1, 20) as $numero) {
        crearEquipoParaBusqueda($bahia, "EQ-$numero");
    }

    crearEquipoParaBusqueda(Bahia::factory()->create(), 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->get(route('equipos.index'))
        ->assertInertia(fn ($page) => $page
            ->has('equipos', 20)
            ->has('equipos.0.equipo_documentos')
            ->missing('filtros'));
});
