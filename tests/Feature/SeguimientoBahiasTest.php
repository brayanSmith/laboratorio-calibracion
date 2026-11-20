<?php

use App\Models\Tenant;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Ingresos\Http\Controllers\SeguimientoBahiasController;
use App\Modules\Ingresos\Mail\SeguimientoBahiasEnlace;
use App\Modules\Ingresos\Models\Ingreso;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $area = crearArea($this->tenant);
    $this->bahiaNorte = Bahia::factory()->for($area)->create(['nombre' => 'Bahía Norte', 'tenant_id' => $this->tenant->id]);
    $this->bahiaSur = Bahia::factory()->for($area)->create(['nombre' => 'Bahía Sur', 'tenant_id' => $this->tenant->id]);
    $this->otraBahia = Bahia::factory()->for($area)->create(['nombre' => 'Bahía Ajena', 'tenant_id' => $this->tenant->id]);
    $this->cliente = Cliente::factory()->create(['nombre' => 'Acme', 'email' => 'compras@acme.test', 'tenant_id' => $this->tenant->id]);
    $this->cliente->bahias()->attach([$this->bahiaNorte->id, $this->bahiaSur->id]);
});

function enlaceBahias(string $correo = 'compras@acme.test', ?int $tenantId = null): string
{
    return URL::temporarySignedRoute('seguimiento.bahias', now()->addDays(SeguimientoBahiasController::DIAS_VIGENCIA), [
        'tenant' => $tenantId ?? test()->tenant->id,
        'correo' => $correo,
    ]);
}

test('con el correo de un cliente con bahias le llega el enlace para ver sus bahias', function () {
    Mail::fake();

    $this->postJson(route('seguimiento.bahias.enlace'), ['correo' => ' Compras@Acme.test '])
        ->assertOk()
        ->assertJsonPath('mensaje', fn (string $mensaje) => str_contains($mensaje, 'compras@acme.test'));

    Mail::assertQueued(SeguimientoBahiasEnlace::class, fn (SeguimientoBahiasEnlace $correo) => $correo->hasTo('compras@acme.test')
        && $correo->tenantId === $this->tenant->id
        && $correo->bahias === ['Bahía Norte', 'Bahía Sur']
        && str_contains($correo->enlace, 'signature='));
});

test('a un correo que no es de un cliente con bahias no se le envia nada, con la misma respuesta', function (string $correo) {
    Mail::fake();
    Cliente::factory()->create(['email' => 'sinbahias@acme.test', 'tenant_id' => $this->tenant->id]);

    $this->postJson(route('seguimiento.bahias.enlace'), ['correo' => $correo])
        ->assertOk()
        ->assertJsonPath('mensaje', fn (string $mensaje) => str_contains($mensaje, $correo));

    Mail::assertNothingQueued();
})->with(['no existe' => 'nadie@acme.test', 'cliente sin bahias' => 'sinbahias@acme.test']);

test('pide un correo valido', function () {
    $this->postJson(route('seguimiento.bahias.enlace'), ['correo' => 'no-es-correo'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('correo');
});

test('limita las solicitudes del enlace para que no se use para llenar buzones', function () {
    Mail::fake();

    foreach (range(1, 5) as $intento) {
        $this->postJson(route('seguimiento.bahias.enlace'), ['correo' => 'compras@acme.test'])->assertOk();
    }

    $this->postJson(route('seguimiento.bahias.enlace'), ['correo' => 'compras@acme.test'])->assertTooManyRequests();
});

test('con el enlace se ven en una sola lista los equipos de los ingresos de sus bahias, del mas reciente al mas antiguo', function () {
    $viejo = Ingreso::factory()->create(['bahia_id' => $this->bahiaNorte->id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO', 'desde' => '2026-10-01', 'hasta' => '2026-10-05', 'fecha_recepcion' => '2026-10-02']);
    $reciente = Ingreso::factory()->create(['bahia_id' => $this->bahiaSur->id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO', 'desde' => '2026-11-10', 'hasta' => '2026-11-15', 'fecha_recepcion' => '2026-11-11']);
    $ajeno = Ingreso::factory()->create(['bahia_id' => $this->otraBahia->id, 'tenant_id' => $this->tenant->id, 'estado_ingreso' => 'RECIBIDO']);

    foreach ([['EQ-VIEJO', $viejo], ['EQ-RECIENTE', $reciente], ['EQ-AJENO', $ajeno]] as [$codigo, $ingreso]) {
        crearEquipo($this->tenant, $codigo)->equipoProgramaciones()->create([
            'tipo_servicio' => 'CALIBRACION',
            'fecha_proximo_servicio' => now()->toDateString(),
            'dias_plazo_vencimiento' => 30,
            'ingreso_id' => $ingreso->id,
            'agendar' => true,
            'estado_programacion' => 'RECIBIDO',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    $this->get(enlaceBahias())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seguimiento/bahias')
            ->where('cliente', 'Acme')
            ->has('bahias', 2)
            ->where('bahias.0.nombre', 'Bahía Norte')
            // Los de la bahía que no tiene asignada no aparecen.
            ->has('equipos', 2)
            ->where('equipos.0.codigo', 'EQ-RECIENTE')
            ->where('equipos.0.ingreso', $reciente->codigo)
            ->where('equipos.0.bahia', 'Bahía Sur')
            ->where('equipos.0.bahia_id', $this->bahiaSur->id)
            ->where('equipos.0.historial.0.fase', 'Ingreso')
            ->where('equipos.1.codigo', 'EQ-VIEJO'));
});
test('sin firma valida o con el enlace vencido no se puede entrar', function () {
    $this->get(route('seguimiento.bahias', ['tenant' => $this->tenant->id, 'correo' => 'compras@acme.test']))->assertForbidden();

    $enlace = enlaceBahias();
    $this->travel(SeguimientoBahiasController::DIAS_VIGENCIA + 1)->days();

    $this->get($enlace)->assertForbidden();
});

test('el enlace de un laboratorio no muestra las bahias del cliente en otro', function () {
    $otroTenant = Tenant::factory()->create();
    $bahiaOtra = Bahia::factory()->for(crearArea($otroTenant))->create(['tenant_id' => $otroTenant->id]);
    Cliente::factory()->create(['email' => 'compras@acme.test', 'tenant_id' => $otroTenant->id])->bahias()->attach($bahiaOtra);

    $this->get(enlaceBahias())
        ->assertInertia(fn ($page) => $page
            ->has('bahias', 2)
            ->where('bahias', fn ($bahias) => collect($bahias)->pluck('id')->doesntContain($bahiaOtra->id)));
});

test('el correo trae el boton para ver los ingresos por bahia', function () {
    $correo = new SeguimientoBahiasEnlace($this->tenant->id, 'Acme', ['Bahía Norte'], 'https://lab.test/enlace-firmado', 7);

    $correo->assertSeeInOrderInHtml(['Acme', 'Bahía Norte', 'Ver ingresos por bahía', 'vence en 7 días']);
    $correo->assertSeeInHtml('https://lab.test/enlace-firmado', false);
});
