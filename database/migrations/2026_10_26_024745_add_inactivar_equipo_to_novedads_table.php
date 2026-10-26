<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si reportar esta novedad (en un ingreso, mantenimiento, calibración o despacho) deja
     * el equipo inactivo, sin tener que hacerlo a mano.
     */
    public function up(): void
    {
        Schema::table('novedads', function (Blueprint $table) {
            $table->boolean('inactivar_equipo')->default(false)->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('novedads', function (Blueprint $table) {
            $table->dropColumn('inactivar_equipo');
        });
    }
};
