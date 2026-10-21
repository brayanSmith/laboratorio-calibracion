<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Calibraciones\Models\Laboratorio;
use App\Modules\Calibraciones\Models\ProcedimientoCalibracion;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\Catalogos\Models\TipoEquipo;
use App\Modules\Catalogos\Models\TipoEquipoCheckList;
use App\Modules\Importaciones\Models\Importacion;
use App\Modules\Inventario\Models\Item;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

/**
 * Sube un CSV del tipo dado y lo importa con las columnas sugeridas (los encabezados del
 * CSV son las etiquetas de los campos). En las pruebas la cola es síncrona.
 *
 * @param  list<string>  $filas  la primera es la de encabezados
 */
function importarCsv(User $usuario, string $tipo, array $filas): Importacion
{
    $subido = test()->actingAs($usuario)
        ->post(route('importaciones.encabezados'), [
            'tipo' => $tipo,
            'archivo' => UploadedFile::fake()->createWithContent("{$tipo}.csv", implode("\n", $filas)),
        ])
        ->assertOk()
        ->assertJsonPath('tipo', $tipo);

    test()->actingAs($usuario)
        ->postJson(route('importaciones.store'), [
            'tipo' => $tipo,
            'archivo' => $subido->json('archivo'),
            'nombre_original' => "{$tipo}.csv",
            'mapeo' => $subido->json('sugerencias'),
        ])
        ->assertCreated();

    return Importacion::latest('id')->firstOrFail();
}

test('la plantilla de cada tipo se importa sin errores', function (string $tipo, string $modelo) {
    // Las bahías y el checklist dependen de un catálogo que ya debe existir (el de la fila de ejemplo).
    match ($tipo) {
        'bahias' => crearArea($this->tenant, 'Planta principal'),
        'checklist-mantenimiento' => crearTipoEquipo($this->tenant, 'Manómetro'),
        default => null,
    };

    $respuesta = $this->actingAs($this->admin)
        ->get(route('importaciones.plantilla', $tipo))
        ->assertOk()
        ->assertDownload("plantilla-importacion-{$tipo}.csv");

    $ruta = $respuesta->baseResponse->getFile()->getPathname();

    $subido = $this->actingAs($this->admin)
        ->post(route('importaciones.encabezados'), [
            'tipo' => $tipo,
            'archivo' => new UploadedFile($ruta, 'plantilla.csv', 'text/csv', test: true),
        ])
        ->assertOk();

    expect(array_filter($subido->json('sugerencias'), fn (?int $indice): bool => $indice === null))->toBe([]);

    $this->actingAs($this->admin)
        ->postJson(route('importaciones.store'), [
            'tipo' => $tipo,
            'archivo' => $subido->json('archivo'),
            'nombre_original' => 'plantilla.csv',
            'mapeo' => $subido->json('sugerencias'),
        ])
        ->assertCreated();

    expect(Importacion::sole())
        ->tipo->value->toBe($tipo)
        ->filas_creadas->toBe(1)
        ->errores->toBe([])
        ->and($modelo::where('tenant_id', $this->tenant->id)->count())->toBe(1);
})->with([
    'ítems' => ['items', Item::class],
    'clientes' => ['clientes', Cliente::class],
    'fabricantes' => ['fabricantes', Fabricante::class],
    'procedimientos de calibración' => ['procedimientos-calibracion', ProcedimientoCalibracion::class],
    'novedades' => ['novedades', Novedad::class],
    'laboratorios' => ['laboratorios', Laboratorio::class],
    'áreas' => ['areas', Area::class],
    'bahías' => ['bahias', Bahia::class],
    'empresas terceras' => ['empresas-terceras', EmpresaTercero::class],
    'tipos de equipo' => ['tipos-equipo', TipoEquipo::class],
    'checklist de mantenimiento' => ['checklist-mantenimiento', TipoEquipoCheckList::class],
]);

test('importa bahias solo en areas existentes y sin repetir el nombre dentro del area', function () {
    $norte = crearArea($this->tenant, 'Planta Norte');
    crearArea($this->tenant, 'Planta Sur');
    Bahia::factory()->for($norte)->create(['nombre' => 'Bahía 1']);

    $importacion = importarCsv($this->admin, 'bahias', [
        'Área,Nombre',
        'planta sur,Bahía 1',
        'Planta Norte,bahia 1',
        'Planta Este,Bahía 1',
    ]);

    expect($importacion->filas_creadas)->toBe(1)
        ->and(Bahia::where('nombre', 'Bahía 1')->count())->toBe(2)
        ->and(array_column($importacion->errores, 'mensaje'))->toBe([
            'El área Planta Norte ya tiene una bahía llamada bahia 1.',
            'No existe el área Planta Este; créala o impórtala primero.',
        ]);
});

test('importa el checklist en tipos de equipo existentes y sin repetir sus puntos', function () {
    $manometro = crearTipoEquipo($this->tenant, 'Manómetro');
    crearTipoEquipo($this->tenant, 'Termómetro');
    TipoEquipoCheckList::create(['tipo_equipo_id' => $manometro->id, 'nombre' => 'Limpieza', 'tenant_id' => $this->tenant->id]);

    $importacion = importarCsv($this->admin, 'checklist-mantenimiento', [
        'Tipo de equipo,Punto del checklist',
        'MANOMETRO,Revisar carátula',
        'Termómetro,Limpieza',
        'Manómetro,limpieza',
        'Balanza,Calibrar',
    ]);

    expect($importacion->filas_creadas)->toBe(2)
        ->and($manometro->tipoEquipoCheckList()->pluck('nombre')->sort()->values()->all())->toBe(['Limpieza', 'Revisar carátula'])
        ->and(array_column($importacion->errores, 'mensaje'))->toBe([
            'El tipo de equipo Manómetro ya tiene en su checklist: limpieza.',
            'No existe el tipo de equipo Balanza; créalo o impórtalo primero.',
        ]);
});

test('importa tipos de equipo con mantenimiento A por defecto y empresas terceras sin repetir el NIT', function () {
    $tipos = importarCsv($this->admin, 'tipos-equipo', [
        'Nombre,Tipo de mantenimiento',
        'Manómetro,',
        'Balanza,b',
        'Termómetro,C',
    ]);

    expect($tipos->filas_creadas)->toBe(2)
        ->and(TipoEquipo::where('nombre', 'Manómetro')->sole()->tipo_mantenimiento)->toBe('A')
        ->and(TipoEquipo::where('nombre', 'Balanza')->sole()->tipo_mantenimiento)->toBe('B')
        ->and($tipos->errores[0]['mensaje'])->toBe('El tipo de mantenimiento debe ser A o B.');

    $empresas = importarCsv($this->admin, 'empresas-terceras', [
        'Nombre,NIT',
        'Calibraciones SAS,900555666',
        'Otra con el mismo NIT,900555666',
    ]);

    expect($empresas->filas_creadas)->toBe(1)
        ->and($empresas->errores[0]['mensaje'])->toBe('Ya existe una empresa tercera con el NIT 900555666.');
});

test('cada tipo se importa con su propio permiso de importacion, no con el de crear', function () {
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::TiposEquipoCrear, TenantPermission::TiposEquipoChecklist]);

    $this->actingAs($usuario)->get(route('importaciones.plantilla', 'tipos-equipo'))->assertForbidden();
    $this->actingAs($usuario)->get(route('importaciones.plantilla', 'checklist-mantenimiento'))->assertForbidden();

    $usuario->givePermissionTo(TenantPermission::ImportarChecklistMantenimiento->value);

    $this->actingAs($usuario)->get(route('importaciones.plantilla', 'checklist-mantenimiento'))->assertOk();
    $this->actingAs($usuario)->get(route('importaciones.plantilla', 'tipos-equipo'))->assertForbidden();
});

test('importa ítems con el precio en formato de excel y omite los códigos repetidos', function () {
    Item::create(['codigo' => 'REP-1', 'nombre' => 'Existente', 'tenant_id' => $this->tenant->id]);

    $importacion = importarCsv($this->admin, 'items', [
        'Código;Nombre;Precio unitario',
        'REP-2;Empaque;1.250,50',
        'REP-1;Ya existe;10',
        'REP-2;Repetido en el archivo;10',
    ]);

    expect($importacion->filas_creadas)->toBe(1)
        ->and(array_column($importacion->errores, 'mensaje'))->toBe([
            'Ya existe un ítem con el código REP-1.',
            'Ya existe un ítem con el código REP-2.',
        ])
        ->and((float) Item::where('codigo', 'REP-2')->sole()->precio_unitario)->toBe(1250.5);
});

test('importa clientes aceptando el nombre del tipo de documento y codigo SAP por defecto', function () {
    $importacion = importarCsv($this->admin, 'clientes', [
        'Nombre,Tipo de documento,Número de documento,Correo electrónico',
        'Ana Pérez,Carnet de extranjería,1010,ana@correo.test',
        'ACME,,900111222,acme@correo.test',
        'Repetido,SAP,900111222,otro@correo.test',
        'Sin correo válido,DNI,2020,no-es-correo',
        'Documento raro,RUT,3030,raro@correo.test',
    ]);

    expect($importacion->filas_creadas)->toBe(2)
        ->and(Cliente::where('numero_documento', '1010')->sole()->tipo_documento->value)->toBe('CE')
        ->and(Cliente::where('numero_documento', '900111222')->sole()->tipo_documento->value)->toBe('SAP')
        ->and(array_column($importacion->errores, 'mensaje'))->toBe([
            'Ya existe un cliente con el documento 900111222.',
            'El campo Correo electrónico no es un correo válido.',
            'El tipo de documento debe ser DNI, SAP o CE.',
        ]);
});

test('importa novedades: la categoria acepta tildes y el nombre solo se repite en otra categoria', function () {
    Novedad::create(['nombre' => 'Llegó sin accesorios', 'categoria' => 'INGRESO', 'tenant_id' => $this->tenant->id]);

    $importacion = importarCsv($this->admin, 'novedades', [
        'Categoría,Nombre',
        'Calibración,Llegó sin accesorios',
        'ingreso,llego SIN accesorios',
        'Bodega,Otra',
    ]);

    expect($importacion->filas_creadas)->toBe(1)
        ->and(Novedad::where('categoria', 'CALIBRACION')->sole()->nombre)->toBe('Llegó sin accesorios')
        ->and(array_column($importacion->errores, 'mensaje'))->toBe([
            'Ya existe una novedad llamada llego SIN accesorios en la categoría INGRESO.',
            'La categoría debe ser INGRESO, MANTENIMIENTO, CALIBRACION o SALIDA.',
        ]);
});

test('omite los nombres que ya existen en el tenant sin importar mayusculas ni tildes', function () {
    Fabricante::create(['nombre' => 'Würth', 'tenant_id' => $this->tenant->id]);
    Fabricante::create(['nombre' => 'Fluke', 'tenant_id' => Tenant::factory()->create()->id]);

    $importacion = importarCsv($this->admin, 'fabricantes', [
        'Nombre',
        'WURTH',
        'Fluke',
        ' fluke ',
    ]);

    expect($importacion->filas_creadas)->toBe(1)
        ->and(Fabricante::where('tenant_id', $this->tenant->id)->pluck('nombre')->sort()->values()->all())->toBe(['Fluke', 'Würth'])
        ->and(array_column($importacion->errores, 'fila'))->toBe([2, 4]);
});

test('solo se puede importar lo que el usuario tiene permitido importar', function () {
    $usuario = usuarioConPermisos($this->tenant, [TenantPermission::ImportarFabricantes]);

    $this->actingAs($usuario)->get(route('importaciones.plantilla', 'fabricantes'))->assertOk();
    $this->actingAs($usuario)->get(route('importaciones.plantilla', 'clientes'))->assertForbidden();

    $this->actingAs($usuario)
        ->post(route('importaciones.encabezados'), [
            'tipo' => 'clientes',
            'archivo' => UploadedFile::fake()->createWithContent('clientes.csv', "Nombre\nACME"),
        ])
        ->assertForbidden();

    $this->actingAs($usuario)
        ->postJson(route('importaciones.store'), [
            'tipo' => 'clientes',
            'archivo' => str_repeat('a', 40).'.csv',
            'nombre_original' => 'clientes.csv',
            'mapeo' => ['nombre' => 0],
        ])
        ->assertForbidden();

    expect(importarCsv($usuario, 'fabricantes', ['Nombre', 'Fluke'])->filas_creadas)->toBe(1);
});

test('rechaza un tipo de importacion que no existe', function () {
    $this->actingAs($this->admin)
        ->postJson(route('importaciones.encabezados'), [
            'tipo' => 'usuarios',
            'archivo' => UploadedFile::fake()->createWithContent('usuarios.csv', "Nombre\nAna"),
        ])
        ->assertJsonValidationErrors('tipo');

    $this->actingAs($this->admin)->get(route('importaciones.plantilla', 'usuarios'))->assertNotFound();
});

test('el historial y el aviso de sin leer incluyen las importaciones de todos los tipos', function () {
    importarCsv($this->admin, 'fabricantes', ['Nombre', 'Fluke']);
    importarCsv($this->admin, 'procedimientos-calibracion', ['Nombre', 'Comparación directa']);

    $this->actingAs($this->admin)
        ->getJson(route('importaciones.historial'))
        ->assertOk()
        ->assertJsonCount(2, 'importaciones')
        ->assertJsonPath('importaciones.0.tipo_etiqueta', 'Procedimientos de calibración')
        ->assertJsonPath('importaciones.1.tipo_etiqueta', 'Fabricantes');

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('importacionesSinLeer', 2));
});
