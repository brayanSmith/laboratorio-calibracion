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
            $table->foreignId('ingreso_id')->constrained('ingresos')->onDelete('cascade')->nullable();
            $table->foreignId('despacho_id')->constrained('despachos')->onDelete('cascade')->nullable();
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
            $table->date('fecha_programada_orden_trabajo');
            $table->date('fecha_vencimiento');
            $table->decimal('dias_plazo_vencimiento');
            $table->enum('estado_vencimiento', ['AL_DIA', 'PROXIMO_A_VENCER', 'VENCIDO']);
            $table->enum('estado', ['EN_BAHIA', 'INGRESADO', 'EN_MANTENIMIENTO', 'EN_CALIBRACION', 'FINALIZADO', 'ENTREGADO']);
            $table->boolean('equipo_ingresado');
            $table->foreignId('novedad_ingreso_id')->constrained('novedad_ingresos')->onDelete('cascade');
            $table->boolean('requiere_calibracion')->default(false);
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
