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
        Schema::create('detalle_medicion_calibracions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calibracion_id')->constrained('calibracions')->onDelete('cascade');
            $table->foreignId('detalle_medicion_alcance_id')->constrained('detalle_medicion_alcances')->onDelete('cascade');
            $table->decimal('valor_referencia', 10, 2);
            $table->foreignId('unidad_medida_id')->constrained('unidad_medidas')->onDelete('cascade');
            $table->decimal('valor_instrumento', 10, 2);
            $table->decimal('error_encontrado', 10, 2);
            $table->decimal('emp', 10, 2);
            $table->decimal('incertidumbre', 10, 2);
            $table->decimal('error_porcentaje', 10, 2);
            $table->decimal('emp_porcentaje_positivo', 10, 2);
            $table->decimal('emp_porcentaje_negativo', 10, 2);
            $table->decimal('resultado_calibracion', 10, 2);
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
        Schema::dropIfExists('detalle_medicion_calibracions');
    }
};
