<?php

use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Mail\IngresoRecibido;
use App\Modules\Ingresos\Models\FotoRecepcion;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

function programacionEnIngreso(Equipo $equipo, Ingreso $ingreso): EquipoProgramacion
{
    return $equipo->equipoProgramaciones()->create([
        'tipo_servicio' => 'CALIBRACION',
        'fecha_proximo_servicio' => now()->toDateString(),
        'dias_plazo_vencimiento' => 30,
        'ingreso_id' => $ingreso->id,
        'agendar' => true,
        'estado_programacion' => 'RECIBIDO',
        'tenant_id' => $equipo->tenant_id,
    ]);
}

test('sin iniciar sesion, con el codigo se ven los equipos del ingreso y en que va cada uno', function () {
    $equipo = crearEquipo($this->tenant, 'EQ-1');
    $ingreso = Ingreso::factory()->create(['bahia_id' => $equipo->bahia_id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO', 'cliente_entrega_id' => null]);
    $programacion = programacionEnIngreso($equipo, $ingreso);
    $programacion->seguimientos()->create(['fase' => 'CALIBRACION', 'seguimiento' => 'EN_PROCESO', 'tenant_id' => $this->tenant->id]);
    programacionEnIngreso(crearEquipo(Tenant::factory()->create(), 'EQ-OTRO-INGRESO'), Ingreso::factory()->create());

    // El código se acepta en minúsculas y con espacios, como lo copiaría el cliente.
    $this->get(route('seguimiento', ['codigo' => ' '.strtolower($ingreso->codigo).' ']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seguimiento/show')
            ->where('codigo', $ingreso->codigo)
            ->where('ingreso.codigo', $ingreso->codigo)
            ->where('ingreso.estado_ingreso', 'RECIBIDO')
            ->has('ingreso.equipos', 1)
            ->where('ingreso.cliente', 'Cliente EQ-1')
            ->where('ingreso.equipos.0.codigo', 'EQ-1')
            ->where('ingreso.equipos.0.tipo_servicio', 'Calibración')
            ->where('ingreso.equipos.0.historial.1.fase', 'Calibración')
            ->where('ingreso.equipos.0.historial.1.estado', 'En proceso')
            ->where('ingreso.equipos.0.siguientes', ['Despacho'])
            ->missing('ingreso.equipos.0.cliente'));
});

test('el codigo con el prefijo anterior ING- de un correo viejo sigue encontrando el ingreso', function () {
    $ingreso = Ingreso::factory()->create(['codigo' => 'IN-7K3QX9MZ', 'tenant_id' => $this->tenant->id]);

    $this->get(route('seguimiento', ['codigo' => 'ING-7K3QX9MZ']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('ingreso.codigo', $ingreso->codigo));
});

test('sin codigo o con un codigo que no existe no muestra ningun ingreso', function (string $codigo) {
    Ingreso::factory()->create();

    $this->get(route('seguimiento', ['codigo' => $codigo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('ingreso', null));
})->with(['sin codigo' => '', 'inexistente' => 'IN-NOEXISTE']);

test('limita los intentos de consulta para que no se puedan probar codigos al azar', function () {
    foreach (range(1, 20) as $intento) {
        $this->get(route('seguimiento', ['codigo' => "IN-{$intento}"]))->assertOk();
    }

    $this->get(route('seguimiento', ['codigo' => 'IN-21']))->assertTooManyRequests();
});

test('el boton del correo del ingreso lleva a la consulta con su codigo', function () {
    $correo = new IngresoRecibido($this->tenant->id, 'IN-7K3QX9MZ', 'Acme', 'Bahía 1', '2026-11-10', null, []);

    $correo->assertSeeInHtml(e(route('seguimiento', ['codigo' => 'IN-7K3QX9MZ'])), false);
});

test('cada equipo muestra cuando ingreso y el laboratorio donde se le hace el servicio', function () {
    Empresa::create(['nit' => '900', 'nombre' => 'Lab Andino', 'direccion' => 'Calle 1', 'telefono' => '1', 'laboratorio_nombre' => 'Laboratorio Propio', 'tenant_id' => $this->tenant->id]);
    $local = crearEquipo($this->tenant, 'EQ-LOCAL');
    $externo = crearEquipo($this->tenant, 'EQ-TERCERO');
    $ingreso = Ingreso::factory()->create(['bahia_id' => $local->bahia_id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO', 'cliente_entrega_id' => $local->cliente_id]);

    $this->travelTo(now()->setDateTime(2026, 11, 10, 14, 30));
    programacionEnIngreso($local, $ingreso)
        ->seguimientos()->create(['fase' => 'CALIBRACION', 'seguimiento' => 'EN_PROCESO', 'tenant_id' => $this->tenant->id]);
    $programacionExterna = programacionEnIngreso($externo, $ingreso);
    $programacionExterna->seguimientos()->create(['fase' => 'MANTENIMIENTO', 'seguimiento' => 'EN_PROCESO', 'tenant_id' => $this->tenant->id]);
    $orden = OrdenTrabajo::create([
        'codigo' => 'OT-1',
        'equipo_programacion_id' => $programacionExterna->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $this->tenant->id,
    ]);
    ServicioTercero::create([
        'orden_trabajo_id' => $orden->id,
        'tipo_servicio' => 'MANTENIMIENTO',
        'empresa_tercero_id' => EmpresaTercero::factory()->create(['nombre' => 'Metrología Externa', 'tenant_id' => $this->tenant->id])->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get(route('seguimiento', ['codigo' => $ingreso->codigo]))
        ->assertInertia(fn ($page) => $page
            ->where('ingreso.cliente', 'Cliente EQ-LOCAL')
            ->where('ingreso.fecha_ingreso', now()->toIso8601String())
            ->where('ingreso.equipos.0.codigo', 'EQ-LOCAL')
            ->where('ingreso.equipos.0.fecha_ingreso', now()->toIso8601String())
            ->where('ingreso.equipos.0.historial.0.laboratorio', null)
            ->where('ingreso.equipos.0.historial.1.laboratorio', 'Laboratorio Propio')
            ->where('ingreso.equipos.1.codigo', 'EQ-TERCERO')
            ->where('ingreso.equipos.1.historial.1.fase', 'Mantenimiento')
            ->where('ingreso.equipos.1.historial.1.laboratorio', 'Metrología Externa'));
});

test('cada equipo muestra las etapas que le faltan segun los servicios que se le programaron', function () {
    $equipo = crearEquipo($this->tenant, 'EQ-1');
    $ingreso = Ingreso::factory()->create(['bahia_id' => $equipo->bahia_id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO']);
    programacionEnIngreso($equipo, $ingreso)->update(['tipo_servicio' => 'MANTENIMIENTO,CALIBRACION']);

    $this->get(route('seguimiento', ['codigo' => $ingreso->codigo]))
        ->assertInertia(fn ($page) => $page
            ->where('ingreso.equipos.0.historial.0.fase', 'Ingreso')
            ->where('ingreso.equipos.0.siguientes', ['Mantenimiento', 'Calibración', 'Despacho']));
});

test('el pie de pagina muestra el contacto del laboratorio del ingreso consultado', function () {
    Empresa::create(['nit' => '900123', 'nombre' => 'Lab Andino', 'direccion' => 'Calle 1', 'telefono' => '601 111', 'laboratorio_email' => 'lab@andino.test', 'tenant_id' => $this->tenant->id]);
    $ingreso = Ingreso::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('seguimiento', ['codigo' => $ingreso->codigo]))
        ->assertInertia(fn ($page) => $page
            ->where('contacto.nombre', 'Lab Andino')
            ->where('contacto.nit', '900123')
            ->where('contacto.telefono', '601 111')
            ->where('contacto.email', 'lab@andino.test'));
});

test('sin codigo el pie muestra el contacto del laboratorio solo cuando hay un unico laboratorio', function (bool $multiTenant, ?string $esperado) {
    config(['app.multi_tenant' => $multiTenant]);
    Empresa::create(['nit' => '900123', 'nombre' => 'Lab Andino', 'direccion' => 'Calle 1', 'telefono' => '1', 'tenant_id' => $this->tenant->id]);

    $this->get(route('seguimiento'))
        ->assertInertia(fn ($page) => $page->where('contacto', fn ($contacto) => $contacto?->get('nombre') === $esperado));
})->with([
    'un solo laboratorio' => [false, 'Lab Andino'],
    'multi tenant' => [true, null],
]);

test('el cliente ve las fotos de como llego cada equipo', function () {
    $equipo = crearEquipo($this->tenant, 'EQ-1');
    $ingreso = Ingreso::factory()->create(['bahia_id' => $equipo->bahia_id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO']);
    $programacion = programacionEnIngreso($equipo, $ingreso);
    $foto = FotoRecepcion::factory()->create(['equipo_programacion_id' => $programacion->id, 'tenant_id' => $this->tenant->id]);

    $this->get(route('seguimiento', ['codigo' => $ingreso->codigo]))
        ->assertInertia(fn ($page) => $page->where('ingreso.equipos.0.fotos', [$foto->url()]));
});

/**
 * Un ingreso recibido con un equipo que tiene un mantenimiento y una calibración en una
 * orden de trabajo, con los estados dados.
 *
 * @return array{0: Ingreso, 1: Mantenimiento, 2: Calibracion}
 */
function ingresoConServicios(Tenant $tenant, string $estadoMantenimiento, string $estadoCalibracion, string $codigo = 'EQ-1'): array
{
    $equipo = crearEquipo($tenant, $codigo);
    $ingreso = Ingreso::factory()->create(['bahia_id' => $equipo->bahia_id, 'tenant_id' => $tenant->id, 'estado_ingreso' => 'RECIBIDO']);
    $orden = OrdenTrabajo::create([
        'codigo' => 'OT-'.$codigo,
        'equipo_programacion_id' => programacionEnIngreso($equipo, $ingreso)->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $tenant->id,
    ]);
    $tecnico = User::factory()->forTenant($tenant)->create();
    $mantenimiento = Mantenimiento::create([
        'orden_trabajo_id' => $orden->id,
        'tipo_mantenimiento' => 'PREVENTIVO',
        'fecha_mantenimiento' => '2026-11-12',
        'estado_mantenimiento' => $estadoMantenimiento,
        'tecnico_id' => $tecnico->id,
        'tenant_id' => $tenant->id,
    ]);
    $calibracion = Calibracion::create([
        'orden_trabajo_id' => $orden->id,
        'fecha_calibracion' => '2026-11-13',
        'estado_calibracion' => $estadoCalibracion,
        'tecnico_id' => $tecnico->id,
        'temperatura' => 20,
        'humedad' => 50,
        'tenant_id' => $tenant->id,
    ]);

    return [$ingreso, $mantenimiento, $calibracion];
}

test('el cliente ve el informe de mantenimiento y el certificado de calibracion de los servicios terminados', function () {
    Storage::fake('public');
    [$ingreso, $mantenimiento, $calibracion] = ingresoConServicios($this->tenant, 'FINALIZADO', 'FINALIZADO');
    $urlInforme = route('seguimiento.mantenimiento.informe', ['codigo' => $ingreso->codigo, 'mantenimiento' => $mantenimiento->id]);
    $urlCertificado = route('seguimiento.calibracion.certificado', ['codigo' => $ingreso->codigo, 'calibracion' => $calibracion->id]);

    $this->get(route('seguimiento', ['codigo' => $ingreso->codigo]))
        ->assertInertia(fn ($page) => $page
            ->has('ingreso.equipos.0.documentos', 2)
            ->where('ingreso.equipos.0.documentos.0.tipo', 'MANTENIMIENTO')
            ->where('ingreso.equipos.0.documentos.0.url', $urlInforme)
            ->where('ingreso.equipos.0.documentos.1.tipo', 'CALIBRACION')
            ->where('ingreso.equipos.0.documentos.1.url', $urlCertificado));

    $this->get($urlInforme)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename=informe-mantenimiento-OT-EQ-1.pdf');
    $this->get($urlCertificado)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('no muestra ni entrega los PDF de servicios sin terminar', function () {
    Storage::fake('public');
    [$ingreso, $mantenimiento, $calibracion] = ingresoConServicios($this->tenant, 'EN_PROCESO', 'PENDIENTE');

    $this->get(route('seguimiento', ['codigo' => $ingreso->codigo]))
        ->assertInertia(fn ($page) => $page->where('ingreso.equipos.0.documentos', []));

    $this->get(route('seguimiento.mantenimiento.informe', ['codigo' => $ingreso->codigo, 'mantenimiento' => $mantenimiento->id]))->assertNotFound();
    $this->get(route('seguimiento.calibracion.certificado', ['codigo' => $ingreso->codigo, 'calibracion' => $calibracion->id]))->assertNotFound();
});

test('con el codigo de un ingreso no se pueden abrir los PDF de equipos de otro ingreso', function () {
    Storage::fake('public');
    [$ingreso] = ingresoConServicios($this->tenant, 'FINALIZADO', 'FINALIZADO');
    [, $mantenimientoAjeno, $calibracionAjena] = ingresoConServicios(Tenant::factory()->create(), 'FINALIZADO', 'FINALIZADO', 'EQ-AJENO');

    $this->get(route('seguimiento.mantenimiento.informe', ['codigo' => $ingreso->codigo, 'mantenimiento' => $mantenimientoAjeno->id]))->assertNotFound();
    $this->get(route('seguimiento.calibracion.certificado', ['codigo' => $ingreso->codigo, 'calibracion' => $calibracionAjena->id]))->assertNotFound();
});

test('el cliente ve el documento que entrego el laboratorio externo por un servicio terminado', function () {
    Storage::fake('public');
    $equipo = crearEquipo($this->tenant, 'EQ-1');
    $ingreso = Ingreso::factory()->create(['bahia_id' => $equipo->bahia_id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO']);
    $orden = OrdenTrabajo::create([
        'codigo' => 'OT-EXT',
        'equipo_programacion_id' => programacionEnIngreso($equipo, $ingreso)->id,
        'fecha_programada_orden_trabajo' => now()->toDateString(),
        'estado' => 'EN_BAHIA',
        'tenant_id' => $this->tenant->id,
    ]);
    $tercero = EmpresaTercero::factory()->create(['nombre' => 'Metrología Externa', 'tenant_id' => $this->tenant->id]);
    $datos = ['orden_trabajo_id' => $orden->id, 'tipo_servicio' => 'CALIBRACION', 'empresa_tercero_id' => $tercero->id, 'tenant_id' => $this->tenant->id];
    $finalizado = ServicioTercero::create([...$datos, 'estado_servicio' => 'FINALIZADO', 'pdf_servicio' => 'servicios-terceros/certificado.pdf']);
    $enProceso = ServicioTercero::create([...$datos, 'estado_servicio' => 'EN_PROCESO', 'pdf_servicio' => 'servicios-terceros/otro.pdf']);
    Storage::disk('public')->put('servicios-terceros/certificado.pdf', '%PDF-1.4');
    Storage::disk('public')->put('servicios-terceros/otro.pdf', '%PDF-1.4');
    $url = fn (ServicioTercero $servicio) => route('seguimiento.servicio-tercero.documento', ['codigo' => $ingreso->codigo, 'servicio' => $servicio->id]);

    $this->get(route('seguimiento', ['codigo' => $ingreso->codigo]))
        ->assertInertia(fn ($page) => $page
            ->has('ingreso.equipos.0.documentos', 1)
            ->where('ingreso.equipos.0.documentos.0.tipo', 'CALIBRACION')
            ->where('ingreso.equipos.0.documentos.0.laboratorio', 'Metrología Externa')
            ->where('ingreso.equipos.0.documentos.0.url', $url($finalizado)));

    $this->get($url($finalizado))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename=certificado-calibracion-OT-EXT.pdf');
    $this->get($url($enProceso))->assertNotFound();
    $this->get(route('seguimiento.servicio-tercero.documento', ['codigo' => Ingreso::factory()->create()->codigo, 'servicio' => $finalizado->id]))->assertNotFound();
});

test('el correo del ingreso sale con el nombre de la empresa del laboratorio como remitente', function () {
    config(['mail.from.address' => 'app.calibraciones@gmail.com', 'mail.from.name' => 'Laravel']);
    $correo = fn () => new IngresoRecibido($this->tenant->id, 'IN-7K3QX9MZ', 'Acme', 'Bahía 1', '2026-11-10', null, []);

    $correo()->assertFrom('app.calibraciones@gmail.com', 'Laravel');

    Empresa::create(['nit' => '900', 'nombre' => 'Ferreyros', 'direccion' => 'Calle 1', 'telefono' => '1', 'tenant_id' => $this->tenant->id]);

    $correo()->assertFrom('app.calibraciones@gmail.com', 'Ferreyros');
});

test('la pestana del navegador muestra el logo y el nombre de la empresa del ingreso consultado', function () {
    Empresa::create(['nit' => '900', 'nombre' => 'Ferreyros', 'direccion' => 'Calle 1', 'telefono' => '1', 'logo' => 'logos/ferreyros.png', 'tenant_id' => $this->tenant->id]);
    $ingreso = Ingreso::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('seguimiento', ['codigo' => $ingreso->codigo]))
        ->assertOk()
        ->assertSee('<link rel="icon" href="/storage/logos/ferreyros.png"', false)
        ->assertSee('Seguimiento de equipos - Ferreyros</title>', false);
});
