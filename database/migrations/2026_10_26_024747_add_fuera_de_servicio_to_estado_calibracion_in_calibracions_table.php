<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una calibración puede terminar con el equipo fuera de servicio, lo que lo deja inactivo.
     */
    public function up(): void
    {
        Schema::table('calibracions', function (Blueprint $table) {
            $table->enum('estado_calibracion', ['PENDIENTE', 'EN_PROCESO', 'FINALIZADO', 'DEVOLVER_MANTENIMIENTO', 'FUERA_DE_SERVICIO'])
                ->default('PENDIENTE')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('calibracions', function (Blueprint $table) {
            $table->enum('estado_calibracion', ['PENDIENTE', 'EN_PROCESO', 'FINALIZADO', 'DEVOLVER_MANTENIMIENTO'])
                ->default('PENDIENTE')
                ->change();
        });
    }
};
