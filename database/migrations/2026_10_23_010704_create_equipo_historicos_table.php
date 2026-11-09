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
        Schema::create('equipo_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained()->onDelete('cascade');
            $table->string('tipo_servicio'); // MANTENIMIENTO o CALIBRACION
            $table->date('fecha_servicio');
            $table->boolean('es_tercero')->default(false);
            $table->foreignId('empresa_tercero_id')->nullable()->constrained('empresa_terceros')->nullOnDelete();
            $table->string('adjunto')->nullable();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipo_historicos');
    }
};
