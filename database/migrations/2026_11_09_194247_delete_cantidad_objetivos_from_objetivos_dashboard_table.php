<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Las cantidades de mantenimientos y calibraciones ya no se definen por tipo de equipo:
     * ahora son los objetivos de cada usuario (tabla objetivo_users).
     */
    public function up(): void
    {
        DB::table('objetivos_dashboard')
            ->whereIn('concepto', ['CANTIDAD_MANTENIMIENTOS', 'CANTIDAD_CALIBRACIONES'])
            ->delete();
    }

    /**
     * Las cantidades borradas no se recuperan.
     */
    public function down(): void
    {
        //
    }
};
