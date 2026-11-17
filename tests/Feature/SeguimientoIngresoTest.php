<?php

use App\Models\Tenant;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Ingresos\Mail\IngresoRecibido;
use App\Modules\Ingresos\Models\Ingreso;

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
    $ingreso = Ingreso::factory()->create(['bahia_id' => $equipo->bahia_id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO']);
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
            ->where('ingreso.equipos.0.codigo', 'EQ-1')
            ->where('ingreso.equipos.0.tipo_servicio', 'Calibración')
            ->where('ingreso.equipos.0.historial.1.fase', 'Calibración')
            ->where('ingreso.equipos.0.historial.1.estado', 'En proceso')
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
