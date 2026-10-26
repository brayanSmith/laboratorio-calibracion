<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Por qué el sistema dejó el equipo inactivo (ej. "Calibración: Fuera de servicio"). Nulo
     * si está activo o si se inactivó a mano.
     */
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->string('motivo_inactivo')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn('motivo_inactivo');
        });
    }
};
