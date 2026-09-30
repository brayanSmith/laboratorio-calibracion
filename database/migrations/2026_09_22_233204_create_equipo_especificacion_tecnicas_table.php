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
        Schema::create('equipo_especificacion_tecnicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
            $table->foreignId('tipo_magnitud_id')->constrained('tipo_magnituds')->onDelete('cascade');
            $table->foreignId('unidad_medida_id')->constrained('unidad_medidas')->onDelete('cascade');
            $table->string('alcance_indicacion'); // Ej: "100mm", el símbolo de la unidad de medida
            $table->string('precision'); // Ej: "±0.5" o "0.5%"
            $table->string('resolucion'); // Ej: "0.01mm", el símbolo de la unidad de medida
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
        Schema::dropIfExists('equipo_especificacion_tecnicas');
    }
};
