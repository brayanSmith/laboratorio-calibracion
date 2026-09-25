<?php

use App\Models\Area;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('el tenant se crea con la factory y activo es booleano', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->activo)->toBeTrue()
        ->and($tenant->slug)->not->toBeEmpty();
});

test('el scope activo excluye tenants inactivos', function () {
    $activo = Tenant::factory()->create();
    $inactivo = Tenant::factory()->inactivo()->create();

    $ids = Tenant::activo()->pluck('id');

    expect($ids)->toContain($activo->id)
        ->and($ids)->not->toContain($inactivo->id);
});

test('el tenant expone sus usuarios y areas', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $area = Area::create(['nombre' => 'Metrologia', 'tenant_id' => $tenant->id]);

    expect($tenant->users->pluck('id')->all())->toBe([$user->id])
        ->and($tenant->areas->pluck('id')->all())->toBe([$area->id])
        ->and($user->tenant->is($tenant))->toBeTrue();
});

test('todas las relaciones hasMany del tenant se pueden consultar', function () {
    $tenant = Tenant::factory()->create();

    $relaciones = collect((new ReflectionClass(Tenant::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method) => $method->class === Tenant::class
            && (string) $method->getReturnType() === HasMany::class)
        ->map(fn (ReflectionMethod $method) => $method->getName());

    expect($relaciones)->toHaveCount(35);

    foreach ($relaciones as $relacion) {
        expect($tenant->{$relacion}()->count())->toBeInt();
    }
});
