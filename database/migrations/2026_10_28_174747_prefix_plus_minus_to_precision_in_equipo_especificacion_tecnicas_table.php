<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La precisión siempre lleva ± adelante; el % al final es opcional ("±0.5" o "±0.5%").
     * Las guardadas solo con % ("5%") pasan a "±5%".
     */
    public function up(): void
    {
        DB::table('equipo_especificacion_tecnicas')
            ->whereNotNull('precision')
            ->get(['id', 'precision'])
            ->reject(fn (object $especificacion): bool => str_starts_with($especificacion->precision, '±'))
            ->each(fn (object $especificacion) => DB::table('equipo_especificacion_tecnicas')
                ->where('id', $especificacion->id)
                ->update(['precision' => "±{$especificacion->precision}"]));
    }

    /**
     * Las de porcentaje vuelven a guardarse sin ± ("±5%" pasa a "5%").
     */
    public function down(): void
    {
        DB::table('equipo_especificacion_tecnicas')
            ->whereNotNull('precision')
            ->get(['id', 'precision'])
            ->filter(fn (object $especificacion): bool => str_starts_with($especificacion->precision, '±') && str_ends_with($especificacion->precision, '%'))
            ->each(fn (object $especificacion) => DB::table('equipo_especificacion_tecnicas')
                ->where('id', $especificacion->id)
                ->update(['precision' => mb_substr($especificacion->precision, 1)]));
    }
};
