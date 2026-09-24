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
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('tipo_equipo_id')->constrained('tipo_equipos')->onDelete('cascade');
            $table->enum('tipo_tecnologia', ['ANALOGICO', 'DIGITAL']);
            $table->string('modelo');
            $table->foreignId('fabricante_id')->constrained('fabricantes')->onDelete('cascade');
            $table->string('numero_serie');
            $table->json('ficha_tecnica')->nullable();
            $table->foreignId('area_id')->constrained('areas')->onDelete('cascade');
            $table->foreignId('bahia_id')->constrained('bahias')->onDelete('cascade');
            $table->string('condicion_actual');
            $table->string('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('patron_referencia')->default(false);
            $table->string('concatenar_codigo_nombre')->nullable();
            $table->boolean('requiere_programacion')->default(true);
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
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
        Schema::dropIfExists('equipos');
    }
};
