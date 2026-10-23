<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si el servicio histórico fue un mantenimiento o una calibración. Se agrega como nulo y
     * luego se vuelve obligatorio: SQLite no permite agregar directamente una columna
     * obligatoria sin valor por defecto.
     */
    public function up(): void
    {
        Schema::table('equipo_historicos', function (Blueprint $table) {
            $table->string('tipo_servicio')->nullable()->after('equipo_id'); // MANTENIMIENTO o CALIBRACION
        });

        Schema::table('equipo_historicos', function (Blueprint $table) {
            $table->string('tipo_servicio')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('equipo_historicos', function (Blueprint $table) {
            $table->dropColumn('tipo_servicio');
        });
    }
};
