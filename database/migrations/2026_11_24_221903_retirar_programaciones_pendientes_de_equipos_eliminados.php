<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Antes, al eliminar un equipo su próxima programación (PENDIENTE, sin ingreso ni orden de
     * trabajo) quedaba viva. Ahora la relación con el equipo incluye los eliminados para que su
     * historial se siga viendo, así que esas programaciones se retiran para que no aparezcan
     * en la agenda ni en los ingresos. Su historial (lo ya ingresado o atendido) se conserva.
     */
    public function up(): void
    {
        DB::table('equipo_programacions')
            ->whereNull('deleted_at')
            ->where('estado_programacion', 'PENDIENTE')
            ->whereNull('ingreso_id')
            ->whereIn('equipo_id', fn (Builder $query) => $query->select('id')
                ->from('equipos')
                ->whereNotNull('deleted_at'))
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('orden_trabajos')
                ->whereColumn('orden_trabajos.equipo_programacion_id', 'equipo_programacions.id'))
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
