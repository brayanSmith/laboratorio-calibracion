<?php

use App\Modules\Despachos\Models\Despacho;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las órdenes de equipos que solo requieren mantenimiento (tipo B) cuyo mantenimiento ya
     * terminó con el equipo operativo, pero que quedaron esperando una calibración que no
     * les corresponde: se les crea su despacho, como ahora se hace al finalizar.
     */
    public function up(): void
    {
        DB::table('orden_trabajos')
            ->join('equipo_programacions', 'equipo_programacions.id', '=', 'orden_trabajos.equipo_programacion_id')
            ->whereNull('orden_trabajos.deleted_at')
            ->where('orden_trabajos.mantenimiento_finalizado', true)
            ->where('orden_trabajos.calibracion_finalizado', false)
            ->where('orden_trabajos.listo_para_calibracion', false)
            ->where('equipo_programacions.tipo_servicio', 'not like', '%CALIBRACION%')
            ->whereExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('mantenimientos')
                ->whereColumn('mantenimientos.orden_trabajo_id', 'orden_trabajos.id')
                ->where('mantenimientos.estado_mantenimiento', 'FINALIZADO')
                ->where('mantenimientos.estado_final_equipo', 'OPERATIVO')
                ->whereNull('mantenimientos.deleted_at'))
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('despachos')
                ->whereColumn('despachos.orden_trabajo_id', 'orden_trabajos.id'))
            ->get(['orden_trabajos.id', 'orden_trabajos.tenant_id'])
            // Con el modelo, para que quede el seguimiento DESPACHO del equipo, igual que al finalizar.
            ->each(fn (object $orden) => Despacho::create([
                'orden_trabajo_id' => $orden->id,
                'tenant_id' => $orden->tenant_id,
            ]));
    }

    /**
     * Los despachos creados no se deshacen: pueden haberse agendado o entregado ya.
     */
    public function down(): void
    {
        //
    }
};
