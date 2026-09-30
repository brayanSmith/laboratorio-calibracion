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
        Schema::create('equipo_programacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
            $table->string('tipo_servicio'); // CSV de tipos de servicio, ej: "MANTENIMIENTO,CALIBRACION"
            $table->decimal('intervalo_servicio', 10, 2)->nullable();
            $table->enum('intervalo_unidad', ['DIAS', 'SEMANAS', 'MESES'])->nullable();
            $table->date('fecha_apertura_historial_servicio')->nullable();
            $table->date('fecha_ultimo_servicio')->nullable();
            $table->date('fecha_proximo_servicio')->nullable(); // Calculada: fecha_ultimo_servicio + intervalo_servicio
            $table->decimal('dias_plazo_vencimiento')->default(30); // Margen para el estado de vencimiento calculado
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
        Schema::dropIfExists('equipo_programacions');
    }
};
