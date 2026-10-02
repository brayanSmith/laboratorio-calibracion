<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orden_trabajos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->foreignId('despacho_id')->constrained('despachos')->onDelete('cascade')->nullable();
            $table->foreignId('equipo_programacion_id')->constrained('equipo_programacions')->onDelete('cascade');
            $table->date('fecha_programada_orden_trabajo');
            $table->enum('estado', ['EN_BAHIA', 'INGRESADO', 'EN_MANTENIMIENTO', 'EN_CALIBRACION', 'FINALIZADO', 'ENTREGADO']);
            // Se deriva del tipo_servicio de la programación (ver OrdenTrabajo::requiereCalibracion()).
            $table->boolean('mantenimiento_asignado_tercero')->default(false);
            $table->boolean('calibracion_asignado_tercero')->default(false);
            $table->boolean('orden_trabajo_programada')->default(false);
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_trabajos');
    }
};
