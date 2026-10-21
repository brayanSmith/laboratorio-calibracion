<?php

use App\Enums\TenantRole;
use App\Enums\TipoDocumentoCliente;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Catalogos\Models\TipoEquipo;
use App\Modules\Catalogos\Models\TipoMagnitud;
use App\Modules\Catalogos\Models\UnidadMedida;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Importaciones\Jobs\ProcesarImportacion;
use App\Modules\Importaciones\Models\Importacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

/**
 * Encabezados en otro orden y con otros nombres que los del sistema, como suele llegar el CSV.
 */
const ENCABEZADOS_CSV = 'Serie,Código,Tecnología,Modelo,Marca,Tipo de equipo,Cliente,NIT cliente,Área,Bahía,Condición,Magnitud,Unidad,Símbolo,Alcance,Precisión,Resolución,Costo,Fecha compra';

/**
 * Mapeo de los campos del sistema a los índices de las columnas de ENCABEZADOS_CSV.
 *
 * @return array<string, int>
 */
function mapeoEquiposCsv(): array
{
    return [
        'numero_serie' => 0, 'codigo' => 1, 'tipo_tecnologia' => 2, 'modelo' => 3, 'fabricante' => 4,
        'tipo_equipo' => 5, 'cliente' => 6, 'cliente_numero_documento' => 7, 'area' => 8, 'bahia' => 9,
        'condicion_actual' => 10, 'tipo_magnitud' => 11, 'unidad_medida' => 12, 'simbolo_unidad' => 13,
        'alcance_indicacion' => 14, 'precision' => 15, 'resolucion' => 16, 'costo_usd' => 17, 'fecha_adquisicion' => 18,
    ];
}

function csvEquipos(string ...$filas): UploadedFile
{
    return UploadedFile::fake()->createWithContent('equipos.csv', implode("\n", [ENCABEZADOS_CSV, ...$filas]));
}

/**
 * Sube el CSV, lo importa con el mapeo dado y devuelve la importación ya procesada
 * (en las pruebas la cola es síncrona).
 *
 * @param  array<string, int>|null  $mapeo
 */
function importarEquipos(User $usuario, UploadedFile $archivo, ?array $mapeo = null): Importacion
{
    $subido = test()->actingAs($usuario)
        ->post(route('importaciones.encabezados'), [
            'tipo' => 'equipos', 'archivo' => $archivo])
        ->assertOk();

    test()->actingAs($usuario)
        ->postJson(route('importaciones.store'), [
            'tipo' => 'equipos',
            'archivo' => $subido->json('archivo'),
            'nombre_original' => $subido->json('nombre_original'),
            'mapeo' => $mapeo ?? mapeoEquiposCsv(),
        ])
        ->assertCreated();

    return Importacion::latest('id')->firstOrFail();
}

test('descarga la plantilla con todos los campos y una fila de ejemplo que se puede importar', function () {
    $respuesta = $this->actingAs($this->admin)
        ->get(route('importaciones.plantilla', 'equipos'))
        ->assertOk()
        ->assertDownload('plantilla-importacion-equipos.csv');

    $ruta = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($ruta, $respuesta->getFile()->getContent());

    // La plantilla misma, con sus encabezados sugeridos, debe importarse sin errores.
    $subido = $this->actingAs($this->admin)
        ->post(route('importaciones.encabezados'), [
            'tipo' => 'equipos',
            'archivo' => new UploadedFile($ruta, 'plantilla.csv', 'text/csv', test: true),
        ])
        ->assertOk()
        ->assertJsonPath('encabezados.0', 'Código')
        ->assertJsonPath('vista_previa.0.0', 'EQ-0001');

    expect(array_filter($subido->json('sugerencias'), fn (?int $indice): bool => $indice === null))->toBe([]);

    $this->actingAs($this->admin)
        ->postJson(route('importaciones.store'), [
            'tipo' => 'equipos',
            'archivo' => $subido->json('archivo'),
            'nombre_original' => 'plantilla.csv',
            'mapeo' => $subido->json('sugerencias'),
        ])
        ->assertCreated();

    expect(Importacion::sole()->filas_creadas)->toBe(1)
        ->and(Equipo::sole()->codigo)->toBe('EQ-0001');
});

test('un usuario sin permiso de crear equipos no puede descargar la plantilla', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('importaciones.plantilla', 'equipos'))->assertForbidden();
});

test('lee los encabezados del csv y sugiere la columna de cada campo', function () {
    $respuesta = $this->actingAs($this->admin)
        ->post(route('importaciones.encabezados'), [
            'tipo' => 'equipos',
            'archivo' => csvEquipos('SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,'),
        ])
        ->assertOk()
        ->assertJsonPath('encabezados.1', 'Código')
        ->assertJsonPath('vista_previa.0.1', 'EQ-1')
        ->assertJsonPath('sugerencias.codigo', 1)
        ->assertJsonPath('sugerencias.tipo_equipo', 5)
        ->assertJsonPath('sugerencias.area', 8)
        ->assertJsonPath('sugerencias.fabricante', null);

    Storage::disk('local')->assertExists("importaciones/{$this->tenant->id}/{$respuesta->json('archivo')}");
});

test('lee un csv de excel separado por punto y coma y en windows-1252', function () {
    $contenido = mb_convert_encoding("Código;Área\nEQ-1;Planta Norte", 'Windows-1252', 'UTF-8');

    $this->actingAs($this->admin)
        ->post(route('importaciones.encabezados'), [
            'tipo' => 'equipos',
            'archivo' => UploadedFile::fake()->createWithContent('equipos.csv', $contenido),
        ])
        ->assertOk()
        ->assertJsonPath('encabezados', ['Código', 'Área'])
        ->assertJsonPath('vista_previa.0', ['EQ-1', 'Planta Norte']);
});

test('rechaza un csv sin filas de datos', function () {
    $this->actingAs($this->admin)
        ->postJson(route('importaciones.encabezados'), [
            'tipo' => 'equipos', 'archivo' => csvEquipos()])
        ->assertJsonValidationErrors([
            'archivo' => 'El archivo no tiene filas para importar (la primera fila debe ser la de encabezados).',
        ]);
});

test('un usuario sin permiso de crear equipos no puede importar', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)
        ->postJson(route('importaciones.encabezados'), [
            'tipo' => 'equipos', 'archivo' => csvEquipos('SN-1,EQ-1')])
        ->assertForbidden();

    $this->actingAs($tecnico)
        ->postJson(route('importaciones.store'), [
            'tipo' => 'equipos',
            'archivo' => str_repeat('a', 40).'.csv',
            'nombre_original' => 'equipos.csv',
            'mapeo' => mapeoEquiposCsv(),
        ])
        ->assertForbidden();
});

test('importa los equipos y crea los catalogos que no existen', function () {
    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta Norte,B1,Bueno,Presión,Bar,bar,0 a 10bar,±0.5,0.1,"1,250.50",15/06/2025',
        'SN-2,EQ-2,ANALOGICO,M2,Fluke,Manómetro,ACME,900111222,Planta Norte,B1,Regular,,,,,,,,',
    ));

    expect($importacion->estado)->toBe(Importacion::ESTADO_COMPLETADA)
        ->and($importacion->total_filas)->toBe(2)
        ->and($importacion->filas_creadas)->toBe(2)
        ->and($importacion->filas_con_error)->toBe(0);

    $equipo = Equipo::with('equipoEspecificacionTecnica')->where('codigo', 'EQ-1')->firstOrFail();
    $area = Area::where('nombre', 'Planta Norte')->sole();

    expect($equipo->tenant_id)->toBe($this->tenant->id)
        ->and($equipo->tipo_tecnologia)->toBe('DIGITAL')
        ->and($equipo->tipoEquipo->nombre)->toBe('Manómetro')
        ->and($equipo->tipoEquipo->tipo_mantenimiento)->toBe('A')
        ->and($equipo->fabricante->nombre)->toBe('Fluke')
        ->and($equipo->cliente->nombre)->toBe('ACME')
        ->and($equipo->cliente->numero_documento)->toBe('900111222')
        ->and($equipo->cliente->tipo_documento)->toBe(TipoDocumentoCliente::Nit)
        ->and($equipo->cliente->email)->toBe('doc-900111222@sin-correo.local')
        ->and($equipo->area_id)->toBe($area->id)
        ->and($equipo->bahia->nombre)->toBe('B1')
        ->and($equipo->bahia->area_id)->toBe($area->id)
        ->and($equipo->activo)->toBeTrue()
        ->and($equipo->ficha_tecnica)->toMatchArray(['costo_usd' => 1250.5, 'fecha_adquisicion' => '2025-06-15'])
        ->and($equipo->equipoEspecificacionTecnica->tipoMagnitud->nombre)->toBe('Presión')
        ->and($equipo->equipoEspecificacionTecnica->unidadMedida->simbolo)->toBe('bar')
        ->and($equipo->equipoEspecificacionTecnica->precision)->toBe('±0.5');

    // Los catálogos repetidos entre filas se crean una sola vez.
    expect(TipoEquipo::count())->toBe(1)
        ->and(Fabricante::count())->toBe(1)
        ->and(Cliente::count())->toBe(1)
        ->and(Bahia::count())->toBe(1)
        ->and(TipoMagnitud::count())->toBe(1)
        ->and(UnidadMedida::count())->toBe(1);

    Storage::disk('local')->assertMissing($importacion->archivo);
});

test('reutiliza los catalogos existentes del tenant sin importar mayusculas', function () {
    $area = Area::create(['nombre' => 'Planta Norte', 'tenant_id' => $this->tenant->id]);
    $bahia = Bahia::create(['nombre' => 'B1', 'area_id' => $area->id, 'tenant_id' => $this->tenant->id]);
    $cliente = Cliente::factory()->for($this->tenant)->create(['nombre' => 'Acme S.A.S.', 'numero_documento' => '900111222']);
    $unidad = UnidadMedida::create(['nombre' => 'Bar', 'simbolo' => 'bar', 'tenant_id' => $this->tenant->id]);

    importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,acme,900111222,PLANTA NORTE,b1,Bueno,Presión,bar,,0 a 10bar,±0.5,0.1,,',
    ));

    $equipo = Equipo::with('equipoEspecificacionTecnica')->sole();

    expect($equipo->area_id)->toBe($area->id)
        ->and($equipo->bahia_id)->toBe($bahia->id)
        ->and($equipo->cliente_id)->toBe($cliente->id)
        ->and($equipo->equipoEspecificacionTecnica->unidad_medida_id)->toBe($unidad->id)
        ->and(Area::count())->toBe(1)
        ->and(Cliente::count())->toBe(1);
});

test('no distingue mayusculas, minusculas ni espacios repetidos en los catalogos y valores', function () {
    $area = Area::create(['nombre' => 'Área Norte', 'tenant_id' => $this->tenant->id]);
    $bahia = Bahia::create(['nombre' => 'Bahía 1', 'area_id' => $area->id, 'tenant_id' => $this->tenant->id]);
    $tipo = crearTipoEquipo($this->tenant, 'Manómetro', 'B');
    $fabricante = Fabricante::create(['nombre' => 'Fluke', 'tenant_id' => $this->tenant->id]);
    $unidad = UnidadMedida::create(['nombre' => 'Bar', 'simbolo' => 'bar', 'tenant_id' => $this->tenant->id]);
    $cliente = Cliente::factory()->for($this->tenant)->create(['nombre' => 'Acme', 'numero_documento' => 'AB123']);
    $mapeo = [...mapeoEquiposCsv(), 'servicio_mantenimiento' => 19, 'intervalo_servicio' => 20, 'intervalo_unidad' => 21, 'fecha_ultimo_servicio' => 22];

    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,digital,M1,FLUKE,MANÓMETRO,acme,ab123,ÁREA  NORTE,BAHÍA 1,Bueno,presión,BAR,,0 a 10 BAR,±0.5,0.1,,,SÍ,12,MeSeS,2026-01-15',
        'SN-2,eq-1,DIGITAL,M1,Fluke,Manómetro,Acme,AB123,Área Norte,Bahía 1,Bueno,,,,,,,,,,,,',
    ), $mapeo);

    $equipo = Equipo::with('equipoEspecificacionTecnica')->sole();

    expect($importacion->filas_creadas)->toBe(1)
        ->and($importacion->errores[0]['mensaje'])->toBe('Ya existe un equipo con el código eq-1.')
        ->and($equipo->tipo_tecnologia)->toBe('DIGITAL')
        ->and($equipo->tipo_equipo_id)->toBe($tipo->id)
        ->and($equipo->fabricante_id)->toBe($fabricante->id)
        ->and($equipo->cliente_id)->toBe($cliente->id)
        ->and($equipo->area_id)->toBe($area->id)
        ->and($equipo->bahia_id)->toBe($bahia->id)
        ->and($equipo->equipoEspecificacionTecnica->unidad_medida_id)->toBe($unidad->id)
        ->and($equipo->equipoEspecificacionTecnica->alcance_indicacion)->toBe('0 a 10bar')
        ->and($equipo->equipoEspecificacionTecnica->resolucion)->toBe('0.1bar')
        ->and(EquipoProgramacion::sole()->only(['tipo_servicio', 'intervalo_unidad']))
        ->toBe(['tipo_servicio' => 'MANTENIMIENTO', 'intervalo_unidad' => 'MESES'])
        ->and(Area::count())->toBe(1)
        ->and(Bahia::count())->toBe(1)
        ->and(TipoEquipo::count())->toBe(1)
        ->and(Fabricante::count())->toBe(1)
        ->and(UnidadMedida::count())->toBe(1)
        ->and(Cliente::count())->toBe(1);
});

test('no distingue tildes en los nombres de los catalogos', function () {
    $area = Area::create(['nombre' => 'Área Norte', 'tenant_id' => $this->tenant->id]);
    $bahia = Bahia::create(['nombre' => 'Bahía 1', 'area_id' => $area->id, 'tenant_id' => $this->tenant->id]);
    $tipo = crearTipoEquipo($this->tenant, 'Manómetro');
    $magnitud = TipoMagnitud::create(['nombre' => 'Presión', 'tenant_id' => $this->tenant->id]);
    $unidad = UnidadMedida::create(['nombre' => 'Milímetro', 'simbolo' => 'mm', 'tenant_id' => $this->tenant->id]);
    $cliente = Cliente::create(['nombre' => 'Compañía Óptica', 'email' => 'optica@cliente.com', 'tenant_id' => $this->tenant->id]);
    $fabricante = Fabricante::create(['nombre' => 'Fabricación Ñandú', 'tenant_id' => $this->tenant->id]);

    importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Analógico,M1,fabricacion nandu,manometro,COMPANIA OPTICA,900111222,AREA NORTE,bahia 1,Bueno,PRESION,milimetro,,0 a 100,±0.5,0.01,,',
    ));

    $equipo = Equipo::with('equipoEspecificacionTecnica')->sole();

    expect($equipo->tipo_tecnologia)->toBe('ANALOGICO')
        ->and($equipo->tipo_equipo_id)->toBe($tipo->id)
        ->and($equipo->fabricante_id)->toBe($fabricante->id)
        ->and($equipo->cliente_id)->toBe($cliente->id)
        ->and($equipo->area_id)->toBe($area->id)
        ->and($equipo->bahia_id)->toBe($bahia->id)
        ->and($equipo->equipoEspecificacionTecnica->tipo_magnitud_id)->toBe($magnitud->id)
        ->and($equipo->equipoEspecificacionTecnica->unidad_medida_id)->toBe($unidad->id)
        ->and($equipo->equipoEspecificacionTecnica->resolucion)->toBe('0.01mm');
});

test('busca la unidad primero por el simbolo y luego por el nombre sin espacios ni signos', function () {
    $milimetros = UnidadMedida::create(['nombre' => 'Milímetros', 'simbolo' => 'mm', 'tenant_id' => $this->tenant->id]);
    $grados = UnidadMedida::create(['nombre' => 'Grados Celsius', 'simbolo' => '°C', 'tenant_id' => $this->tenant->id]);

    importarEquipos($this->admin, csvEquipos(
        // El nombre no coincide ("Milímetro" en singular), pero el símbolo sí.
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Longitud,Milímetro,MM,0 a 25,±0.5,0.1,,',
        // Sin símbolo: el nombre coincide aunque traiga otros espacios, guiones y mayúsculas.
        'SN-2,EQ-2,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Temperatura,GRADOS-CELSIUS ,,0 a 100,±0.5,0.1,,',
    ));

    $unidadDe = fn (string $codigo): int => Equipo::where('codigo', $codigo)->sole()->equipoEspecificacionTecnica->unidad_medida_id;

    expect($unidadDe('EQ-1'))->toBe($milimetros->id)
        ->and($unidadDe('EQ-2'))->toBe($grados->id)
        ->and(Equipo::where('codigo', 'EQ-2')->sole()->equipoEspecificacionTecnica->resolucion)->toBe('0.1°C')
        ->and(UnidadMedida::count())->toBe(2);
});

test('no confunde simbolos de unidades que solo se diferencian por un caracter especial', function () {
    $metro = UnidadMedida::create(['nombre' => 'Metro', 'simbolo' => 'm', 'tenant_id' => $this->tenant->id]);
    $micra = UnidadMedida::create(['nombre' => 'Micrómetro', 'simbolo' => 'µm', 'tenant_id' => $this->tenant->id]);

    importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Longitud,µm,,0 a 25,±0.5,0.1,,',
        'SN-2,EQ-2,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Longitud,M,,0 a 25,±0.5,0.1,,',
    ));

    $unidadDe = fn (string $codigo): int => Equipo::where('codigo', $codigo)->sole()->equipoEspecificacionTecnica->unidad_medida_id;

    expect($unidadDe('EQ-1'))->toBe($micra->id)
        ->and($unidadDe('EQ-2'))->toBe($metro->id)
        ->and(UnidadMedida::count())->toBe(2);
});

test('identifica al cliente por su documento y no por su nombre', function () {
    $acme = Cliente::factory()->for($this->tenant)->create(['nombre' => 'ACME', 'numero_documento' => '900111222']);

    importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,800999111,Planta,B1,Bueno,,,,,,,,',
    ));

    $nuevo = Equipo::sole()->cliente;

    expect($nuevo->id)->not->toBe($acme->id)
        ->and($nuevo->nombre)->toBe('ACME')
        ->and($nuevo->numero_documento)->toBe('800999111');
});

test('asigna el documento al cliente sin documento que tenga el mismo nombre', function () {
    $antiguo = Cliente::create(['nombre' => 'ACME', 'email' => 'acme@cliente.com', 'tenant_id' => $this->tenant->id]);

    $importacion = importarEquipos(
        $this->admin,
        csvEquipos('SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,CC'),
        [...mapeoEquiposCsv(), 'cliente_tipo_documento' => 19],
    );

    expect($importacion->filas_creadas)->toBe(1)
        ->and(Equipo::sole()->cliente_id)->toBe($antiguo->id)
        ->and($antiguo->fresh()->numero_documento)->toBe('900111222')
        ->and($antiguo->fresh()->tipo_documento)->toBe(TipoDocumentoCliente::Cc)
        ->and(Cliente::count())->toBe(1);
});

test('reporta la fila cuando el documento es de un cliente eliminado', function () {
    Cliente::factory()->for($this->tenant)->create(['numero_documento' => '900111222'])->delete();

    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,',
    ));

    expect($importacion->filas_creadas)->toBe(0)
        ->and($importacion->errores)->toBe([[
            'fila' => 2,
            'mensaje' => 'El cliente con documento 900111222 fue eliminado; restáuralo o usa otro documento.',
        ]])
        ->and(Area::count())->toBe(0);
});

test('crea la primera programacion con el tipo de servicio que corresponde al tipo de equipo', function () {
    crearTipoEquipo($this->tenant, 'Balanza', 'B');
    $mapeo = [
        ...mapeoEquiposCsv(),
        'intervalo_servicio' => 19, 'intervalo_unidad' => 20, 'fecha_ultimo_servicio' => 21, 'dias_plazo_vencimiento' => 22,
    ];

    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-A,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,12,meses,15/01/2026,',
        'SN-2,EQ-B,Digital,M1,Fluke,Balanza,ACME,900111222,Planta,B1,Bueno,,,,,,,,,2,Semanas,2026-01-01,10',
        'SN-3,EQ-SIN,Digital,M1,Fluke,Balanza,ACME,900111222,Planta,B1,Bueno,,,,,,,,,,,,',
    ), $mapeo);

    expect($importacion->filas_creadas)->toBe(3);

    $tipoA = EquipoProgramacion::whereRelation('equipo', 'codigo', 'EQ-A')->sole();
    $tipoB = EquipoProgramacion::whereRelation('equipo', 'codigo', 'EQ-B')->sole();

    expect($tipoA->tipo_servicio)->toBe('MANTENIMIENTO,CALIBRACION')
        ->and($tipoA->intervalo_unidad)->toBe('MESES')
        ->and($tipoA->fecha_ultimo_servicio->toDateString())->toBe('2026-01-15')
        ->and($tipoA->fecha_proximo_servicio->toDateString())->toBe('2027-01-15')
        ->and((int) $tipoA->dias_plazo_vencimiento)->toBe(30)
        ->and($tipoB->tipo_servicio)->toBe('MANTENIMIENTO')
        ->and($tipoB->fecha_proximo_servicio->toDateString())->toBe('2026-01-15')
        ->and((int) $tipoB->dias_plazo_vencimiento)->toBe(10)
        ->and(EquipoProgramacion::whereRelation('equipo', 'codigo', 'EQ-SIN')->exists())->toBeFalse();
});

test('marca el tipo de servicio segun las columnas mantenimiento y calibracion y usa la fecha del proximo servicio del csv', function () {
    $mapeo = [
        ...mapeoEquiposCsv(),
        'servicio_mantenimiento' => 19, 'servicio_calibracion' => 20, 'intervalo_servicio' => 21,
        'intervalo_unidad' => 22, 'fecha_ultimo_servicio' => 23, 'fecha_proximo_servicio' => 24,
    ];

    // Manómetro se crea como tipo A (mantenimiento y calibración), pero mandan las columnas.
    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-SOLO-CAL,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,No,Sí,12,MESES,2026-01-15,2026-12-01',
        'SN-2,EQ-SOLO-MANT,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,Sí,,6,MESES,,2026-09-30',
        'SN-3,EQ-AMBOS,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,si,si,12,MESES,2026-01-15,',
    ), $mapeo);

    $programacion = fn (string $codigo) => EquipoProgramacion::whereRelation('equipo', 'codigo', $codigo)->sole();

    expect($importacion->filas_creadas)->toBe(3)
        ->and($programacion('EQ-SOLO-CAL')->tipo_servicio)->toBe('CALIBRACION')
        ->and($programacion('EQ-SOLO-CAL')->fecha_proximo_servicio->toDateString())->toBe('2026-12-01')
        ->and($programacion('EQ-SOLO-MANT')->tipo_servicio)->toBe('MANTENIMIENTO')
        ->and($programacion('EQ-SOLO-MANT')->fecha_ultimo_servicio)->toBeNull()
        ->and($programacion('EQ-SOLO-MANT')->fecha_proximo_servicio->toDateString())->toBe('2026-09-30')
        ->and($programacion('EQ-AMBOS')->tipo_servicio)->toBe('MANTENIMIENTO,CALIBRACION')
        ->and($programacion('EQ-AMBOS')->fecha_proximo_servicio->toDateString())->toBe('2027-01-15');
});

test('reporta un tipo de servicio sin marcar o un proximo servicio anterior al ultimo', function () {
    $mapeo = [
        ...mapeoEquiposCsv(),
        'servicio_mantenimiento' => 19, 'servicio_calibracion' => 20, 'intervalo_servicio' => 21,
        'intervalo_unidad' => 22, 'fecha_ultimo_servicio' => 23, 'fecha_proximo_servicio' => 24,
    ];

    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,No,No,12,MESES,2026-01-15,',
        'SN-2,EQ-2,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,Sí,,12,MESES,2026-01-15,2025-12-01',
        'SN-3,EQ-3,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,Sí,,,,,',
    ), $mapeo);

    expect($importacion->filas_creadas)->toBe(0)
        ->and($importacion->errores[0]['mensaje'])->toBe('Marca "Sí" en Mantenimiento, en Calibración o en las dos.')
        ->and($importacion->errores[1]['mensaje'])->toContain('Fecha del próximo servicio')
        ->and($importacion->errores[2]['mensaje'])->toContain('Intervalo de servicio');
});

test('acepta las fechas como suele exportarlas excel', function (string $fecha) {
    $mapeo = [...mapeoEquiposCsv(), 'intervalo_servicio' => 19, 'intervalo_unidad' => 20, 'fecha_ultimo_servicio' => 21];

    $importacion = importarEquipos($this->admin, csvEquipos(
        "SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,{$fecha},12,MESES,{$fecha}",
    ), $mapeo);

    expect($importacion->errores)->toBe([])
        ->and(Equipo::sole()->ficha_tecnica['fecha_adquisicion'])->toBe('2025-06-05')
        ->and(EquipoProgramacion::sole()->fecha_ultimo_servicio->toDateString())->toBe('2025-06-05');
})->with([
    'día y mes con ceros' => '05/06/2025',
    'sin ceros a la izquierda' => '5/6/2025',
    'con hora' => '5/06/2025 0:00',
    'con hora y am/pm' => '05/06/2025 12:00 a. m.',
    'año de dos dígitos' => '05/06/25',
    'con guiones' => '05-06-2025',
    'con puntos' => '05.06.2025',
    'año primero' => '2025-06-05',
    'año primero sin ceros' => '2025/6/5',
    'número de serie de excel' => '45813',
]);

test('reporta una fecha que no existe con un mensaje claro', function () {
    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,31/02/2025',
    ));

    expect($importacion->errores[0]['mensaje'])
        ->toBe('La Fecha de adquisición no es una fecha válida: usa DD/MM/AAAA (ej. 15/06/2025).');
});

test('reporta la programacion incompleta y no crea el equipo', function () {
    $mapeo = [...mapeoEquiposCsv(), 'intervalo_servicio' => 19, 'intervalo_unidad' => 20, 'fecha_ultimo_servicio' => 21];

    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,12,,',
        'SN-2,EQ-2,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,,,MESES,2026-01-01',
    ), $mapeo);

    expect($importacion->filas_creadas)->toBe(0)
        ->and($importacion->errores[0]['mensaje'])->toContain('Unidad del intervalo')
        ->and($importacion->errores[0]['mensaje'])->toContain('Fecha del último servicio')
        ->and($importacion->errores[1]['mensaje'])->toContain('Intervalo de servicio')
        ->and(Equipo::count())->toBe(0);
});

test('agrega los simbolos de precision, alcance y resolucion cuando el csv solo trae numeros', function () {
    UnidadMedida::create(['nombre' => 'Milímetro', 'simbolo' => 'mm', 'tenant_id' => $this->tenant->id]);
    $mapeo = [...mapeoEquiposCsv(), 'simbolo_precision' => 19];

    importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Longitud,Milímetro,,0 a 100,"0,5","0,01",,,±',
        'SN-2,EQ-2,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Presión,Bar,bar,0 a 10,2,0.1,,,%',
        'SN-3,EQ-3,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Presión,Bar,bar,0 a 10bar,±0.5,0.1,,,',
    ), $mapeo);

    $especificacion = fn (string $codigo) => Equipo::where('codigo', $codigo)->sole()->equipoEspecificacionTecnica;

    expect($especificacion('EQ-1')->only(['alcance_indicacion', 'precision', 'resolucion']))
        ->toBe(['alcance_indicacion' => '0 a 100mm', 'precision' => '±0.5', 'resolucion' => '0.01mm'])
        ->and($especificacion('EQ-2')->only(['alcance_indicacion', 'precision', 'resolucion']))
        ->toBe(['alcance_indicacion' => '0 a 10bar', 'precision' => '2%', 'resolucion' => '0.1bar'])
        ->and($especificacion('EQ-3')->only(['alcance_indicacion', 'precision', 'resolucion']))
        ->toBe(['alcance_indicacion' => '0 a 10bar', 'precision' => '±0.5', 'resolucion' => '0.1bar']);
});

test('acepta la resolucion con coma o punto decimal y hasta 4 decimales', function (string $resolucion, string $esperada) {
    $importacion = importarEquipos($this->admin, csvEquipos(
        "SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Longitud,Milímetro,mm,0 a 25,±0.5,\"{$resolucion}\",,",
    ));

    expect($importacion->errores)->toBe([])
        ->and(Equipo::sole()->equipoEspecificacionTecnica->resolucion)->toBe($esperada);
})->with([
    'punto' => ['0.0005', '0.0005mm'],
    'coma' => ['0,0005', '0.0005mm'],
    'entero' => ['1', '1mm'],
    'sin cero inicial con coma' => [',5', '0.5mm'],
    'sin cero inicial con punto' => ['.25', '0.25mm'],
    'notación científica de excel' => ['1E-04', '0.0001mm'],
    'con espacios' => [' 0,01 ', '0.01mm'],
]);

test('reporta la especificacion tecnica con un formato que el formulario no aceptaria', function () {
    $mapeo = [...mapeoEquiposCsv(), 'simbolo_precision' => 19];

    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Presión,Bar,bar,0 a 10,0.5,0.1,,,',
        'SN-2,EQ-2,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Presión,Bar,bar,0 a 10,0.5,0.1 aprox,,,±',
        // La resolución lleva solo el número: con el símbolo escrito ya no se acepta.
        'SN-3,EQ-3,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Presión,Bar,bar,0 a 10,0.5,0.1bar,,,±',
        // Mismo límite de 4 decimales que el formulario de equipos.
        'SN-4,EQ-4,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,Presión,Bar,bar,0 a 10,0.5,0.00001,,,±',
    ), $mapeo);

    $mensajeResolucion = 'La resolución debe ser solo el número, con máximo 4 decimales (ej. 0.0001): el símbolo de la unidad (bar) se agrega solo.';

    expect($importacion->filas_creadas)->toBe(0)
        ->and($importacion->errores[0]['mensaje'])->toBe('La precisión debe ser un número con ± adelante o % al final (ej. ±0.5 o 0.5%): indica el símbolo en la columna Símbolo de la precisión.')
        ->and(array_column(array_slice($importacion->errores, 1), 'mensaje'))->toBe([$mensajeResolucion, $mensajeResolucion, $mensajeResolucion]);
});

test('no reutiliza los catalogos de otro tenant', function () {
    $otroTenant = Tenant::factory()->create();
    $areaAjena = Area::create(['nombre' => 'Planta Norte', 'tenant_id' => $otroTenant->id]);

    importarEquipos($this->admin, csvEquipos('SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta Norte,B1,Bueno,,,,,,,,'));

    $equipo = Equipo::sole();

    expect($equipo->area_id)->not->toBe($areaAjena->id)
        ->and($equipo->area->tenant_id)->toBe($this->tenant->id);
});

test('reporta las filas con error con su numero y sigue con las demas', function () {
    Equipo::create([
        'codigo' => 'EQ-EXISTE',
        'tipo_equipo_id' => crearTipoEquipo($this->tenant)->id,
        'tipo_tecnologia' => 'DIGITAL',
        'modelo' => 'M',
        'fabricante_id' => Fabricante::create(['nombre' => 'F', 'tenant_id' => $this->tenant->id])->id,
        'numero_serie' => 'S',
        'area_id' => $area = Area::create(['nombre' => 'A', 'tenant_id' => $this->tenant->id])->id,
        'bahia_id' => Bahia::create(['nombre' => 'B', 'area_id' => $area, 'tenant_id' => $this->tenant->id])->id,
        'condicion_actual' => 'Bueno',
        'cliente_id' => Cliente::create(['nombre' => 'C', 'email' => 'c@c.com', 'tenant_id' => $this->tenant->id])->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $importacion = importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-OK,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,',
        'SN-2,EQ-EXISTE,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,',
        'SN-3,EQ-3,Láser,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,',
        '',
        'SN-5,EQ-5,Digital,,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,',
    ));

    expect($importacion->estado)->toBe(Importacion::ESTADO_COMPLETADA)
        ->and($importacion->total_filas)->toBe(4)
        ->and($importacion->filas_creadas)->toBe(1)
        ->and($importacion->filas_con_error)->toBe(3)
        ->and(array_column($importacion->errores, 'fila'))->toBe([3, 4, 6])
        ->and($importacion->errores[0]['mensaje'])->toBe('Ya existe un equipo con el código EQ-EXISTE.')
        ->and($importacion->errores[1]['mensaje'])->toContain('Tipo de indicación')
        ->and($importacion->errores[2]['mensaje'])->toContain('Modelo')
        ->and(Equipo::where('codigo', 'EQ-OK')->exists())->toBeTrue();
});

test('importa un archivo grande en varias tandas y acumula el avance', function () {
    $total = ProcesarImportacion::FILAS_POR_TANDA * 2 + 5;
    $filas = array_map(
        fn (int $numero): string => "SN-{$numero},EQ-{$numero},Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,",
        range(1, $total),
    );
    $filas[] = 'SN-X,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,';

    $importacion = importarEquipos($this->admin, csvEquipos(...$filas));

    expect($importacion->estado)->toBe(Importacion::ESTADO_COMPLETADA)
        ->and($importacion->total_filas)->toBe($total + 1)
        ->and($importacion->filas_procesadas)->toBe($total + 1)
        ->and($importacion->filas_creadas)->toBe($total)
        ->and($importacion->errores)->toBe([['fila' => $total + 2, 'mensaje' => 'Ya existe un equipo con el código EQ-1.']])
        ->and(Equipo::count())->toBe($total);

    Storage::disk('local')->assertMissing($importacion->archivo);
});

test('exige relacionar los campos obligatorios', function () {
    $mapeo = mapeoEquiposCsv();
    unset($mapeo['codigo']);

    $this->actingAs($this->admin)
        ->postJson(route('importaciones.store'), [
            'tipo' => 'equipos',
            'archivo' => str_repeat('a', 40).'.csv',
            'nombre_original' => 'equipos.csv',
            'mapeo' => $mapeo,
        ])
        ->assertJsonValidationErrors(['mapeo.codigo' => 'Elige la columna del CSV para Código.']);

    expect(Importacion::count())->toBe(0);
});

test('rechaza un archivo que no fue subido o es de otro tenant', function () {
    $otroTenant = Tenant::factory()->create();
    $archivo = str_repeat('a', 40).'.csv';
    Storage::disk('local')->put("importaciones/{$otroTenant->id}/{$archivo}", ENCABEZADOS_CSV."\nSN-1");

    $this->actingAs($this->admin)
        ->postJson(route('importaciones.store'), [
            'tipo' => 'equipos',
            'archivo' => $archivo,
            'nombre_original' => 'equipos.csv',
            'mapeo' => mapeoEquiposCsv(),
        ])
        ->assertJsonValidationErrors(['archivo' => 'El archivo ya no está disponible, vuelve a subirlo.']);
});

test('solo quien inicio la importacion puede ver su avance', function () {
    $importacion = importarEquipos($this->admin, csvEquipos('SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,'));
    $otroUsuario = User::factory()->forTenant($this->tenant)->create();

    $this->actingAs($otroUsuario)->getJson(route('importaciones.show', $importacion))->assertForbidden();

    $this->actingAs($this->admin)
        ->getJson(route('importaciones.show', $importacion))
        ->assertOk()
        ->assertJsonPath('estado', Importacion::ESTADO_COMPLETADA)
        ->assertJsonPath('filas_creadas', 1);
});

test('el historial muestra las ultimas importaciones del usuario con sus errores', function () {
    importarEquipos($this->admin, csvEquipos(
        'SN-1,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,',
        'SN-2,EQ-1,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,',
    ));
    importarEquipos($this->admin, csvEquipos('SN-3,EQ-3,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,'));
    importarEquipos(User::factory()->forTenant($this->tenant)->create(), csvEquipos(
        'SN-4,EQ-4,Digital,M1,Fluke,Manómetro,ACME,900111222,Planta,B1,Bueno,,,,,,,,',
    ));

    $this->actingAs($this->admin)
        ->getJson(route('importaciones.historial'))
        ->assertOk()
        ->assertJsonCount(2, 'importaciones')
        ->assertJsonPath('importaciones.0.filas_creadas', 1)
        ->assertJsonPath('importaciones.0.filas_con_error', 0)
        ->assertJsonPath('importaciones.1.filas_creadas', 1)
        ->assertJsonPath('importaciones.1.errores.0', ['fila' => 3, 'mensaje' => 'Ya existe un equipo con el código EQ-1.']);
});

/**
 * @param  array<string, mixed>  $atributos
 */
function crearImportacion(User $usuario, array $atributos = []): Importacion
{
    return Importacion::create([
        'tipo' => 'equipos', 'archivo' => 'x.csv', 'nombre_original' => 'equipos.csv', 'delimitador' => ',',
        'codificacion' => 'UTF-8', 'mapeo' => [], 'estado' => Importacion::ESTADO_COMPLETADA,
        'user_id' => $usuario->id, 'tenant_id' => $usuario->tenant_id,
        ...$atributos,
    ]);
}

test('marca el resultado de una importacion como leido y como no leido', function () {
    $importacion = crearImportacion($this->admin);

    $this->actingAs($this->admin)
        ->patchJson(route('importaciones.leida', $importacion), ['leida' => true])
        ->assertOk()
        ->assertJsonPath('leida', true);

    expect($importacion->fresh()->leida_en)->not->toBeNull();

    $this->actingAs($this->admin)
        ->patchJson(route('importaciones.leida', $importacion), ['leida' => false])
        ->assertOk()
        ->assertJsonPath('leida', false);

    expect($importacion->fresh()->leida_en)->toBeNull();
});

test('solo quien hizo la importacion puede marcarla como leida', function () {
    $importacion = crearImportacion($this->admin);

    $this->actingAs(User::factory()->forTenant($this->tenant)->create())
        ->patchJson(route('importaciones.leida', $importacion), ['leida' => true])
        ->assertForbidden();

    expect($importacion->fresh()->leida_en)->toBeNull();
});

test('comparte cuantas importaciones terminadas no ha leido el usuario', function () {
    crearImportacion($this->admin);
    crearImportacion($this->admin, ['estado' => Importacion::ESTADO_FALLIDA]);
    crearImportacion($this->admin, ['leida_en' => now()]);
    crearImportacion($this->admin, ['estado' => Importacion::ESTADO_PROCESANDO]);
    crearImportacion(User::factory()->forTenant($this->tenant)->create());

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('importacionesSinLeer', 2));

    $this->actingAs(User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('importacionesSinLeer', null));
});

test('el historial muestra primero las importaciones en curso y luego las sin leer', function () {
    $enCurso = crearImportacion($this->admin, ['estado' => Importacion::ESTADO_PROCESANDO, 'tipo' => 'clientes']);
    $sinLeer = crearImportacion($this->admin);
    $leida = crearImportacion($this->admin, ['leida_en' => now()]);

    $this->actingAs($this->admin)
        ->getJson(route('importaciones.historial'))
        ->assertJsonPath('importaciones.0.id', $enCurso->id)
        ->assertJsonPath('importaciones.0.estado', Importacion::ESTADO_PROCESANDO)
        ->assertJsonPath('importaciones.1.id', $sinLeer->id)
        ->assertJsonPath('importaciones.1.leida', false)
        ->assertJsonPath('importaciones.2.id', $leida->id)
        ->assertJsonPath('importaciones.2.leida', true);
});

test('devuelve todas las importaciones en curso del usuario, de la mas antigua a la mas nueva', function () {
    $datos = [
        'tipo' => 'equipos', 'archivo' => 'x.csv', 'nombre_original' => 'equipos.csv', 'delimitador' => ',',
        'codificacion' => 'UTF-8', 'mapeo' => [], 'user_id' => $this->admin->id, 'tenant_id' => $this->tenant->id,
    ];
    Importacion::create([...$datos, 'estado' => Importacion::ESTADO_COMPLETADA]);

    $this->actingAs($this->admin)
        ->getJson(route('importaciones.en-curso'))
        ->assertOk()
        ->assertExactJson(['importaciones' => []]);

    $procesando = Importacion::create([...$datos, 'estado' => Importacion::ESTADO_PROCESANDO]);
    $pendiente = Importacion::create([...$datos, 'tipo' => 'clientes', 'estado' => Importacion::ESTADO_PENDIENTE]);
    Importacion::create([...$datos, 'estado' => Importacion::ESTADO_PROCESANDO, 'user_id' => User::factory()->forTenant($this->tenant)->create()->id]);

    $this->actingAs($this->admin)
        ->getJson(route('importaciones.en-curso'))
        ->assertOk()
        ->assertJsonCount(2, 'importaciones')
        ->assertJsonPath('importaciones.0.id', $procesando->id)
        ->assertJsonPath('importaciones.1.id', $pendiente->id)
        ->assertJsonPath('importaciones.1.tipo', 'clientes');
});
