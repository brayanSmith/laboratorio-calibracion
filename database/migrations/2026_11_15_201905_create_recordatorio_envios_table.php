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
        Schema::create('recordatorio_envios', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);
            $table->boolean('es_prueba')->default(false);
            $table->string('destinatario');
            $table->string('correo');
            $table->string('asunto');
            $table->json('equipos');
            $table->string('estado', 10)->default('EN_COLA');
            $table->text('error')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recordatorio_envios');
    }
};
