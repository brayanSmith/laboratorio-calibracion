<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El código y nombre concatenado ya no se escribe a mano: se calcula con
     * Equipo::codigoNombre(), para que un cambio de regla aplique a todos los equipos.
     */
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn('concatenar_codigo_nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->string('concatenar_codigo_nombre')->nullable()->after('patron_referencia');
        });
    }
};
