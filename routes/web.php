<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\BahiaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoEquipoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EmpresaTerceroController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EquipoEspecificacionTecnicaController;
use App\Http\Controllers\EquipoProgramacionController;
use App\Http\Controllers\FabricanteController;
use App\Http\Controllers\ForcePasswordChangeController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LaboratorioController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\OrdenTrabajoController;
use App\Http\Controllers\Plataforma\DashboardController as PlataformaDashboardController;
use App\Http\Controllers\Plataforma\TenantController;
use App\Http\Controllers\Plataforma\TenantUserController;
use App\Http\Controllers\ProcedimientoCalibracionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TipoEquipoCheckListController;
use App\Http\Controllers\TipoEquipoController;
use App\Http\Controllers\TipoMagnitudController;
use App\Http\Controllers\UnidadMedidaController;
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
    Route::singleton('empresa', EmpresaController::class)
        ->creatable()
        ->destroyable()
        ->except(['create', 'edit']);

    Route::resource('equipos', EquipoController::class)->except(['create', 'edit']);

    Route::post('equipos/{equipo}/especificacion-tecnica', [EquipoEspecificacionTecnicaController::class, 'store'])->name('equipos.especificacion-tecnica.store');
    Route::get('equipo-programaciones/buscar', [EquipoProgramacionController::class, 'buscar'])->name('equipo-programaciones.buscar');
    Route::get('equipo-programaciones/equipos-disponibles', [EquipoProgramacionController::class, 'equiposDisponibles'])->name('equipo-programaciones.equipos-disponibles');
    Route::post('equipos/{equipo}/programaciones', [EquipoProgramacionController::class, 'store'])->name('equipos.programaciones.store');
    Route::delete('programaciones/{equipoProgramacion}', [EquipoProgramacionController::class, 'destroy'])->name('programaciones.destroy');
    Route::post('equipos/{equipo}/documentos', [DocumentoEquipoController::class, 'store'])->name('equipos.documentos.store');
    Route::delete('documentos/{documentoEquipo}', [DocumentoEquipoController::class, 'destroy'])->name('documentos.destroy');

    Route::resource('ingresos', IngresoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('ingresos/{ingreso}/estado', [IngresoController::class, 'actualizarEstado'])->name('ingresos.estado.update');

    Route::get('orden-trabajos/equipos-listos', [OrdenTrabajoController::class, 'equiposListos'])->name('orden-trabajos.equipos-listos');
    Route::post('orden-trabajos', [OrdenTrabajoController::class, 'store'])->name('orden-trabajos.store');

    // Sin "create": un mantenimiento solo se origina desde "Agendar Mantenimiento".
    Route::resource('mantenimientos', MantenimientoController::class)->only(['index', 'update', 'destroy']);

    Route::resource('clientes', ClienteController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('areas', AreaController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('bahias', BahiaController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('fabricantes', FabricanteController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('items', ItemController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('laboratorios', LaboratorioController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('empresas-terceras', EmpresaTerceroController::class)
        ->parameters(['empresas-terceras' => 'empresaTercero'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('tipos-equipo', TipoEquipoController::class)
        ->parameters(['tipos-equipo' => 'tipoEquipo'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('tipos-equipo/{tipoEquipo}/checklist', [TipoEquipoCheckListController::class, 'store'])->name('tipos-equipo.checklist.store');
    Route::put('checklist/{tipoEquipoCheckList}', [TipoEquipoCheckListController::class, 'update'])->name('checklist.update');
    Route::delete('checklist/{tipoEquipoCheckList}', [TipoEquipoCheckListController::class, 'destroy'])->name('checklist.destroy');

    Route::resource('procedimientos-calibracion', ProcedimientoCalibracionController::class)
        ->parameters(['procedimientos-calibracion' => 'procedimientoCalibracion'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('tipos-magnitud', TipoMagnitudController::class)
        ->parameters(['tipos-magnitud' => 'tipoMagnitud'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('unidades-medida', UnidadMedidaController::class)
        ->parameters(['unidades-medida' => 'unidadMedida'])
        ->only(['index', 'store', 'update', 'destroy']);

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
