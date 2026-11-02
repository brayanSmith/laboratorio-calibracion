<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Completa las fechas de recepción de los ingresos y de despacho de los despachos que
     * se recibieron/entregaron antes de que se guardaran solas, con el día en que el
     * seguimiento del equipo registró ese paso. Solo llena las que están vacías.
     */
    public function up(): void
    {
        $this->completarFechasRecepcion();
        $this->completarFechasDespacho();
    }

    /**
     * Las fechas completadas no se distinguen de las registradas, así que no se deshacen.
     */
    public function down(): void
    {
        //
    }

    /**
     * Ingresos RECIBIDO sin fecha: el primer "INGRESO / RECIBIDO" de sus equipos.
     */
    private function completarFechasRecepcion(): void
    {
        DB::table('ingresos')
            ->where('estado_ingreso', 'RECIBIDO')
            ->whereNull('fecha_recepcion')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->each(function (int $ingresoId): void {
                $recibido = DB::table('seguimiento_programacion_equipos')
                    ->join('equipo_programacions', 'equipo_programacions.id', '=', 'seguimiento_programacion_equipos.programacion_equipo_id')
                    ->where('equipo_programacions.ingreso_id', $ingresoId)
                    ->where('seguimiento_programacion_equipos.fase', 'INGRESO')
                    ->where('seguimiento_programacion_equipos.seguimiento', 'RECIBIDO')
                    ->whereNull('seguimiento_programacion_equipos.deleted_at')
                    ->min('seguimiento_programacion_equipos.created_at');

                if ($recibido !== null) {
                    DB::table('ingresos')
                        ->where('id', $ingresoId)
                        ->update(['fecha_recepcion' => $this->comoFecha($recibido)]);
                }
            });
    }

    /**
     * Despachos entregados sin fecha: el primer "DESPACHO / ENTREGADO" del equipo desde que
     * se creó el despacho (un equipo puede tener varios despachos).
     */
    private function completarFechasDespacho(): void
    {
        DB::table('despachos')
            ->join('orden_trabajos', 'orden_trabajos.id', '=', 'despachos.orden_trabajo_id')
            ->where('despachos.entrega_recibida', true)
            ->whereNull('despachos.fecha_despacho')
            ->whereNull('despachos.deleted_at')
            ->get(['despachos.id', 'despachos.created_at', 'orden_trabajos.equipo_programacion_id'])
            ->each(function (object $despacho): void {
                $entregado = DB::table('seguimiento_programacion_equipos')
                    ->where('programacion_equipo_id', $despacho->equipo_programacion_id)
                    ->where('fase', 'DESPACHO')
                    ->where('seguimiento', 'ENTREGADO')
                    ->where('created_at', '>=', $despacho->created_at)
                    ->whereNull('deleted_at')
                    ->min('created_at');

                if ($entregado !== null) {
                    DB::table('despachos')
                        ->where('id', $despacho->id)
                        ->update(['fecha_despacho' => $this->comoFecha($entregado)]);
                }
            });
    }

    /**
     * El día del momento dado, con el mismo formato con el que Eloquent guarda las columnas
     * con cast "date" (AAAA-MM-DD 00:00:00), para no mezclar formatos en SQLite.
     */
    private function comoFecha(string $momento): string
    {
        return Carbon::parse($momento)->startOfDay()->toDateTimeString();
    }
};
