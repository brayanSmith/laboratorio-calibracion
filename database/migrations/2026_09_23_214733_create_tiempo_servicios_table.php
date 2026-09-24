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
        Schema::create('tiempo_servicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('orden_trabajos')->onDelete('cascade');
            $table->enum('tipo_servicio', ['MANTENIMIENTO', 'CALIBRACION']);
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->time('duracion');
            $table->enum('estado_tiempo', ['INICIO', 'FIN']);
            $table->boolean('es_tercero')->default(false);
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
        Schema::dropIfExists('tiempo_servicios');
    }
};
