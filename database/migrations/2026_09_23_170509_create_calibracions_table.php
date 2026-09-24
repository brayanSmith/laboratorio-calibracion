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
        Schema::create('calibracions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('orden_trabajos')->onDelete('cascade');
            $table->foreignId('laboratorio_id')->constrained('laboratorios')->onDelete('cascade');
            $table->foreignId('solicitante_id')->constrained('areas')->onDelete('cascade');
            $table->foreignId('tecnico_id')->constrained('users')->onDelete('cascade');
            $table->decimal('temperatura', 10, 2)->nullable();
            $table->decimal('humedad', 10, 2)->nullable();
            $table->foreignId('procedimiento_id')->constrained('procedimientos')->onDelete('cascade');
            $table->boolean('ajustes_requeridos')->default(false);
            $table->enum('estado_calibracion', ['PENDIENTE', 'EN_PROCESO', 'FINALIZADO', 'DEVOLVER_MANTENIMIENTO']);
            $table->boolean('firmado')->default(false);
            $table->foreignId('novedad_id')->constrained('novedads')->onDelete('cascade');
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
        Schema::dropIfExists('calibracions');
    }
};
