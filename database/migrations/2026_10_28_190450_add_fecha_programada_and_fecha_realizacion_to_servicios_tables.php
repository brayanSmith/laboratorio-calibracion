<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mantenimientos, calibraciones y servicios de terceros guardan el día para el que se
     * programaron (lo elige quien agenda) y el día en que realmente se terminaron (se fija
     * solo al finalizar), para ver el plazo y si se cumplió.
     *
     * Los existentes toman como programada la fecha que ya tenían (o el día en que se
     * crearon) y, si ya terminaron, como realizada el fin de su tiempo de servicio (o su
     * última actualización).
     */
    public function up(): void
    {
        foreach (['mantenimientos', 'calibracions', 'servicio_terceros'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->date('fecha_programada')->nullable()->after('orden_trabajo_id');
                $table->date('fecha_realizacion')->nullable()->after('fecha_programada');
            });
        }

        $this->completar('mantenimientos', 'MANTENIMIENTO', 'fecha_mantenimiento', fn (object $registro): bool => $registro->estado_mantenimiento === 'FINALIZADO');
        $this->completar('calibracions', 'CALIBRACION', 'fecha_calibracion', fn (object $registro): bool => in_array($registro->estado_calibracion, ['FINALIZADO', 'DEVOLVER_MANTENIMIENTO', 'FUERA_DE_SERVICIO'], true));
        $this->completar('servicio_terceros', null, null, fn (object $registro): bool => $registro->estado_servicio === 'FINALIZADO');
    }

    public function down(): void
    {
        foreach (['mantenimientos', 'calibracions', 'servicio_terceros'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['fecha_programada', 'fecha_realizacion']);
            });
        }
    }

    /**
     * @param  string|null  $tipoServicio  el del tiempo de servicio; null si se toma del registro (servicios de terceros)
     * @param  string|null  $columnaFecha  la fecha que ya tenía; null si se usa el día de creación
     * @param  Closure(object): bool  $terminado
     */
    private function completar(string $tabla, ?string $tipoServicio, ?string $columnaFecha, Closure $terminado): void
    {
        DB::table($tabla)->orderBy('id')->get()->each(function (object $registro) use ($tabla, $tipoServicio, $columnaFecha, $terminado): void {
            $fechaRealizacion = null;

            if ($terminado($registro)) {
                $fin = DB::table('tiempo_servicios')
                    ->where('orden_trabajo_id', $registro->orden_trabajo_id)
                    ->where('tipo_servicio', $tipoServicio ?? $registro->tipo_servicio)
                    ->whereNotNull('fin')
                    ->orderByDesc('id')
                    ->value('fin');

                $fechaRealizacion = substr((string) ($fin ?? $registro->updated_at), 0, 10);
            }

            DB::table($tabla)->where('id', $registro->id)->update([
                'fecha_programada' => substr((string) ($columnaFecha ? $registro->{$columnaFecha} : $registro->created_at), 0, 10),
                'fecha_realizacion' => $fechaRealizacion,
            ]);
        });
    }
};
