<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Día en que realmente se entregó el equipo (puede diferir de `fecha_despacho`, el día
     * programado). Se fija sola cuando la entrega queda recibida.
     */
    public function up(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->date('fecha_entrega')->nullable()->after('fecha_despacho');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            $table->dropColumn('fecha_entrega');
        });
    }
};
