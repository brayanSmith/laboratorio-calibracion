<?php

use App\Models\Empresa;
use App\Models\Tenant;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Mail\IngresoRecibido;
use App\Modules\Ingresos\Models\Ingreso;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use App\Modules\ServiciosTerceros\Models\ServicioTercero;

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

test('sin codigo o con un codigo que no existe no muestra ningun ingreso', function (string $codigo) {
    Ingreso::factory()->create();

    $this->get(route('seguimiento', ['codigo' => $codigo]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('ingreso', null));
})->with(['sin codigo' => '', 'inexistente' => 'ING-NOEXISTE']);

test('limita los intentos de consulta para que no se puedan probar codigos al azar', function () {
    foreach (range(1, 20) as $intento) {
        $this->get(route('seguimiento', ['codigo' => "ING-{$intento}"]))->assertOk();
    }

    $this->get(route('seguimiento', ['codigo' => 'ING-21']))->assertTooManyRequests();
});

test('el boton del correo del ingreso lleva a la consulta con su codigo', function () {
    $correo = new IngresoRecibido($this->tenant->id, 'ING-7K3QX9MZ', 'Acme', 'Bahía 1', '2026-11-10', null, []);

    $correo->assertSeeInHtml(e(route('seguimiento', ['codigo' => 'ING-7K3QX9MZ'])), false);
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
