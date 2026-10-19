<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `fecha_despacho` era el día programado y `fecha_entrega` el día real: ahora el
     * programado se llama `fecha_programada` y el real `fecha_despacho`. El orden importa
     * para no chocar con el nombre.
     */
    public function up(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->renameColumn('fecha_despacho', 'fecha_programada');
        });

        Schema::table('despachos', function (Blueprint $table) {
            $table->renameColumn('fecha_entrega', 'fecha_despacho');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->renameColumn('fecha_despacho', 'fecha_entrega');
        });

        Schema::table('despachos', function (Blueprint $table) {
            $table->renameColumn('fecha_programada', 'fecha_despacho');
        });
    }
};
