<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\ForcePasswordChangeController;
use App\Http\Controllers\Plataforma\DashboardController as PlataformaDashboardController;
use App\Http\Controllers\Plataforma\TenantController;
use App\Http\Controllers\Plataforma\TenantUserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserHasActiveTenant;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('password-change', [ForcePasswordChangeController::class, 'edit'])->name('password-change.edit');
    Route::put('password-change', [ForcePasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password-change.update');
});

Route::middleware(['auth', EnsureUserHasActiveTenant::class])->group(function () {
    Route::resource('equipos', EquipoController::class)->except('create');

    Route::resource('roles', RoleController::class)->except(['create', 'show']);

    Route::resource('usuarios', UsuarioController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->only(['index', 'store', 'update']);
    Route::put('usuarios/{usuario}/password', [UsuarioController::class, 'resetPassword'])->name('usuarios.password');
});

Route::prefix('plataforma')
    ->name('plataforma.')
    ->middleware(['auth', EnsurePlatformAdmin::class])
    ->group(function () {
        Route::get('/', PlataformaDashboardController::class)->name('dashboard');

        Route::resource('tenants', TenantController::class)->except('create');

        Route::scopeBindings()->group(function () {
            Route::put('tenants/{tenant}/users/{user}', [TenantUserController::class, 'update'])->name('tenants.users.update');
            Route::put('tenants/{tenant}/users/{user}/password', [TenantUserController::class, 'resetPassword'])->name('tenants.users.password');
        });
    });

require __DIR__.'/settings.php';
