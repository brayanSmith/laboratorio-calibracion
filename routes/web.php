<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ForcePasswordChangeController;
use App\Http\Controllers\Plataforma\AjustePlataformaController;
use App\Http\Controllers\Plataforma\DashboardController as PlataformaDashboardController;
use App\Http\Controllers\Plataforma\TenantController;
use App\Http\Controllers\Plataforma\TenantUserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserHasActiveTenant;
use App\Modules\Agenda\Http\Controllers\AgendaController;
use App\Modules\AlcancesMedicion\Http\Controllers\DetalleMedicionAlcanceController;
use App\Modules\AlcancesMedicion\Http\Controllers\MedicionAlcanceController;
use App\Modules\Calibraciones\Http\Controllers\CalibracionController;
use App\Modules\Calibraciones\Http\Controllers\LaboratorioController;
use App\Modules\Calibraciones\Http\Controllers\ProcedimientoCalibracionController;
use App\Modules\Catalogos\Http\Controllers\AreaController;
use App\Modules\Catalogos\Http\Controllers\BahiaController;
use App\Modules\Catalogos\Http\Controllers\ClienteController;
use App\Modules\Catalogos\Http\Controllers\FabricanteController;
use App\Modules\Catalogos\Http\Controllers\NovedadController;
use App\Modules\Catalogos\Http\Controllers\TipoEquipoCheckListController;
use App\Modules\Catalogos\Http\Controllers\TipoEquipoController;
use App\Modules\Catalogos\Http\Controllers\TipoMagnitudController;
use App\Modules\Catalogos\Http\Controllers\UnidadMedidaController;
use App\Modules\Despachos\Http\Controllers\DespachoController;
use App\Modules\Equipos\Http\Controllers\DocumentoEquipoController;
use App\Modules\Equipos\Http\Controllers\EquipoController;
use App\Modules\Equipos\Http\Controllers\EquipoEspecificacionTecnicaController;
use App\Modules\Equipos\Http\Controllers\EquipoProgramacionController;
use App\Modules\Importaciones\Http\Controllers\ImportacionEquipoController;
use App\Modules\Ingresos\Http\Controllers\IngresoController;
use App\Modules\Inventario\Http\Controllers\EntradaItemController;
use App\Modules\Inventario\Http\Controllers\ItemController;
use App\Modules\Mantenimientos\Http\Controllers\ComentarioMantenimientoController;
use App\Modules\Mantenimientos\Http\Controllers\GaleriaMantenimientoController;
use App\Modules\Mantenimientos\Http\Controllers\ItemMantenimientoController;
use App\Modules\Mantenimientos\Http\Controllers\MantenimientoController;
use App\Modules\Mantenimientos\Http\Controllers\MantenimientoDefectoIdentificadoController;
use App\Modules\OrdenesTrabajo\Http\Controllers\OrdenTrabajoController;
use App\Modules\ServiciosTerceros\Http\Controllers\EmpresaTerceroController;
use App\Modules\ServiciosTerceros\Http\Controllers\ServicioTerceroController;
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

    Route::get('agenda/eventos', [AgendaController::class, 'eventos'])->name('agenda.eventos');

    Route::resource('equipos', EquipoController::class)->except(['create', 'edit']);

    Route::post('equipos/{equipo}/especificacion-tecnica', [EquipoEspecificacionTecnicaController::class, 'store'])->name('equipos.especificacion-tecnica.store');
    Route::get('equipo-programaciones/buscar', [EquipoProgramacionController::class, 'buscar'])->name('equipo-programaciones.buscar');
    Route::get('equipo-programaciones/equipos-disponibles', [EquipoProgramacionController::class, 'equiposDisponibles'])->name('equipo-programaciones.equipos-disponibles');
    Route::post('equipos/{equipo}/programaciones', [EquipoProgramacionController::class, 'store'])->name('equipos.programaciones.store');
    Route::delete('programaciones/{equipoProgramacion}', [EquipoProgramacionController::class, 'destroy'])->name('programaciones.destroy');
    Route::post('equipos/{equipo}/documentos', [DocumentoEquipoController::class, 'store'])->name('equipos.documentos.store');
    Route::delete('documentos/{documentoEquipo}', [DocumentoEquipoController::class, 'destroy'])->name('documentos.destroy');

    Route::get('importaciones/equipos/plantilla', [ImportacionEquipoController::class, 'plantilla'])->name('importaciones.equipos.plantilla');
    Route::post('importaciones/equipos/encabezados', [ImportacionEquipoController::class, 'encabezados'])->name('importaciones.equipos.encabezados');
    Route::get('importaciones/equipos/en-curso', [ImportacionEquipoController::class, 'enCurso'])->name('importaciones.equipos.en-curso');
    Route::post('importaciones/equipos', [ImportacionEquipoController::class, 'store'])->name('importaciones.equipos.store');
    Route::get('importaciones/{importacion}', [ImportacionEquipoController::class, 'show'])->name('importaciones.show');

    Route::resource('ingresos', IngresoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('ingresos/{ingreso}/estado', [IngresoController::class, 'actualizarEstado'])->name('ingresos.estado.update');

    Route::get('orden-trabajos/opciones-agendar', [OrdenTrabajoController::class, 'opcionesAgendar'])->name('orden-trabajos.opciones-agendar');
    Route::get('orden-trabajos/equipos-listos', [OrdenTrabajoController::class, 'equiposListos'])->name('orden-trabajos.equipos-listos');
    Route::post('orden-trabajos', [OrdenTrabajoController::class, 'store'])->name('orden-trabajos.store');
    Route::get('orden-trabajos/equipos-listos-calibracion', [OrdenTrabajoController::class, 'equiposListosCalibracion'])->name('orden-trabajos.equipos-listos-calibracion');
    Route::post('orden-trabajos/calibraciones', [OrdenTrabajoController::class, 'storeCalibracion'])->name('orden-trabajos.store-calibracion');
    Route::get('orden-trabajos/despachos-listos', [OrdenTrabajoController::class, 'despachosListos'])->name('orden-trabajos.despachos-listos');
    Route::post('orden-trabajos/despachos', [OrdenTrabajoController::class, 'storeDespacho'])->name('orden-trabajos.store-despacho');

    // Sin "create": un mantenimiento solo se origina desde "Agendar Mantenimiento".
    Route::resource('mantenimientos', MantenimientoController::class)->only(['index', 'update', 'destroy']);

    // Sin "create": una calibración solo se origina desde "Agendar Calibraciones".
    Route::resource('calibraciones', CalibracionController::class)
        ->parameters(['calibraciones' => 'calibracion'])
        ->only(['index', 'update', 'destroy']);
    // Sin "create": un despacho solo se origina al finalizar una calibración.
    Route::resource('despachos', DespachoController::class)->only(['index', 'update', 'destroy']);

    // Sin "create": un servicio de tercero solo se origina desde "Agendar Mantenimiento"/"Agendar Calibraciones".
    Route::resource('servicio-terceros', ServicioTerceroController::class)->only(['update', 'destroy']);
    Route::post('servicio-terceros/{servicio_tercero}/iniciar', [ServicioTerceroController::class, 'iniciar'])->name('servicio-terceros.iniciar');
    Route::post('servicio-terceros/{servicio_tercero}/codigos', [ServicioTerceroController::class, 'guardarCodigos'])->name('servicio-terceros.guardar-codigos');
    Route::post('servicio-terceros/{servicio_tercero}/finalizar', [ServicioTerceroController::class, 'finalizar'])->name('servicio-terceros.finalizar');

    Route::post('mantenimientos/{mantenimiento}/iniciar', [MantenimientoController::class, 'iniciar'])->name('mantenimientos.iniciar');
    Route::post('mantenimientos/{mantenimiento}/actualizar-stock', [MantenimientoController::class, 'actualizarStock'])->name('mantenimientos.actualizar-stock');
    Route::post('mantenimientos/{mantenimiento}/finalizar', [MantenimientoController::class, 'finalizar'])->name('mantenimientos.finalizar');
    Route::get('mantenimientos/{mantenimiento}/informe', [MantenimientoController::class, 'informe'])->name('mantenimientos.informe');
    Route::post('mantenimientos/{mantenimiento}/informe/actualizar', [MantenimientoController::class, 'actualizarInforme'])->name('mantenimientos.actualizar-informe');
    Route::post('calibraciones/{calibracion}/iniciar', [CalibracionController::class, 'iniciar'])->name('calibraciones.iniciar');
    Route::post('calibraciones/{calibracion}/finalizar', [CalibracionController::class, 'finalizar'])->name('calibraciones.finalizar');
    Route::get('calibraciones/{calibracion}/certificado', [CalibracionController::class, 'certificado'])->name('calibraciones.certificado');
    Route::post('calibraciones/{calibracion}/certificado/actualizar', [CalibracionController::class, 'actualizarCertificado'])->name('calibraciones.actualizar-certificado');

    // Borrado individual de lo registrado en la gestión de un mantenimiento (ver
    // GestionarMantenimientoModal): el checklist, un nuevo defecto/ítem usado/comentario/
    // foto se guardan todos junto con el mantenimiento en MantenimientoController::update().
    Route::delete('defectos/{defectoIdentificado}', [MantenimientoDefectoIdentificadoController::class, 'destroy'])->name('defectos.destroy');
    Route::delete('item-mantenimientos/{itemMantenimiento}', [ItemMantenimientoController::class, 'destroy'])->name('item-mantenimientos.destroy');
    Route::delete('comentarios/{comentarioMantenimiento}', [ComentarioMantenimientoController::class, 'destroy'])->name('comentarios.destroy');
    Route::delete('galeria/{galeriaMantenimiento}', [GaleriaMantenimientoController::class, 'destroy'])->name('galeria.destroy');

    Route::resource('clientes', ClienteController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('areas', AreaController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('bahias', BahiaController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('fabricantes', FabricanteController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('items', ItemController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('items/{item}/movimientos', [ItemController::class, 'movimientos'])->name('items.movimientos');
    Route::post('items/{item}/entradas', [EntradaItemController::class, 'store'])->name('items.entradas.store');
    Route::patch('entradas-item/{entradaItem}/recibir', [EntradaItemController::class, 'recibir'])->name('entradas-item.recibir');

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

    Route::resource('alcances-medicion', MedicionAlcanceController::class)
        ->parameters(['alcances-medicion' => 'medicionAlcance'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('alcances-medicion/{medicionAlcance}/detalles', [DetalleMedicionAlcanceController::class, 'store'])->name('alcances-medicion.detalles.store');
    Route::put('detalles-alcance/{detalleMedicionAlcance}', [DetalleMedicionAlcanceController::class, 'update'])->name('detalles-alcance.update');
    Route::delete('detalles-alcance/{detalleMedicionAlcance}', [DetalleMedicionAlcanceController::class, 'destroy'])->name('detalles-alcance.destroy');

    Route::resource('novedades', NovedadController::class)
        ->parameters(['novedades' => 'novedad'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('tipos-magnitud', TipoMagnitudController::class)
        ->parameters(['tipos-magnitud' => 'tipoMagnitud'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('unidades-medida', UnidadMedidaController::class)
        ->parameters(['unidades-medida' => 'unidadMedida'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('roles', RoleController::class)->except(['create', 'show', 'edit']);

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

        Route::get('ajustes', [AjustePlataformaController::class, 'edit'])->name('ajustes.edit');
        Route::post('ajustes', [AjustePlataformaController::class, 'update'])->name('ajustes.update');

        Route::resource('tenants', TenantController::class)->except('create');

        Route::post('tenants/{tenant}/users', [TenantUserController::class, 'store'])->name('tenants.users.store');

        Route::scopeBindings()->group(function () {
            Route::put('tenants/{tenant}/users/{user}', [TenantUserController::class, 'update'])->name('tenants.users.update');
            Route::put('tenants/{tenant}/users/{user}/password', [TenantUserController::class, 'resetPassword'])->name('tenants.users.password');
        });
    });

require __DIR__.'/settings.php';
