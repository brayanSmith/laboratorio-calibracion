<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El estado inicial del equipo pasa a ser Operativo / Inoperativo (el "Fuera de servicio"
     * queda solo para el estado final) y se le agregan observaciones.
     */
    public function up(): void
    {
        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->string('estado_inicial_equipo', 20)->nullable()->change();
            $table->text('observaciones_estado_inicial')->nullable()->after('estado_inicial_equipo');
        });

        DB::table('mantenimientos')
            ->where('estado_inicial_equipo', 'FUERA_DE_SERVICIO')
            ->update(['estado_inicial_equipo' => 'INOPERATIVO']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('mantenimientos')
            ->where('estado_inicial_equipo', 'INOPERATIVO')
            ->update(['estado_inicial_equipo' => 'FUERA_DE_SERVICIO']);

        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->dropColumn('observaciones_estado_inicial');
            $table->enum('estado_inicial_equipo', ['OPERATIVO', 'FUERA_DE_SERVICIO'])->nullable()->change();
        });
    }
};
