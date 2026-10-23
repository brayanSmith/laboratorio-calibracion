<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Equipos\Models\EquipoHistorico;
use App\Modules\Importaciones\Models\Importacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const LOTE_HISTORICO = 'LoteDePruebaDeAdjuntosDelHistorico000001';

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->equipo = crearEquipo($this->tenant, 'EQ-0001');
});

/**
 * Sube los adjuntos (en una sola tanda) y el CSV del histórico, y lo importa con las
 * columnas sugeridas. En las pruebas la cola es síncrona.
 *
 * @param  list<string>  $filas  la primera es la de encabezados
 * @param  list<UploadedFile>  $adjuntos
 */
function importarHistorico(User $usuario, array $filas, array $adjuntos = []): Importacion
{
    if ($adjuntos !== []) {
        test()->actingAs($usuario)
            ->post(route('importaciones.adjuntos'), ['tipo' => 'equipo-historicos', 'lote' => LOTE_HISTORICO, 'adjuntos' => $adjuntos])
            ->assertOk();
    }

    $subido = test()->actingAs($usuario)
        ->post(route('importaciones.encabezados'), [
            'tipo' => 'equipo-historicos',
            'archivo' => UploadedFile::fake()->createWithContent('historico.csv', implode("\n", $filas)),
        ])
        ->assertOk();

    test()->actingAs($usuario)
        ->postJson(route('importaciones.store'), [
            'tipo' => 'equipo-historicos',
            'archivo' => $subido->json('archivo'),
            'nombre_original' => 'historico.csv',
            'mapeo' => $subido->json('sugerencias'),
            'lote_adjuntos' => $adjuntos === [] ? null : LOTE_HISTORICO,
        ])
        ->assertCreated();

    return Importacion::latest('id')->firstOrFail();
}

const ENCABEZADOS_HISTORICO = 'Código del equipo;Tipo de servicio;Fecha del servicio;Laboratorio;Es tercero;Adjunto';

test('importa el historico de un equipo existente interpretando tipo, fecha y tercero', function () {
    $importacion = importarHistorico($this->admin, [
        ENCABEZADOS_HISTORICO,
        'eq-0001;Calibración;15/06/2024;SEIMET;Sí;',
        'EQ-0001;mantenimiento;2023-01-10;Laboratorio propio;;',
    ]);

    expect($importacion->filas_creadas)->toBe(2)
        ->and($importacion->errores)->toBe([]);

    $historicos = EquipoHistorico::orderBy('fecha_servicio')->get();

    expect($historicos->map->only(['equipo_id', 'tipo_servicio', 'nombre_laboratorio', 'es_tercero', 'tenant_id'])->all())->toBe([
        ['equipo_id' => $this->equipo->id, 'tipo_servicio' => 'MANTENIMIENTO', 'nombre_laboratorio' => 'Laboratorio propio', 'es_tercero' => false, 'tenant_id' => $this->tenant->id],
        ['equipo_id' => $this->equipo->id, 'tipo_servicio' => 'CALIBRACION', 'nombre_laboratorio' => 'SEIMET', 'es_tercero' => true, 'tenant_id' => $this->tenant->id],
    ])->and($historicos->map(fn (EquipoHistorico $historico) => $historico->fecha_servicio->toDateString())->all())->toBe(['2023-01-10', '2024-06-15']);
});

test('rechaza las filas de equipos inexistentes o de otro tenant y los datos invalidos', function () {
    crearEquipo(Tenant::factory()->create(), 'EQ-AJENO');

    $importacion = importarHistorico($this->admin, [
        ENCABEZADOS_HISTORICO,
        'EQ-9999;Calibración;15/06/2024;SEIMET;No;',
        'EQ-AJENO;Calibración;15/06/2024;SEIMET;No;',
        'EQ-0001;Verificación;15/06/2024;SEIMET;No;',
        'EQ-0001;Calibración;31/02/2024;SEIMET;No;',
        'EQ-0001;Calibración;'.now()->addDay()->format('d/m/Y').';SEIMET;No;',
        'EQ-0001;Calibración;15/06/2024;SEIMET;Tal vez;',
    ]);

    expect($importacion->filas_creadas)->toBe(0)
        ->and(array_column($importacion->errores, 'mensaje'))->toBe([
            'No existe el equipo EQ-9999; regístralo o impórtalo primero.',
            'No existe el equipo EQ-AJENO; regístralo o impórtalo primero.',
            'El tipo de servicio debe ser Mantenimiento o Calibración.',
            'La fecha del servicio no es válida: usa DD/MM/AAAA (ej. 15/06/2024).',
            'La fecha del servicio no puede ser futura.',
            'Es tercero debe ser Sí o No.',
        ]);
});

test('no duplica un servicio ya registrado ni repetido dentro del archivo', function () {
    EquipoHistorico::factory()->for($this->equipo)->create([
        'tipo_servicio' => 'CALIBRACION',
        'fecha_servicio' => '2024-06-15',
        'nombre_laboratorio' => 'SEIMET',
    ]);

    $importacion = importarHistorico($this->admin, [
        ENCABEZADOS_HISTORICO,
        'EQ-0001;Calibración;15/06/2024;seimet;Sí;',
        'EQ-0001;Mantenimiento;01/02/2024;BHU;Sí;',
        'EQ-0001;Mantenimiento;01/02/2024;BHU;Sí;',
    ]);

    expect($importacion->filas_creadas)->toBe(1)
        ->and(array_column($importacion->errores, 'fila'))->toBe([2, 4])
        ->and(EquipoHistorico::count())->toBe(2);
});

test('adjunta a cada fila el archivo de la carpeta subida con el mismo nombre', function () {
    $importacion = importarHistorico($this->admin, [
        ENCABEZADOS_HISTORICO,
        'EQ-0001;Calibración;15/06/2024;SEIMET;Sí;CERTIFICADO-001.PDF',
        'EQ-0001;Mantenimiento;10/01/2024;BHU;Sí;certificado-001.pdf',
        'EQ-0001;Mantenimiento;11/01/2024;BHU;Sí;no-esta.pdf',
    ], [
        UploadedFile::fake()->createWithContent('certificado-001.pdf', 'contenido del certificado'),
        UploadedFile::fake()->createWithContent('otro.pdf', 'otro'),
    ]);

    expect($importacion->filas_creadas)->toBe(2)
        ->and(array_column($importacion->errores, 'mensaje'))->toBe(['No se encontró el archivo no-esta.pdf en la carpeta de adjuntos.']);

    EquipoHistorico::all()->each(function (EquipoHistorico $historico): void {
        expect($historico->adjunto)->toStartWith("historicos/{$this->tenant->id}/{$this->equipo->id}/")
            ->and($historico->adjuntoNombre())->toBe('certificado-001.pdf');
        Storage::disk('public')->assertExists($historico->adjunto);
        expect(Storage::disk('public')->get($historico->adjunto))->toBe('contenido del certificado');
    });

    Storage::disk('local')->assertMissing(Importacion::carpetaDeLote($this->tenant->id, LOTE_HISTORICO));
});

test('una fila con adjunto falla si no se selecciono la carpeta de adjuntos', function () {
    $importacion = importarHistorico($this->admin, [
        ENCABEZADOS_HISTORICO,
        'EQ-0001;Calibración;15/06/2024;SEIMET;Sí;certificado-001.pdf',
    ]);

    expect($importacion->filas_creadas)->toBe(0)
        ->and($importacion->errores[0]['mensaje'])->toBe('La fila indica el adjunto certificado-001.pdf, pero no se seleccionó la carpeta de adjuntos.');
});

test('el adjunto solo se busca por su nombre dentro del lote, no por una ruta', function () {
    Storage::disk('local')->put('secreto.pdf', 'no debe copiarse');

    $importacion = importarHistorico($this->admin, [
        ENCABEZADOS_HISTORICO,
        'EQ-0001;Calibración;15/06/2024;SEIMET;Sí;../../../secreto.pdf',
    ], [UploadedFile::fake()->createWithContent('otro.pdf', 'otro')]);

    expect($importacion->filas_creadas)->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('guarda los adjuntos subidos dentro del lote aunque el nombre traiga una ruta', function () {
    $this->actingAs($this->admin)
        ->post(route('importaciones.adjuntos'), [
            'tipo' => 'equipo-historicos',
            'lote' => LOTE_HISTORICO,
            'adjuntos' => [UploadedFile::fake()->createWithContent('../../fuera.pdf', 'x')],
        ])
        ->assertOk()
        ->assertJson(['subidos' => 1]);

    expect(Storage::disk('local')->allFiles())->toBe([Importacion::carpetaDeLote($this->tenant->id, LOTE_HISTORICO).'/fuera.pdf']);
});

test('valida la subida de adjuntos', function (array $datos, string $error) {
    $this->actingAs($this->admin)
        ->postJson(route('importaciones.adjuntos'), [
            'tipo' => 'equipo-historicos',
            'lote' => LOTE_HISTORICO,
            'adjuntos' => [UploadedFile::fake()->create('certificado.pdf', 10)],
            ...$datos,
        ])
        ->assertJsonValidationErrors($error);
})->with([
    'tipo sin adjuntos' => [['tipo' => 'clientes'], 'tipo'],
    'lote invalido' => [['lote' => '../otro'], 'lote'],
    'mas de 20 archivos por tanda' => [['adjuntos' => array_map(fn (int $numero) => UploadedFile::fake()->create("c{$numero}.pdf", 1), range(1, 21))], 'adjuntos'],
    'extension no permitida' => [['adjuntos' => [UploadedFile::fake()->create('script.php', 1)]], 'adjuntos.0'],
]);

test('importar el historico y subir sus adjuntos requiere su permiso de importacion', function () {
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::ImportarEquipos]);

    $this->actingAs($usuario)
        ->post(route('importaciones.adjuntos'), [
            'tipo' => 'equipo-historicos',
            'lote' => LOTE_HISTORICO,
            'adjuntos' => [UploadedFile::fake()->create('certificado.pdf', 10)],
        ])
        ->assertForbidden();
    $this->actingAs($usuario)->get(route('importaciones.plantilla', 'equipo-historicos'))->assertForbidden();

    $usuario->givePermissionTo(TenantPermission::ImportarEquipoHistoricos->value);

    $this->actingAs($usuario)->get(route('importaciones.plantilla', 'equipo-historicos'))->assertOk();
});

test('los demas tipos de importacion no aceptan un lote de adjuntos', function () {
    $subido = $this->actingAs($this->admin)
        ->post(route('importaciones.encabezados'), [
            'tipo' => 'fabricantes',
            'archivo' => UploadedFile::fake()->createWithContent('fabricantes.csv', "Nombre\nFluke"),
        ])
        ->assertOk()
        ->assertJsonPath('adjuntos', null);

    $this->actingAs($this->admin)
        ->postJson(route('importaciones.store'), [
            'tipo' => 'fabricantes',
            'archivo' => $subido->json('archivo'),
            'nombre_original' => 'fabricantes.csv',
            'mapeo' => $subido->json('sugerencias'),
            'lote_adjuntos' => LOTE_HISTORICO,
        ])
        ->assertJsonValidationErrors('lote_adjuntos');
});
