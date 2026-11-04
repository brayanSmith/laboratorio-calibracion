<?php

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Equipos\Models\DocumentoEquipo;
use App\Modules\Equipos\Models\Equipo;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->forTenant($this->tenant)->create();
});

function documentoDe(Equipo $equipo, string $nombre): DocumentoEquipo
{
    return $equipo->equipoDocumentos()->create([
        'nombre' => $nombre,
        'archivo' => "documentos/{$equipo->tenant_id}/{$equipo->id}/".str($nombre)->slug().'.pdf',
        'tenant_id' => $equipo->tenant_id,
    ]);
}

test('busca la documentacion por su nombre o por el codigo y el modelo de su equipo', function () {
    $equipo = crearEquipo($this->tenant, 'EQ-MANOMETRO');
    $otro = crearEquipo($this->tenant, 'EQ-BALANZA');
    $otro->update(['modelo' => 'Balanza XP']);

    documentoDe($equipo, 'Manual de usuario');
    documentoDe($otro, 'Certificado de calibración');
    documentoDe(crearEquipo(Tenant::factory()->create(), 'EQ-AJENO'), 'Manual del ajeno');

    $this->actingAs($this->admin)
        ->getJson(route('documentos.buscar', ['q' => 'manual']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.nombre', 'Manual de usuario')
        ->assertJsonPath('0.equipo_codigo', 'EQ-MANOMETRO');

    $this->actingAs($this->admin)
        ->getJson(route('documentos.buscar', ['q' => 'balanza xp']))
        ->assertJsonCount(1)
        ->assertJsonPath('0.nombre', 'Certificado de calibración');

    $this->actingAs($this->admin)
        ->getJson(route('documentos.buscar', ['q' => 'EQ-MANO']))
        ->assertJsonCount(1)
        ->assertJsonPath('0.equipo_modelo', 'Modelo X');
});

test('los comodines del texto buscado se toman literalmente', function () {
    documentoDe(crearEquipo($this->tenant, 'EQ-1'), 'Manual');

    $this->actingAs($this->admin)
        ->getJson(route('documentos.buscar', ['q' => '%%']))
        ->assertOk()
        ->assertJsonCount(0);
});

test('exige al menos dos letras y permiso para ver equipos', function () {
    $this->actingAs($this->admin)
        ->getJson(route('documentos.buscar', ['q' => 'm']))
        ->assertUnprocessable();

    $sinPermiso = usuarioConPermisos($this->tenant, [TenantPermission::DashboardVer]);

    $this->actingAs($sinPermiso)
        ->getJson(route('documentos.buscar', ['q' => 'manual']))
        ->assertForbidden();
});
