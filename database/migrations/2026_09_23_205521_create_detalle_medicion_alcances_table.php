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
        Schema::create('detalle_medicion_alcances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicion_alcance_id')->constrained('medicion_alcances')->onDelete('cascade');
            $table->foreignId('unidad_medida_id')->constrained('unidad_medidas')->onDelete('cascade');
            $table->decimal('valor_instrumento', 10, 2);
            $table->decimal('emp', 10, 2);
            $table->decimal('incertidumbre', 10, 2);
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
        Schema::dropIfExists('detalle_medicion_alcances');
    }
};
