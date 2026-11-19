<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de los correos del ingreso: a quién, si salió o falló (y por qué), y quién lo
     * disparó, para avisarle en sus notificaciones.
     */
    public function up(): void
    {
        Schema::create('ingreso_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingreso_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destinatario');
            $table->string('correo');
            $table->string('estado', 10)->default('EN_COLA');
            $table->text('error')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'leido_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingreso_envios');
    }
};
