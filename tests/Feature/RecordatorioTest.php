<?php

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Recordatorios\Actions\GenerarPdfRecordatorio;
use App\Modules\Recordatorios\Actions\ObtenerEquiposRecordatorio;
use App\Modules\Recordatorios\Mail\RecordatorioEquipos;
use App\Modules\Recordatorios\Models\Recordatorio;
use App\Modules\Recordatorios\Models\RecordatorioEnvio;
use App\Modules\Recordatorios\Models\RecordatorioPlantilla;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
            ->where('resumen.programados', 1)
            ->where('resumen.clientes', 2)
            ->has('clientes_recordatorio', 2)
            ->where('clientes_recordatorio.0.nombre', 'Cliente EQ-1')
            ->where('clientes_recordatorio.0.equipos', 1)
            ->where('clientes_recordatorio.0.tiene_correo', true));
});

test('un usuario sin permiso no puede ver ni configurar el recordatorio', function () {
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();

    $this->actingAs($tecnico)->get(route('recordatorio.show'))->assertForbidden();
    $this->actingAs($tecnico)->put(route('recordatorio.update'), ['frecuencia' => 'SEMANAL'])->assertForbidden();
    $this->actingAs($tecnico)->post(route('recordatorio.prueba', 'cliente'))->assertForbidden();

    expect(Recordatorio::count())->toBe(0);
});

test('guarda la configuracion del tenant y descarta el dia que no corresponde a la frecuencia', function () {
    $this->actingAs($this->admin)
        ->put(route('recordatorio.update'), [
            'activo' => '1',
            'frecuencia' => 'MENSUAL',
            'dia_semana' => 3,
            'dia_mes' => 15,
            'mes_aviso' => 'SIGUIENTE',
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
        ->and($recordatorio->mes_aviso)->toBe('SIGUIENTE')
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
    'mensual sin mes a avisar' => [['frecuencia' => 'MENSUAL', 'dia_mes' => 25], 'mes_aviso'],
    'mes a avisar invalido' => [['frecuencia' => 'MENSUAL', 'dia_mes' => 25, 'mes_aviso' => 'ANTERIOR'], 'mes_aviso'],
    'semanal sin dias de anticipacion' => [['dias_anticipacion' => ''], 'dias_anticipacion'],
    'dia de la semana fuera de rango' => [['dia_semana' => 8], 'dia_semana'],
    'hora invalida' => [['hora_envio' => '25:00'], 'hora_envio'],
    'correo con copia invalido' => [['correos_copia' => ['no-es-correo']], 'correos_copia.0'],
    'correo con copia repetido' => [['correos_copia' => ['jefe@lab.test', 'JEFE@lab.test']], 'correos_copia.1'],
]);

test('el mensual sin dias de anticipacion conserva los guardados para cuando vuelva a semanal', function () {
    recordatorioActivo($this->tenant, ['dias_anticipacion' => 45]);

    $this->actingAs($this->admin)
        ->put(route('recordatorio.update'), [
            'frecuencia' => 'MENSUAL',
            'dia_mes' => 25,
            'mes_aviso' => 'SIGUIENTE',
            'hora_envio' => '08:00',
        ])
        ->assertSessionHasNoErrors();

    expect(Recordatorio::sole()->dias_anticipacion)->toBe(45);
});

test('el envio mensual avisa hasta el ultimo dia del mes elegido', function (string $mesAviso, array $esperados) {
    Mail::fake();
    // Hoy es 12 de noviembre: noviembre termina en 18 días y diciembre en 49.
    recordatorioActivo($this->tenant, [
        'frecuencia' => 'MENSUAL',
        'dia_semana' => null,
        'dia_mes' => 12,
        'mes_aviso' => $mesAviso,
        'dias_anticipacion' => 5,
        'enviar_resumen_bahia' => false,
    ]);

    foreach (['EQ-VENCIDO' => -10, 'EQ-30-NOV' => 18, 'EQ-1-DIC' => 19, 'EQ-31-DIC' => 49, 'EQ-1-ENE' => 50] as $codigo => $dias) {
        programarServicio(crearEquipo($this->tenant, $codigo), $dias);
    }

    $this->artisan('recordatorios:enviar')->assertSuccessful();

    $codigos = collect(Mail::queued(RecordatorioEquipos::class))
        ->flatMap(fn (RecordatorioEquipos $correo) => codigosDelCorreo($correo))
        ->sort()
        ->values()
        ->all();

    expect($codigos)->toBe($esperados);
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->diasAnticipacion === ($mesAviso === 'ACTUAL' ? 18 : 49));
})->with([
    'mes actual' => ['ACTUAL', ['EQ-30-NOV', 'EQ-VENCIDO']],
    // Lo que vence de aquí a fin de noviembre ya lo avisó el envío de octubre.
    'mes siguiente' => ['SIGUIENTE', ['EQ-1-DIC', 'EQ-31-DIC', 'EQ-VENCIDO']],
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

test('cada cliente recibe solo sus equipos vencidos y programados de equipos activos, clasificados por fecha', function () {
    Mail::fake();
    recordatorioActivo($this->tenant, ['enviar_resumen_bahia' => false]);

    $vencido = crearEquipo($this->tenant, 'EQ-VENCIDO');
    programarServicio($vencido, -5);
    programarServicio(crearEquipo($this->tenant, 'EQ-PROXIMO'), 20);
    programarServicio(crearEquipo($this->tenant, 'EQ-AGENDADO'), -2, 'AGENDADO');
    programarServicio(crearEquipo($this->tenant, 'EQ-AGENDADO-FUTURO'), 10, 'AGENDADO');
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

    Mail::assertQueuedCount(4);
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('eq-vencido@cliente.test')
        && codigosDelCorreo($correo, 'VENCIDO') === ['EQ-VENCIDO']
        && codigosDelCorreo($correo, 'PROGRAMADO') === ['EQ-MISMO-CLIENTE']
        && $correo->agruparPor === 'cliente');
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('eq-proximo@cliente.test')
        && codigosDelCorreo($correo) === ['EQ-PROXIMO']);
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('eq-agendado@cliente.test')
        && codigosDelCorreo($correo, 'VENCIDO') === ['EQ-AGENDADO']);
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('eq-agendado-futuro@cliente.test')
        && codigosDelCorreo($correo, 'PROGRAMADO') === ['EQ-AGENDADO-FUTURO']);
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

test('envia al usuario autenticado la prueba del tipo de correo elegido con todos los equipos del tenant', function (string $tipo, string $asunto) {
    Mail::fake();
    recordatorioActivo($this->tenant, ['asunto_cliente' => 'Asunto clientes', 'asunto_bahia' => 'Asunto bahias']);
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);
    programarServicio(crearEquipo(Tenant::factory()->create(), 'EQ-AJENO'), -1);

    $this->actingAs($this->admin)
        ->post(route('recordatorio.prueba', $tipo))
        ->assertRedirect();

    Mail::assertSentCount(1);
    Mail::assertSent(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo($this->admin->email)
        && $correo->agruparPor === $tipo
        && $correo->plantillaAsunto === $asunto
        && codigosDelCorreo($correo) === ['EQ-1']);
})->with([
    'a clientes' => ['cliente', 'Asunto clientes'],
    'resumen por bahia' => ['bahia', 'Asunto bahias'],
]);

test('la prueba y la vista previa solo aceptan los tipos de correo existentes', function () {
    $this->actingAs($this->admin)->post('/recordatorio/prueba/otro')->assertNotFound();
    $this->actingAs($this->admin)->get('/recordatorio/vista-previa/otro')->assertNotFound();
});

test('guarda la plantilla predeterminada limpia y deja en null lo que queda igual al texto por defecto', function () {
    $porDefecto = Recordatorio::PLANTILLAS_POR_DEFECTO['bahia'];

    $this->actingAs($this->admin)
        ->put(route('recordatorio.plantillas.predeterminada', 'cliente'), [
            'asunto' => 'Tus equipos, {nombre}',
            'mensaje' => '<p style="text-align: center; color: red" onclick="robar()">Hola <strong>{nombre}</strong></p><script>alert(1)</script><p>{tabla}</p><img src="javascript:alert(1)"><img src="https://lab.test/storage/logo.png" onerror="robar()">',
        ])
        ->assertRedirect();
    $this->actingAs($this->admin)
        ->put(route('recordatorio.plantillas.predeterminada', 'bahia'), [
            'asunto' => $porDefecto['asunto'],
            // El editor escribe <br> en vez de <br />: sigue siendo el texto por defecto.
            'mensaje' => str_replace('<br />', '<br>', $porDefecto['mensaje']),
        ])
        ->assertRedirect();

    $recordatorio = Recordatorio::sole();

    expect($recordatorio->asunto_cliente)->toBe('Tus equipos, {nombre}')
        ->and($recordatorio->mensaje_cliente)->toBe('<p style="text-align: center">Hola <strong>{nombre}</strong></p><p>{tabla}</p><img /><img src="https://lab.test/storage/logo.png" />')
        ->and($recordatorio->asunto_bahia)->toBeNull()
        ->and($recordatorio->mensaje_bahia)->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('recordatorio.show'))
        ->assertInertia(fn ($page) => $page
            ->where('plantillas.cliente.en_uso', null)
            ->where('plantillas.cliente.predeterminada.asunto', 'Tus equipos, {nombre}')
            ->where('plantillas.bahia.predeterminada.mensaje', $porDefecto['mensaje']));
});

test('valida el largo de los textos de la plantilla predeterminada', function () {
    $this->actingAs($this->admin)
        ->put(route('recordatorio.plantillas.predeterminada', 'cliente'), [
            'asunto' => str_repeat('a', 151),
            'mensaje' => str_repeat('a', 20001),
        ])
        ->assertSessionHasErrors(['asunto', 'mensaje']);
});

test('un mensaje sin contenido visible se guarda como el de por defecto', function () {
    $this->actingAs($this->admin)
        ->put(route('recordatorio.plantillas.predeterminada', 'cliente'), [
            'asunto' => 'Asunto',
            'mensaje' => '<p></p><p>&nbsp;</p>',
        ])
        ->assertRedirect();

    expect(Recordatorio::sole()->mensaje_cliente)->toBeNull();
});

test('guardar la configuracion no cambia los textos del correo', function () {
    recordatorioActivo($this->tenant, ['asunto_cliente' => 'Asunto propio', 'mensaje_cliente' => '<p>Propio</p>']);

    $this->actingAs($this->admin)
        ->put(route('recordatorio.update'), [
            'frecuencia' => 'SEMANAL',
            'dia_semana' => 2,
            'hora_envio' => '08:00',
            'dias_anticipacion' => 30,
        ])
        ->assertSessionHasNoErrors();

    $recordatorio = Recordatorio::sole();

    expect($recordatorio->dia_semana)->toBe(2)
        ->and($recordatorio->asunto_cliente)->toBe('Asunto propio')
        ->and($recordatorio->mensaje_cliente)->toBe('<p>Propio</p>');
});

test('crea una plantilla con los textos de la predeterminada y no repite nombres en el mismo correo', function () {
    recordatorioActivo($this->tenant, ['asunto_bahia' => 'Asunto bahías']);

    $this->actingAs($this->admin)
        ->post(route('recordatorio.plantillas.store'), ['tipo' => 'bahia', 'nombre' => '  Navidad '])
        ->assertSessionHasNoErrors();

    $plantilla = RecordatorioPlantilla::sole();

    expect($plantilla->tenant_id)->toBe($this->tenant->id)
        ->and($plantilla->tipo)->toBe('bahia')
        ->and($plantilla->nombre)->toBe('Navidad')
        ->and($plantilla->asunto)->toBe('Asunto bahías')
        ->and($plantilla->mensaje)->toBe(Recordatorio::PLANTILLAS_POR_DEFECTO['bahia']['mensaje']);

    $this->actingAs($this->admin)
        ->post(route('recordatorio.plantillas.store'), ['tipo' => 'bahia', 'nombre' => 'Navidad'])
        ->assertSessionHasErrors('nombre');
    $this->actingAs($this->admin)
        ->post(route('recordatorio.plantillas.store'), ['tipo' => 'cliente', 'nombre' => 'Navidad'])
        ->assertSessionHasNoErrors();
    // Otro tenant puede tener su propia "Navidad".
    RecordatorioPlantilla::factory()->bahia()->create(['nombre' => 'Otra', 'tenant_id' => Tenant::factory()->create()->id]);
    $this->actingAs($this->admin)
        ->post(route('recordatorio.plantillas.store'), ['tipo' => 'bahia', 'nombre' => 'Otra'])
        ->assertSessionHasNoErrors();

    expect(RecordatorioPlantilla::where('tenant_id', $this->tenant->id)->count())->toBe(3);
});

test('edita una plantilla con el mensaje limpio y valida sus campos', function () {
    $plantilla = RecordatorioPlantilla::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)
        ->put(route('recordatorio.plantillas.update', $plantilla), [
            'nombre' => 'Navidad',
            'asunto' => 'Felices fiestas, {nombre}',
            'mensaje' => '<p>¡Feliz Navidad!</p><script>alert(1)</script><p>{tabla}</p>',
        ])
        ->assertSessionHasNoErrors();

    expect($plantilla->fresh())
        ->nombre->toBe('Navidad')
        ->asunto->toBe('Felices fiestas, {nombre}')
        ->mensaje->toBe('<p>¡Feliz Navidad!</p><p>{tabla}</p>');

    $this->actingAs($this->admin)
        ->put(route('recordatorio.plantillas.update', $plantilla), [
            'nombre' => '',
            'asunto' => '',
            'mensaje' => '<p> </p>',
        ])
        ->assertSessionHasErrors(['nombre', 'asunto', 'mensaje']);
});

test('el envio programado usa la plantilla en uso y vuelve a la predeterminada al eliminarla', function () {
    Mail::fake();
    $recordatorio = recordatorioActivo($this->tenant, ['enviar_resumen_bahia' => false, 'asunto_cliente' => 'Predeterminada']);
    $navidad = RecordatorioPlantilla::factory()->create([
        'nombre' => 'Navidad',
        'asunto' => 'Felices fiestas',
        'mensaje' => '<p>Navidad</p>',
        'tenant_id' => $this->tenant->id,
    ]);
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);

    $this->actingAs($this->admin)
        ->put(route('recordatorio.plantillas.usar', 'cliente'), ['plantilla_id' => $navidad->id])
        ->assertSessionHasNoErrors();

    $this->artisan('recordatorios:enviar')->assertSuccessful();
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->plantillaAsunto === 'Felices fiestas'
        && $correo->plantillaMensaje === '<p>Navidad</p>');

    $this->actingAs($this->admin)->delete(route('recordatorio.plantillas.destroy', $navidad))->assertRedirect();

    expect($recordatorio->fresh()->plantilla_cliente_id)->toBeNull()
        ->and($recordatorio->fresh()->plantilla('cliente')['asunto'])->toBe('Predeterminada');
});

test('vuelve a la plantilla predeterminada y solo deja usar plantillas del mismo correo y tenant', function () {
    $recordatorio = recordatorioActivo($this->tenant);
    $deBahia = RecordatorioPlantilla::factory()->bahia()->create(['tenant_id' => $this->tenant->id]);
    $ajena = RecordatorioPlantilla::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $propia = RecordatorioPlantilla::factory()->create(['tenant_id' => $this->tenant->id]);
    $recordatorio->update(['plantilla_cliente_id' => $propia->id]);

    foreach ([$deBahia, $ajena] as $invalida) {
        $this->actingAs($this->admin)
            ->put(route('recordatorio.plantillas.usar', 'cliente'), ['plantilla_id' => $invalida->id])
            ->assertSessionHasErrors('plantilla_id');
    }

    $this->actingAs($this->admin)
        ->put(route('recordatorio.plantillas.usar', 'cliente'), ['plantilla_id' => null])
        ->assertSessionHasNoErrors();

    expect($recordatorio->fresh()->plantilla_cliente_id)->toBeNull();
});

test('la variable detalle pone en el correo la tabla de equipos del pdf', function () {
    $vencido = crearEquipo($this->tenant, 'EQ-VENCIDO');
    $vencido->update(['modelo' => '<b>M1</b>']);
    programarServicio($vencido, -3);
    programarServicio(crearEquipo($this->tenant, 'EQ-PROXIMO'), 5);
    $equipos = app(ObtenerEquiposRecordatorio::class)->handle($this->tenant->id, 30)->all();

    $correo = new RecordatorioEquipos($this->tenant->id, 'ACME', $equipos, 'bahia', 30, 'Asunto', '<p>Hola</p><p>{detalle}</p><p>Gracias</p>');

    $correo->assertSeeInOrderInHtml(['Hola', 'Bahía: Bahía 1', 'Vencidos (1)', 'EQ-VENCIDO', '3 de atraso', 'Programados (1)', 'EQ-PROXIMO', 'En 5', 'Gracias']);
    $correo->assertSeeInHtml('Modelo: &lt;b&gt;M1&lt;/b&gt;', false);
    $correo->assertDontSeeInHtml('{detalle}');
});

test('la tabla de detalle del correo muestra hasta el maximo de equipos y remite el resto al pdf', function () {
    $equipos = collect(range(1, RecordatorioEquipos::MAXIMO_DETALLE + 2))->map(fn (int $numero) => [
        'programacion_id' => $numero,
        'codigo' => "EQ-{$numero}",
        'tipo_equipo' => 'Balanza',
        'modelo' => 'M',
        'numero_serie' => 'S',
        'cliente_id' => 1,
        'cliente' => 'ACME',
        'bahia_id' => 1,
        'bahia' => 'Bahía 1',
        'area' => 'Área',
        'tipo_servicio' => 'Calibración',
        'fecha_proximo_servicio' => '2026-11-20',
        'dias' => 8,
        'estado' => 'PROGRAMADO',
    ])->all();

    $correo = new RecordatorioEquipos($this->tenant->id, 'ACME', $equipos, 'cliente', 30, 'Asunto', '<p>{detalle}</p>');

    $correo->assertSeeInHtml('EQ-'.RecordatorioEquipos::MAXIMO_DETALLE.'<', false);
    $correo->assertDontSeeInHtml('EQ-'.(RecordatorioEquipos::MAXIMO_DETALLE + 1).'<', false);
    $correo->assertSeeInHtml('Y 2 equipos más en el PDF adjunto.');
});

test('el envio programado deja en el historial a quien se le envio cada correo y con que equipos', function () {
    Mail::fake();
    recordatorioActivo($this->tenant, ['correos_copia' => ['jefe@lab.test'], 'asunto_cliente' => 'Equipos de {nombre}']);
    $equipo = crearEquipo($this->tenant, 'EQ-1');
    programarServicio($equipo, -1);
    User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create(['name' => 'Ana', 'email' => 'ana@lab.test'])
        ->bahias()->attach($equipo->bahia_id);

    $this->artisan('recordatorios:enviar')->assertSuccessful();

    $envios = RecordatorioEnvio::orderBy('id')->get();

    expect($envios->pluck('tipo')->all())->toBe(['cliente', 'bahia', 'copia'])
        ->and($envios->pluck('correo')->all())->toBe(['eq-1@cliente.test', 'ana@lab.test', 'jefe@lab.test'])
        ->and($envios->every(fn (RecordatorioEnvio $envio) => $envio->estado === 'EN_COLA' && ! $envio->es_prueba))->toBeTrue()
        ->and($envios[0]->destinatario)->toBe('Cliente EQ-1')
        ->and($envios[0]->asunto)->toBe('Equipos de Cliente EQ-1')
        ->and($envios[0]->equipos)->toBe([[
            'codigo' => 'EQ-1',
            'tipo_equipo' => 'Tipo EQ-1',
            'cliente' => 'Cliente EQ-1',
            'bahia' => 'Bahía 1',
            'fecha_proximo_servicio' => '2026-11-11',
            'estado' => 'VENCIDO',
        ]]);
});

test('el historial marca el correo como enviado cuando sale y como fallido si no se pudo enviar', function () {
    recordatorioActivo($this->tenant, ['enviar_resumen_bahia' => false]);
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);

    // Cola síncrona y correo en memoria (phpunit.xml): el correo sale en el acto.
    $this->artisan('recordatorios:enviar')->assertSuccessful();

    $envio = RecordatorioEnvio::sole();

    expect($envio->estado)->toBe('ENVIADO')
        ->and($envio->enviado_at)->not->toBeNull();

    $correo = new RecordatorioEquipos($this->tenant->id, 'ACME', [], 'cliente', 30);
    $correo->envioId = $envio->id;
    $correo->failed(new RuntimeException('Connection could not be established with host smtp.gmail.com'));

    expect($envio->fresh())
        ->estado->toBe('FALLIDO')
        ->error->toBe('Connection could not be established with host smtp.gmail.com');
});

test('la prueba queda en el historial marcada como prueba', function () {
    $this->actingAs($this->admin)->post(route('recordatorio.prueba', 'bahia'))->assertRedirect();

    expect(RecordatorioEnvio::sole())
        ->tipo->toBe('bahia')
        ->es_prueba->toBeTrue()
        ->correo->toBe($this->admin->email)
        ->estado->toBe('ENVIADO');
});

test('muestra el historial de envios del tenant del mas reciente al mas antiguo', function () {
    $anterior = RecordatorioEnvio::factory()->enviado()->create(['tenant_id' => $this->tenant->id, 'destinatario' => 'Anterior']);
    $reciente = RecordatorioEnvio::factory()->create(['tenant_id' => $this->tenant->id, 'destinatario' => 'Reciente']);
    RecordatorioEnvio::factory()->create(['destinatario' => 'De otro tenant']);

    $this->actingAs($this->admin)
        ->get(route('recordatorio.show'))
        ->assertInertia(fn ($page) => $page
            ->where('envios.total', 2)
            ->where('envios.data.0.id', $reciente->id)
            ->where('envios.data.0.estado', 'EN_COLA')
            ->where('envios.data.1.id', $anterior->id)
            ->where('envios.data.1.estado', 'ENVIADO'));
});

test('no se pueden editar ni eliminar plantillas de otro tenant ni gestionarlas sin permiso', function () {
    $ajena = RecordatorioPlantilla::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $propia = RecordatorioPlantilla::factory()->create(['tenant_id' => $this->tenant->id]);
    $tecnico = User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create();
    $datos = ['nombre' => 'X', 'asunto' => 'X', 'mensaje' => '<p>X</p>'];

    $this->actingAs($this->admin)->put(route('recordatorio.plantillas.update', $ajena), $datos)->assertForbidden();
    $this->actingAs($this->admin)->delete(route('recordatorio.plantillas.destroy', $ajena))->assertForbidden();
    $this->actingAs($tecnico)->post(route('recordatorio.plantillas.store'), ['tipo' => 'cliente', 'nombre' => 'X'])->assertForbidden();
    $this->actingAs($tecnico)->put(route('recordatorio.plantillas.update', $propia), $datos)->assertForbidden();
    $this->actingAs($tecnico)->put(route('recordatorio.plantillas.usar', 'cliente'), ['plantilla_id' => $propia->id])->assertForbidden();
    $this->actingAs($tecnico)->put(route('recordatorio.plantillas.predeterminada', 'cliente'), ['asunto' => 'X'])->assertForbidden();

    expect(RecordatorioPlantilla::count())->toBe(2)
        ->and($ajena->fresh()->nombre)->not->toBe('X');
});

test('el correo usa el texto personalizado con las variables reemplazadas y escapadas', function () {
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);
    programarServicio(crearEquipo($this->tenant, 'EQ-2'), 5);
    $equipos = app(ObtenerEquiposRecordatorio::class)->handle($this->tenant->id, 30)->all();

    $correo = new RecordatorioEquipos(
        $this->tenant->id,
        'ACME <b>S.A.</b>',
        $equipos,
        'cliente',
        30,
        '{vencidos} vencidos y {programados} programados ({proximos}) para {nombre}',
        '<p>Estimado <strong>{nombre}</strong>:<br>Revisar en {dias} días.</p><p>{tabla}</p><p><img src="https://lab.test/storage/firma.png"></p><p>Gracias</p><script>alert(1)</script>',
    );

    $correo->assertHasSubject('1 vencidos y 1 programados (1) para ACME <b>S.A.</b>');
    // El correo agrega estilos en línea a cada etiqueta, por eso se revisan por partes.
    $correo->assertSeeInHtml('ACME &lt;b&gt;S.A.&lt;/b&gt;</strong>:<br>Revisar en 30 días.', false);
    $correo->assertSeeInHtml('max-width: 100%; height: auto;" src="https://lab.test/storage/firma.png"', false);
    $correo->assertSeeInOrderInHtml(['Revisar en 30 días.', 'Programados', 'Gracias']);
    $correo->assertDontSeeInHtml('{tabla}');
    $correo->assertDontSeeInHtml('<script', false);
});

test('el encabezado del correo muestra el logo de la empresa o su nombre, no el de laravel', function (bool $conLogo) {
    Storage::fake('public');
    Storage::disk('public')->put('logos/logo.png', UploadedFile::fake()->image('logo.png')->getContent());
    Empresa::create([
        'nit' => '900123456-7',
        'nombre' => 'Lab Norte',
        'direccion' => 'Calle 1 # 2-3',
        'telefono' => '3001234567',
        'logo' => $conLogo ? 'logos/logo.png' : null,
        'tenant_id' => $this->tenant->id,
    ]);

    $correo = new RecordatorioEquipos($this->tenant->id, 'ACME', [], 'cliente', 30);

    $correo->assertDontSeeInHtml('laravel.com', false);
    $correo->assertSeeInHtml('© '.date('Y').' Lab Norte.', false);
    // Incrustado en el correo: al previsualizarlo, Laravel lo deja como data URI.
    $conLogo
        ? $correo->assertSeeInHtml('<img src="data:image/png;base64,', false)
        : $correo->assertDontSeeInHtml('<img', false);
})->with(['con logo' => true, 'sin logo' => false]);

test('el correo convierte en parrafos los mensajes guardados como texto plano', function () {
    $correo = new RecordatorioEquipos($this->tenant->id, 'ACME', [], 'cliente', 30, null, "Hola {nombre}\nlinea dos\n\nGracias");

    $correo->assertSeeInHtml('Hola ACME<br>linea dos', false);
    $correo->assertSeeInOrderInHtml(['Hola ACME', 'Gracias']);
});

test('sube imagenes para el mensaje y devuelve su url publica', function () {
    Storage::fake('public');

    $url = $this->actingAs($this->admin)
        ->post(route('recordatorio.imagenes'), ['imagen' => UploadedFile::fake()->image('firma.png')])
        ->assertCreated()
        ->json('url');

    $archivos = Storage::disk('public')->files("recordatorios/{$this->tenant->id}");

    expect($archivos)->toHaveCount(1)
        ->and($url)->toBe(Storage::disk('public')->url($archivos[0]));
});

test('solo acepta imagenes y requiere permiso para editar el recordatorio', function () {
    Storage::fake('public');
    $soloVer = usuarioConPermisos($this->tenant, [TenantPermission::RecordatoriosVer]);

    $this->actingAs($this->admin)
        ->postJson(route('recordatorio.imagenes'), ['imagen' => UploadedFile::fake()->create('virus.php', 10, 'text/plain')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('imagen');

    $this->actingAs($soloVer)
        ->post(route('recordatorio.imagenes'), ['imagen' => UploadedFile::fake()->image('firma.png')])
        ->assertForbidden();

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('cada tipo de correo del envio programado usa su texto', function () {
    Mail::fake();
    recordatorioActivo($this->tenant, [
        'correos_copia' => ['jefe@lab.test'],
        'asunto_cliente' => 'Para clientes',
        'mensaje_cliente' => 'Mensaje clientes',
        'asunto_bahia' => 'Para bahias',
    ]);
    $equipo = crearEquipo($this->tenant, 'EQ-1');
    programarServicio($equipo, -1);
    User::factory()->forTenant($this->tenant, TenantRole::Tecnico)->create(['email' => 'tecnico@lab.test'])
        ->bahias()->attach($equipo->bahia_id);

    $this->artisan('recordatorios:enviar')->assertSuccessful();

    Mail::assertQueuedCount(3);
    Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo('eq-1@cliente.test')
        && $correo->plantillaAsunto === 'Para clientes'
        && $correo->plantillaMensaje === 'Mensaje clientes');
    foreach (['tecnico@lab.test', 'jefe@lab.test'] as $correoBahia) {
        Mail::assertQueued(RecordatorioEquipos::class, fn (RecordatorioEquipos $correo) => $correo->hasTo($correoBahia)
            && $correo->plantillaAsunto === 'Para bahias'
            && $correo->plantillaMensaje === Recordatorio::PLANTILLAS_POR_DEFECTO['bahia']['mensaje']);
    }
});

test('la vista previa devuelve el pdf del tipo de correo elegido', function (string $tipo) {
    programarServicio(crearEquipo($this->tenant, 'EQ-1'), -1);

    $this->actingAs($this->admin)
        ->get(route('recordatorio.vista-previa', $tipo))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', "inline; filename=\"vista-previa-recordatorio-{$tipo}.pdf\"");
})->with(['cliente', 'bahia']);

test('la vista previa de un cliente muestra el pdf que le llegaria, solo con sus equipos', function () {
    $delCliente = crearEquipo($this->tenant, 'EQ-1');
    programarServicio($delCliente, -1);
    programarServicio(crearEquipo($this->tenant, 'EQ-OTRO-CLIENTE'), -1);

    $this->mock(GenerarPdfRecordatorio::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(fn (int $tenantId, string $destinatario, array $equipos, string $agruparPor) => $destinatario === 'Cliente EQ-1'
            && collect($equipos)->pluck('codigo')->all() === ['EQ-1']
            && $agruparPor === 'cliente')
        ->andReturn('%PDF-prueba');

    $this->actingAs($this->admin)
        ->get(route('recordatorio.vista-previa', ['tipo' => 'cliente', 'cliente' => $delCliente->cliente_id]))
        ->assertOk();
});

test('no muestra la vista previa del cliente de otro tenant', function () {
    $ajeno = crearEquipo(Tenant::factory()->create(), 'EQ-AJENO');

    $this->actingAs($this->admin)
        ->get(route('recordatorio.vista-previa', ['tipo' => 'cliente', 'cliente' => $ajeno->cliente_id]))
        ->assertNotFound();
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
