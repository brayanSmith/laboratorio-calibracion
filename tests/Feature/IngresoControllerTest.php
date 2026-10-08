<?php

use App\Enums\TenantRole;
use App\Models\Bahia;
use App\Models\Cliente;
use App\Models\EquipoProgramacion;
use App\Models\Ingreso;
use App\Models\Novedad;
use App\Models\OrdenTrabajo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
    $this->bahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    $this->cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);
});

function datosIngreso(array $overrides = []): array
{
    return [
        'bahia_id' => test()->bahia->id,
        'desde' => '2026-09-01',
        'hasta' => '2026-09-05',
        ...$overrides,
    ];
}

test('lista solo los ingresos del tenant', function () {
    Ingreso::factory()->create(['bahia_id' => $this->bahia->id, 'cliente_entrega_id' => $this->cliente->id]);
    Ingreso::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('ingresos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ingresos/index')
            ->has('ingresos', 1)
            ->where('ingresos.0.bahia_nombre', $this->bahia->nombre)
            ->where('ingresos.0.cliente_entrega_nombre', $this->cliente->nombre)
            ->has('bahias', 1)
            ->has('clientes', 1));
});

test('un usuario sin permiso de ver ingresos no puede acceder', function () {
    $sinRol = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($sinRol)->get(route('ingresos.index'))->assertForbidden();
});

test('un invitado es redirigido al inicio de sesion', function () {
    $this->get(route('ingresos.index'))->assertRedirect(route('login'));
});

test('recepcion puede registrar ingresos pero no editarlos ni eliminarlos', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($recepcion)
        ->post(route('ingresos.store'), datosIngreso())
        ->assertRedirect(route('ingresos.index'));
    $this->actingAs($recepcion)
        ->put(route('ingresos.update', $ingreso), datosIngreso())
        ->assertForbidden();
    $this->actingAs($recepcion)->delete(route('ingresos.destroy', $ingreso))->assertForbidden();
});

test('crea un ingreso sin tecnico, cliente ni estado, con estado pendiente por defecto', function () {
    $this->actingAs($this->admin)
        ->post(route('ingresos.store'), datosIngreso())
        ->assertRedirect(route('ingresos.index'))
        ->assertSessionHasNoErrors();

    expect(Ingreso::firstOrFail())
        ->tenant_id->toBe($this->tenant->id)
        ->tecnico_recibe_id->toBeNull()
        ->cliente_entrega_id->toBeNull()
        ->estado_ingreso->toBe('PENDIENTE');
});

test('rechaza un ingreso sin datos obligatorios', function () {
    $this->actingAs($this->admin)
        ->post(route('ingresos.store'), [])
        ->assertSessionHasErrors(['bahia_id', 'desde', 'hasta']);
});

test('rechaza una fecha hasta anterior a la fecha desde', function () {
    $this->actingAs($this->admin)
        ->post(route('ingresos.store'), datosIngreso(['desde' => '2026-09-10', 'hasta' => '2026-09-01']))
        ->assertSessionHasErrors('hasta');
});

test('rechaza una bahia de otro tenant al crear un ingreso', function () {
    $otro = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('ingresos.store'), datosIngreso([
            'bahia_id' => Bahia::factory()->for(crearArea($otro))->create()->id,
        ]))
        ->assertSessionHasErrors('bahia_id');

    expect(Ingreso::count())->toBe(0);
});

test('no permite actualizar ni eliminar ingresos de otro tenant', function () {
    $ajeno = Ingreso::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('ingresos.update', $ajeno), datosIngreso())
        ->assertForbidden();
    $this->actingAs($this->admin)->delete(route('ingresos.destroy', $ajeno))->assertForbidden();

    expect(Ingreso::find($ajeno->id))->not->toBeNull();
});

test('elimina un ingreso con borrado suave y borra su firma', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)->patch(route('ingresos.estado.update', $ingreso), [
        'estado_ingreso' => 'RECIBIDO',
        'tecnico_recibe_id' => $this->admin->id,
        'cliente_entrega_id' => $this->cliente->id,
        'firma_cliente_entrega' => UploadedFile::fake()->image('a.png'),
    ]);
    $firma = $ingreso->fresh()->firma_cliente_entrega;

    $this->actingAs($this->admin)
        ->delete(route('ingresos.destroy', $ingreso))
        ->assertRedirect(route('ingresos.index'));

    expect(Ingreso::find($ingreso->id))->toBeNull()
        ->and(Ingreso::withTrashed()->find($ingreso->id))->not->toBeNull();
    Storage::disk('public')->assertMissing($firma);
});

test('al eliminar un ingreso, libera sus programaciones preventivas para una nueva busqueda', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $novedad = Novedad::create(['nombre' => 'Otro', 'categoria' => 'INGRESO', 'tenant_id' => $this->tenant->id]);
    $programacion = crearProgramacion($equipo, [
        'fecha_proximo_servicio' => '2026-09-03',
        'estado_programacion' => 'CANCELADO',
        'novedad_ingreso_id' => $novedad->id,
        'observacion_no_ingreso' => 'El cliente no trajo el equipo',
        're_agendar' => true,
        'datos_re_agendamiento' => ['fecha_proximo_agendamiento' => '2026-11-01'],
    ]);
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $programacion->update(['ingreso_id' => $ingreso->id]);

    $this->actingAs($this->admin)
        ->delete(route('ingresos.destroy', $ingreso))
        ->assertRedirect(route('ingresos.index'));

    expect($programacion->fresh())
        ->ingreso_id->toBeNull()
        ->estado_programacion->toBe('PENDIENTE')
        ->novedad_ingreso_id->toBeNull()
        ->observacion_no_ingreso->toBeNull()
        ->re_agendar->toBeFalse()
        ->datos_re_agendamiento->toBeNull();

    $this->actingAs($this->admin)
        ->getJson(route('equipo-programaciones.buscar', [
            'desde' => '2026-09-01',
            'hasta' => '2026-09-05',
            'bahia_id' => $this->bahia->id,
        ]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $programacion->id);
});

test('al eliminar un ingreso, elimina sus equipos correctivos agendados', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $correctivo = $equipo->equipoProgramaciones()->create([
        'tipo_servicio' => 'MANTENIMIENTO',
        'tipo_mantenimiento' => 'CORRECTIVO',
        'falla_detectada' => 'El equipo no enciende',
        'estado_programacion' => 'AGENDADO',
        'ingreso_id' => $ingreso->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('ingresos.destroy', $ingreso))
        ->assertRedirect(route('ingresos.index'));

    expect(EquipoProgramacion::find($correctivo->id))->toBeNull()
        ->and(EquipoProgramacion::withTrashed()->find($correctivo->id))->not->toBeNull();
});

test('agenda un equipo correctivo al crear el ingreso y lo enlaza con su ingreso_id', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia, 'EQ-5001', 'A');

    $this->actingAs($this->admin)
        ->post(route('ingresos.store'), [
            'bahia_id' => $this->bahia->id,
            'desde' => '2026-09-01',
            'hasta' => '2026-09-05',
            'programaciones_correctivas' => [
                ['equipo_id' => $equipo->id, 'falla_detectada' => 'El equipo no enciende'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $ingreso = Ingreso::firstOrFail();
    $programacion = EquipoProgramacion::where('equipo_id', $equipo->id)->firstOrFail();

    expect($programacion)
        ->ingreso_id->toBe($ingreso->id)
        ->tipo_mantenimiento->toBe('CORRECTIVO')
        ->tipo_servicio->toBe('MANTENIMIENTO,CALIBRACION')
        ->falla_detectada->toBe('El equipo no enciende')
        ->estado_programacion->toBe('AGENDADO');
});

test('no enlaza una programacion marcada con agendar=false al crear el ingreso', function () {
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $programacion = crearProgramacion($equipo);

    $this->actingAs($this->admin)
        ->post(route('ingresos.store'), [
            'bahia_id' => $this->bahia->id,
            'desde' => '2026-09-01',
            'hasta' => '2026-09-05',
            'programaciones_actualizadas' => [
                $programacion->id => ['agendar' => '0'],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($programacion->fresh())
        ->ingreso_id->toBeNull()
        ->estado_programacion->toBe('PENDIENTE')
        ->agendar->toBeFalse();
});

test('agenda y enlaza una programacion marcada con agendar=true al editar el ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $programacion = crearProgramacion($equipo);

    $this->actingAs($this->admin)
        ->put(route('ingresos.update', $ingreso), datosIngreso([
            'programaciones_actualizadas' => [
                $programacion->id => ['agendar' => '1'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($programacion->fresh())
        ->ingreso_id->toBe($ingreso->id)
        ->estado_programacion->toBe('AGENDADO')
        ->agendar->toBeTrue();
});

test('un usuario sin permiso de editar equipos no puede tocar programaciones al crear un ingreso', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $equipo = crearEquipoParaBusqueda($this->bahia);

    $this->actingAs($recepcion)
        ->post(route('ingresos.store'), [
            'bahia_id' => $this->bahia->id,
            'desde' => '2026-09-01',
            'hasta' => '2026-09-05',
            'programaciones_correctivas' => [
                ['equipo_id' => $equipo->id, 'falla_detectada' => 'El equipo no enciende'],
            ],
        ])
        ->assertForbidden();

    expect(Ingreso::count())->toBe(0)
        ->and(EquipoProgramacion::where('equipo_id', $equipo->id)->count())->toBe(0);
});

test('rechaza una programacion actualizada que no pertenece al tenant', function () {
    $ajeno = crearEquipoDeTipo(crearTipoEquipo(Tenant::factory()->create()), 'EQ-9999');
    $programacionAjena = crearProgramacion($ajeno);

    $this->actingAs($this->admin)
        ->post(route('ingresos.store'), [
            'bahia_id' => $this->bahia->id,
            'desde' => '2026-09-01',
            'hasta' => '2026-09-05',
            'programaciones_actualizadas' => [
                $programacionAjena->id => ['agendar' => '1'],
            ],
        ])
        ->assertSessionHasErrors('programaciones_actualizadas.0.id');
});

test('rechaza un equipo correctivo que no pertenece a la bahia del ingreso', function () {
    $otraBahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    $equipo = crearEquipoParaBusqueda($otraBahia, 'EQ-OTRA');

    $this->actingAs($this->admin)
        ->post(route('ingresos.store'), [
            'bahia_id' => $this->bahia->id,
            'desde' => '2026-09-01',
            'hasta' => '2026-09-05',
            'programaciones_correctivas' => [
                ['equipo_id' => $equipo->id, 'falla_detectada' => 'El equipo no enciende'],
            ],
        ])
        ->assertSessionHasErrors('programaciones_correctivas.0.equipo_id');
});

test('no elimina un ingreso que tiene ordenes de trabajo asociadas', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $programacion = crearProgramacion($equipo, ['ingreso_id' => $ingreso->id]);

    DB::table('orden_trabajos')->insert([
        'codigo' => 'OT-1',
        'equipo_programacion_id' => $programacion->id,
        'fecha_programada_orden_trabajo' => '2026-09-01',
        'estado' => 'INGRESADO',
        'tenant_id' => $this->tenant->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->from(route('ingresos.index'))
        ->delete(route('ingresos.destroy', $ingreso))
        ->assertRedirect(route('ingresos.index'));

    expect(Ingreso::find($ingreso->id))->not->toBeNull()
        ->and(OrdenTrabajo::count())->toBe(1);
});

test('recibe un ingreso con tecnico, cliente y firma, quedando aprobado', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => $this->cliente->id,
            'firma_cliente_entrega' => UploadedFile::fake()->image('firma.png'),
            'novedad' => 'Equipo con golpe leve',
        ])
        ->assertRedirect();

    $ingreso->refresh();

    expect($ingreso->estado_ingreso)->toBe('RECIBIDO')
        ->and($ingreso->tecnico_recibe_id)->toBe($this->admin->id)
        ->and($ingreso->cliente_entrega_id)->toBe($this->cliente->id)
        ->and($ingreso->novedad)->toBe('Equipo con golpe leve');
    Storage::disk('public')->assertExists($ingreso->firma_cliente_entrega);
});

test('mantiene agendado un equipo programado marcado como ingresado al recibir el ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $programacion = crearProgramacion($equipo, [
        'ingreso_id' => $ingreso->id,
        'agendar' => true,
        'estado_programacion' => 'AGENDADO',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => $this->cliente->id,
            'equipos_recibidos' => [
                $programacion->id => ['ingresado' => '1'],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($programacion->fresh())
        ->ingresado->toBeTrue()
        ->estado_programacion->toBe('AGENDADO');
});

test('marca un equipo programado como no ingresado, dejandolo cancelado con su novedad', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $novedad = Novedad::create(['nombre' => 'Equipo no ubicado', 'categoria' => 'INGRESO', 'tenant_id' => $this->tenant->id]);
    $programacion = crearProgramacion($equipo, [
        'ingreso_id' => $ingreso->id,
        'agendar' => true,
        'estado_programacion' => 'AGENDADO',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => $this->cliente->id,
            'equipos_recibidos' => [
                $programacion->id => [
                    'ingresado' => '0',
                    'novedad_ingreso_id' => $novedad->id,
                    'observacion_no_ingreso' => 'El cliente no trajo el equipo',
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($programacion->fresh())
        ->ingresado->toBeFalse()
        ->estado_programacion->toBe('CANCELADO')
        ->novedad_ingreso_id->toBe($novedad->id)
        ->observacion_no_ingreso->toBe('El cliente no trajo el equipo');
});

test('rechaza un equipo recibido que no pertenece a este ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $equipo = crearEquipoParaBusqueda($this->bahia);
    $programacion = crearProgramacion($equipo);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => $this->cliente->id,
            'equipos_recibidos' => [
                $programacion->id => ['ingresado' => '1'],
            ],
        ])
        ->assertSessionHasErrors('equipos_recibidos.0.id');
});

test('agenda un equipo correctivo anotado al recibir el ingreso y lo enlaza con su ingreso_id', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $equipo = crearEquipoParaBusqueda($this->bahia, 'EQ-5001', 'A');

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => $this->cliente->id,
            'equipos_correctivos_recibidos' => [
                ['equipo_id' => $equipo->id, 'falla_detectada' => 'El equipo no enciende'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $programacion = EquipoProgramacion::where('equipo_id', $equipo->id)->firstOrFail();

    expect($programacion)
        ->ingreso_id->toBe($ingreso->id)
        ->tipo_mantenimiento->toBe('CORRECTIVO')
        ->tipo_servicio->toBe('MANTENIMIENTO,CALIBRACION')
        ->falla_detectada->toBe('El equipo no enciende')
        ->estado_programacion->toBe('AGENDADO')
        ->ingresado->toBeTrue();
});

test('rechaza un equipo correctivo de otra bahia al recibir el ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $otraBahia = Bahia::factory()->for(crearArea($this->tenant))->create();
    $equipo = crearEquipoParaBusqueda($otraBahia, 'EQ-OTRA');

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => $this->cliente->id,
            'equipos_correctivos_recibidos' => [
                ['equipo_id' => $equipo->id, 'falla_detectada' => 'El equipo no enciende'],
            ],
        ])
        ->assertSessionHasErrors('equipos_correctivos_recibidos.0.equipo_id');
});

test('un usuario sin permiso de editar equipos no puede tocar equipos al recibir un ingreso', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);
    $equipo = crearEquipoParaBusqueda($this->bahia);

    $this->actingAs($recepcion)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $recepcion->id,
            'cliente_entrega_id' => $this->cliente->id,
            'equipos_correctivos_recibidos' => [
                ['equipo_id' => $equipo->id, 'falla_detectada' => 'El equipo no enciende'],
            ],
        ])
        ->assertForbidden();

    expect(EquipoProgramacion::where('equipo_id', $equipo->id)->count())->toBe(0);
});

test('exige tecnico y cliente para recibir un ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), ['estado_ingreso' => 'RECIBIDO'])
        ->assertSessionHasErrors(['tecnico_recibe_id', 'cliente_entrega_id']);
});

test('rechaza un tecnico o cliente de otro tenant al recibir un ingreso', function () {
    $otro = Tenant::factory()->create();
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => User::factory()->forTenant($otro)->create()->id,
            'cliente_entrega_id' => Cliente::factory()->create(['tenant_id' => $otro->id])->id,
        ])
        ->assertSessionHasErrors(['tecnico_recibe_id', 'cliente_entrega_id']);
});

test('rechaza una firma que no es una imagen al recibir un ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'RECIBIDO',
            'tecnico_recibe_id' => $this->admin->id,
            'cliente_entrega_id' => $this->cliente->id,
            'firma_cliente_entrega' => UploadedFile::fake()->create('firma.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('firma_cliente_entrega');
});

test('reemplaza la firma anterior al volver a recibir un ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)->patch(route('ingresos.estado.update', $ingreso), [
        'estado_ingreso' => 'RECIBIDO',
        'tecnico_recibe_id' => $this->admin->id,
        'cliente_entrega_id' => $this->cliente->id,
        'firma_cliente_entrega' => UploadedFile::fake()->image('a.png'),
    ]);
    $firmaAnterior = $ingreso->fresh()->firma_cliente_entrega;

    $this->actingAs($this->admin)->patch(route('ingresos.estado.update', $ingreso), [
        'estado_ingreso' => 'RECIBIDO',
        'tecnico_recibe_id' => $this->admin->id,
        'cliente_entrega_id' => $this->cliente->id,
        'firma_cliente_entrega' => UploadedFile::fake()->image('b.png'),
    ]);

    $ingreso->refresh();

    expect($ingreso->firma_cliente_entrega)->not->toBe($firmaAnterior);
    Storage::disk('public')->assertMissing($firmaAnterior);
    Storage::disk('public')->assertExists($ingreso->firma_cliente_entrega);
});

test('conserva la firma al recibir sin enviar una nueva y la quita si se pide', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)->patch(route('ingresos.estado.update', $ingreso), [
        'estado_ingreso' => 'RECIBIDO',
        'tecnico_recibe_id' => $this->admin->id,
        'cliente_entrega_id' => $this->cliente->id,
        'firma_cliente_entrega' => UploadedFile::fake()->image('a.png'),
    ]);
    $firma = $ingreso->fresh()->firma_cliente_entrega;

    $this->actingAs($this->admin)->patch(route('ingresos.estado.update', $ingreso), [
        'estado_ingreso' => 'RECIBIDO',
        'tecnico_recibe_id' => $this->admin->id,
        'cliente_entrega_id' => $this->cliente->id,
    ]);

    expect($ingreso->fresh()->firma_cliente_entrega)->toBe($firma);

    $this->actingAs($this->admin)->patch(route('ingresos.estado.update', $ingreso), [
        'estado_ingreso' => 'RECIBIDO',
        'tecnico_recibe_id' => $this->admin->id,
        'cliente_entrega_id' => $this->cliente->id,
        'eliminar_firma' => true,
    ]);

    expect($ingreso->fresh()->firma_cliente_entrega)->toBeNull();
    Storage::disk('public')->assertMissing($firma);
});

test('cancela un ingreso con su motivo', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), ['estado_ingreso' => 'CANCELADO'])
        ->assertSessionHasErrors('motivo_cancelacion');

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), [
            'estado_ingreso' => 'CANCELADO',
            'motivo_cancelacion' => 'El cliente canceló el servicio',
        ])
        ->assertRedirect();

    expect($ingreso->fresh())
        ->estado_ingreso->toBe('CANCELADO')
        ->motivo_cancelacion->toBe('El cliente canceló el servicio');
});

test('vuelve a dejar pendiente un ingreso aprobado, sin exigir mas datos', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), ['estado_ingreso' => 'PENDIENTE'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($ingreso->fresh())->estado_ingreso->toBe('PENDIENTE');
});

test('rechaza un estado que no existe al actualizar el estado del ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ingreso), ['estado_ingreso' => 'OTRO'])
        ->assertSessionHasErrors('estado_ingreso');
});

test('un usuario sin permiso de editar ingresos no puede actualizar su estado', function () {
    $recepcion = User::factory()->forTenant($this->tenant, TenantRole::Recepcion)->create();
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->actingAs($recepcion)
        ->patch(route('ingresos.estado.update', $ingreso), ['estado_ingreso' => 'PENDIENTE'])
        ->assertForbidden();
});

test('no permite actualizar el estado de un ingreso de otro tenant', function () {
    $ajeno = Ingreso::factory()->create();

    $this->actingAs($this->admin)
        ->patch(route('ingresos.estado.update', $ajeno), ['estado_ingreso' => 'PENDIENTE'])
        ->assertForbidden();
});

test('un invitado no puede actualizar el estado de un ingreso', function () {
    $ingreso = Ingreso::factory()->create(['bahia_id' => $this->bahia->id]);

    $this->patch(route('ingresos.estado.update', $ingreso), ['estado_ingreso' => 'PENDIENTE'])
        ->assertRedirect(route('login'));
});
