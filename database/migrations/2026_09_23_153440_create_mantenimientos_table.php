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
        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('orden_trabajos')->onDelete('cascade');
            $table->enum('tipo_mantenimiento', ['PREVENTIVO', 'CORRECTIVO']);
            $table->date('fecha_mantenimiento');
            $table->text('descripcion');
            $table->enum('estado_inicial_equipo', ['OPERATIVO', 'FUERA_DE_SERVICIO']);
            $table->enum('estado_final_equipo', ['OPERATIVO', 'FUERA_DE_SERVICIO']);
            $table->enum('estado_mantenimiento', ['PENDIENTE', 'EN_PROCESO', 'FALTA_REPUESTOS', 'FINALIZADO']);
            $table->foreignId('tecnico_id')->constrained('users')->onDelete('cascade');
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
        Schema::dropIfExists('mantenimientos');
    }
};
