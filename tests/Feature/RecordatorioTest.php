<?php

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Recordatorios\Actions\ObtenerEquiposRecordatorio;
use App\Modules\Recordatorios\Mail\RecordatorioEquipos;
use App\Modules\Recordatorios\Models\Recordatorio;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Jueves 12 de noviembre de 2026, 10:00 UTC.
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(10, 0));

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function programarServicio(Equipo $equipo, int $diasDesdeHoy, string $estado = 'PENDIENTE'): EquipoProgramacion
{
    return $equipo->equipoProgramaciones()->create([
        'tipo_servicio' => 'CALIBRACION',
        'fecha_proximo_servicio' => now()->addDays($diasDesdeHoy)->toDateString(),
        'dias_plazo_vencimiento' => 30,
        'agendar' => $estado !== 'PENDIENTE',
        'estado_programacion' => $estado,
        'tenant_id' => $equipo->tenant_id,
    ]);
}

function recordatorioActivo(Tenant $tenant, array $atributos = []): Recordatorio
{
    return Recordatorio::create([
        'activo' => true,
        'frecuencia' => 'SEMANAL',
        'dia_semana' => 4,
        'hora_envio' => '09:00',
        'dias_anticipacion' => 30,
        'tenant_id' => $tenant->id,
        ...$atributos,
    ]);
}

/**
 * @return list<string> Códigos de los equipos del correo, por estado
 */
function codigosDelCorreo(RecordatorioEquipos $correo, ?string $estado = null): array
{
    return collect($correo->equipos)
        ->when($estado, fn ($equipos) => $equipos->where('estado', $estado))
        ->pluck('codigo')
        ->sort()
        ->values()
        ->all();
}

test('muestra la configuracion por defecto y lo que se enviaria hoy', function () {
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -3);
    programarServicio(crearEquipo($this->tenant, 'EQ-2'), 10);
    programarServicio(crearEquipo(Tenant::factory()->create(), 'EQ-AJENO'), -3);

    $this->actingAs($this->admin)
        ->get(route('recordatorio.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recordatorio/show')
            ->where('recordatorio.activo', false)
            ->where('recordatorio.frecuencia', 'SEMANAL')
            ->where('recordatorio.dias_anticipacion', 30)
            ->where('resumen.vencidos', 1)
            ->where('resumen.proximos_a_vencer', 1)
            ->where('resumen.clientes', 2));
});

test('un usuario sin permiso no puede ver ni configurar el recordatorio', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('recordatorio.show'))->assertForbidden();
    $this->actingAs($tecnico)->put(route('recordatorio.update'), ['frecuencia' => 'SEMANAL'])->assertForbidden();
    $this->actingAs($tecnico)->post(route('recordatorio.prueba'))->assertForbidden();

    expect(Recordatorio::count())->toBe(0);
});

test('guarda la configuracion del tenant y descarta el dia que no corresponde a la frecuencia', function () {
    $this->actingAs($this->admin)
        ->put(route('recordatorio.update'), [
            'activo' => '1',
            'frecuencia' => 'MENSUAL',
            'dia_semana' => 3,
            'dia_mes' => 15,
            'hora_envio' => '13:30',
            'dias_anticipacion' => 45,
            'enviar_a_clientes' => 'on',
            'tenant_id' => Tenant::factory()->create()->id,
        ])
        ->assertRedirect(route('recordatorio.show'));

    $recordatorio = Recordatorio::sole();

    expect($recordatorio->tenant_id)->toBe($this->tenant->id)
        ->and($recordatorio->activo)->toBeTrue()
        ->and($recordatorio->frecuencia)->toBe('MENSUAL')
        ->and($recordatorio->dia_mes)->toBe(15)
        ->and($recordatorio->dia_semana)->toBeNull()
        ->and($recordatorio->hora_envio)->toBe('13:30')
        ->and($recordatorio->dias_anticipacion)->toBe(45)
        ->and($recordatorio->enviar_a_clientes)->toBeTrue()
        ->and($recordatorio->enviar_resumen_bahia)->toBeFalse();
});

test('valida el dia segun la frecuencia y el formato de la hora', function (array $datos, string $campo) {
    $this->actingAs($this->admin)
        ->put(route('recordatorio.update'), [
            'frecuencia' => 'SEMANAL',
            'dia_semana' => 1,
            'hora_envio' => '08:00',
            'dias_anticipacion' => 30,
            ...$datos,
        ])
        ->assertSessionHasErrors($campo);

    expect(Recordatorio::count())->toBe(0);
})->with([
    'dia del mes mayor a 28' => [['frecuencia' => 'MENSUAL', 'dia_mes' => 29], 'dia_mes'],
    'mensual sin dia del mes' => [['frecuencia' => 'MENSUAL'], 'dia_mes'],
    'dia de la semana fuera de rango' => [['dia_semana' => 8], 'dia_semana'],
    'hora invalida' => [['hora_envio' => '25:00'], 'hora_envio'],
    'correo con copia invalido' => [['correos_copia' => ['no-es-correo']], 'correos_copia.0'],
    'correo con copia repetido' => [['correos_copia' => ['jefe@lab.test', 'JEFE@lab.test']], 'correos_copia.1'],
]);

test('guarda los correos con copia sin espacios ni vacios', function () {
    $this->actingAs($this->admin)
        ->put(route('recordatorio.update'), [
            'frecuencia' => 'SEMANAL',
            'dia_semana' => 1,
            'hora_envio' => '08:00',
            'dias_anticipacion' => 30,
            'correos_copia' => [' Jefe@Lab.test ', 'gerente@lab.test', ''],
        ])
        ->assertRedirect(route('recordatorio.show'));

    expect(Recordatorio::sole()->correos_copia)->toBe(['jefe@lab.test', 'gerente@lab.test']);

    $this->actingAs($this->admin)
        ->get(route('recordatorio.show'))
        ->assertInertia(fn ($page) => $page->where('recordatorio.correos_copia', ['jefe@lab.test', 'gerente@lab.test']));
});

test('los correos con copia reciben el resumen de todos los equipos por bahia', function () {
    Mail::fake();
    recordatorioActivo($this->tenant, [
        'enviar_a_clientes' => false,
        'enviar_resumen_bahia' => false,
        'correos_copia' => ['jefe@lab.test', 'gerente@lab.test'],
    ]);

    programarServicio(crearEquipo($this->tenant, 'EQ-A'), -1);
    programarServicio(crearEquipo($this->tenant, 'EQ-B'), 5);

    $this->artisan('recordatorios:enviar')->assertSuccessful();

    Mail::assertQueuedCount(2);
    foreach (['jefe@lab.test', 'gerente@lab.test'] as $correo) {
        Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $mail) => $mail->hasTo($correo)
            && codigosDelCorreo($mail) === ['EQ-A', 'EQ-B']
            && $mail->agruparPor === 'bahia');
    }
});

test('los correos con copia no reciben nada si no hay equipos que avisar', function () {
    Mail::fake();
    recordatorioActivo($this->tenant, ['correos_copia' => ['jefe@lab.test']]);
    programarServicio(crearEquipo($this->tenant, 'EQ-AL-DIA'), 200);

    $this->artisan('recordatorios:enviar')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('cada cliente recibe solo sus equipos vencidos, proximos y agendados de equipos activos', function () {
    Mail::fake();
    recordatorioActivo($this->tenant, ['enviar_resumen_bahia' => false]);

    $vencido = crearEquipo($this->tenant, 'EQ-VENCIDO');
    programarServicio($vencido, -5);
    programarServicio(crearEquipo($this->tenant, 'EQ-PROXIMO'), 20);
    programarServicio(crearEquipo($this->tenant, 'EQ-AGENDADO'), -2, 'AGENDADO');
    programarServicio(crearEquipo($this->tenant, 'EQ-LEJANO'), 31);
    programarServicio(crearEquipo($this->tenant, 'EQ-RECIBIDO'), -5, 'RECIBIDO');
    $inactivo = crearEquipo($this->tenant, 'EQ-INACTIVO');
    programarServicio($inactivo, -5);
    $inactivo->update(['activo' => false]);

    // Otro equipo del mismo cliente que EQ-VENCIDO llega en el mismo correo.
    $otroDelCliente = crearEquipo($this->tenant, 'EQ-MISMO-CLIENTE');
    $otroDelCliente->update(['cliente_id' => $vencido->cliente_id]);
    programarServicio($otroDelCliente, 3);

    $this->artisan('recordatorios:enviar')->assertSuccessful();

    Mail::assertQueuedCount(3);
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('eq-vencido@cliente.test')
        && codigosDelCorreo($correo, 'VENCIDO') === ['EQ-VENCIDO']
        && codigosDelCorreo($correo, 'PROXIMO_A_VENCER') === ['EQ-MISMO-CLIENTE']
        && $correo->agruparPor === 'cliente');
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('eq-proximo@cliente.test')
        && codigosDelCorreo($correo) === ['EQ-PROXIMO']);
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('eq-agendado@cliente.test')
        && codigosDelCorreo($correo, 'AGENDADO') === ['EQ-AGENDADO']);
});

test('cada usuario asignado a bahias recibe el resumen de los equipos de sus bahias', function () {
    Mail::fake();
    recordatorioActivo($this->tenant, ['enviar_a_clientes' => false]);

    $equipoA = crearEquipo($this->tenant, 'EQ-A');
    programarServicio($equipoA, -1);
    $equipoB = crearEquipo($this->tenant, 'EQ-B');
    programarServicio($equipoB, 5);
    $sinPendientes = crearEquipo($this->tenant, 'EQ-AL-DIA');
    programarServicio($sinPendientes, 200);

    $deAmbas = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create(['email' => 'ambas@lab.test']);
    $deAmbas->bahias()->attach([$equipoA->bahia_id, $equipoB->bahia_id]);
    $deUna = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create(['email' => 'una@lab.test']);
    $deUna->bahias()->attach($equipoB->bahia_id);
    $sinNadaQueAvisar = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create(['email' => 'aldia@lab.test']);
    $sinNadaQueAvisar->bahias()->attach($sinPendientes->bahia_id);

    $this->artisan('recordatorios:enviar')->assertSuccessful();

    Mail::assertQueuedCount(2);
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('ambas@lab.test')
        && codigosDelCorreo($correo) === ['EQ-A', 'EQ-B']
        && $correo->agruparPor === 'bahia');
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('una@lab.test')
        && codigosDelCorreo($correo) === ['EQ-B']);
});

test('solo envia el dia elegido, desde la hora de envio y una vez por dia', function (array $atributos, bool $debeEnviar) {
    Mail::fake();
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);
    recordatorioActivo($this->tenant, $atributos);

    $this->artisan('recordatorios:enviar')->assertSuccessful();

    $debeEnviar ? Mail::assertQueuedCount(1) : Mail::assertNothingQueued();
})->with([
    'semanal el dia y despues de la hora' => [[], true],
    'semanal otro dia' => [['dia_semana' => 5], false],
    'antes de la hora' => [['hora_envio' => '10:01'], false],
    'mensual el dia del mes' => [['frecuencia' => 'MENSUAL', 'dia_semana' => null, 'dia_mes' => 12], true],
    'mensual otro dia del mes' => [['frecuencia' => 'MENSUAL', 'dia_semana' => null, 'dia_mes' => 13], false],
    'ya enviado hoy' => [['ultimo_envio_at' => '2026-11-12 09:00:00'], false],
    'enviado la semana pasada' => [['ultimo_envio_at' => '2026-11-05 09:00:00'], true],
    'inactivo' => [['activo' => false], false],
]);

test('no vuelve a enviar en la siguiente pasada del mismo dia', function () {
    Mail::fake();
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);
    recordatorioActivo($this->tenant);

    $this->artisan('recordatorios:enviar');
    $this->travel(5)->minutes();
    $this->artisan('recordatorios:enviar');

    Mail::assertQueuedCount(1);
});

test('no envia a los tenants inactivos', function () {
    Mail::fake();
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);
    recordatorioActivo($this->tenant);
    $this->tenant->update(['activo' => false]);

    $this->artisan('recordatorios:enviar');

    Mail::assertNothingQueued();
});

test('envia una prueba al usuario autenticado con todos los equipos del tenant', function () {
    Mail::fake();
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);
    programarServicio(crearEquipo(Tenant::factory()->create(), 'EQ-AJENO'), -1);

    $this->actingAs($this->admin)
        ->post(route('recordatorio.prueba'))
        ->assertRedirect();

    Mail::assertSent(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo($this->admin->email)
        && codigosDelCorreo($correo) === ['EQ-1']);
});

test('la vista previa devuelve el pdf del recordatorio', function () {
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);

    $this->actingAs($this->admin)
        ->get(route('recordatorio.vista-previa'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('el correo adjunta el pdf resumen', function () {
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);
    $equipos = app(ObtenerEquiposRecordatorio::class)->handle($this->tenant->id, 30)->all();

    $correo = new RecordatorioEquipos($this->tenant->id, 'Cliente', $equipos, 'cliente', 30);

    $correo->assertSeeInHtml('Cliente');
    expect($correo->attachments())->toHaveCount(1);
});

test('envia las bahias y usuarios para asignar solo a quien puede asignar usuarios a bahias', function () {
    $equipo = crearEquipo($this->tenant, 'EQ-1');
    $soloRecordatorios = usuarioConPermisos($this->tenant, [TenantPermission::RecordatoriosVer]);

    $this->actingAs($this->admin)
        ->get(route('recordatorio.show'))
        ->assertInertia(fn ($page) => $page
            ->has('asignacion_bahias.bahias', 1)
            ->where('asignacion_bahias.bahias.0.id', $equipo->bahia_id)
            ->has('asignacion_bahias.usuarios', 2));

    $this->actingAs($soloRecordatorios)
        ->get(route('recordatorio.show'))
        ->assertInertia(fn ($page) => $page->where('asignacion_bahias', null));
});

test('al asignar bahias desde recordatorios vuelve a recordatorios', function () {
    $equipo = crearEquipo($this->tenant, 'EQ-1');

    $this->actingAs($this->admin)
        ->from(route('recordatorio.show'))
        ->put(route('bahias.asignar-usuarios'), [
            'asignaciones' => [['bahia_id' => $equipo->bahia_id, 'usuario_ids' => [$this->admin->id]]],
        ])
        ->assertRedirect(route('recordatorio.show'));

    expect($this->admin->bahias()->pluck('bahias.id')->all())->toBe([$equipo->bahia_id]);
});
