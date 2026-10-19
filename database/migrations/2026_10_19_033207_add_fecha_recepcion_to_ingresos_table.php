<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Día en que se recibieron los equipos; se completa al recibir el ingreso.
     */
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->date('fecha_recepcion')->nullable()->after('hasta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropColumn('fecha_recepcion');
        });
    }
};
