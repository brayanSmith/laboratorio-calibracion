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
        Schema::create('servicio_terceros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('orden_trabajos')->onDelete('cascade');
            $table->enum('tipo_servicio', ['MANTENIMIENTO', 'CALIBRACION']);
            $table->foreignId('empresa_tercero_id')->constrained('empresa_terceros')->onDelete('cascade');
            $table->string('pdf_servicio')->nullable();
            // Solo en servicios de mantenimiento; ambos opcionales.
            $table->string('codigo_compra', 50)->nullable();
            $table->string('codigo_despacho', 50)->nullable();
            $table->enum('estado_servicio', ['PENDIENTE', 'EN_PROCESO', 'FINALIZADO'])->default('PENDIENTE');
            $table->enum('estado_final_equipo', ['APROBADO', 'RECHAZADO'])->nullable();
            $table->boolean('re_agendar')->default(false);
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
        Schema::dropIfExists('servicio_terceros');
    }
};
