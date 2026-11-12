<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración del recordatorio por correo de equipos vencidos, próximos a vencer y
     * agendados: una sola por tenant.
     */
    public function up(): void
    {
        Schema::create('recordatorios', function (Blueprint $table) {
            $table->id();
            $table->boolean('activo')->default(false);
            $table->enum('frecuencia', ['SEMANAL', 'MENSUAL'])->default('SEMANAL');
            $table->unsignedTinyInteger('dia_semana')->nullable(); // 1 = lunes ... 7 = domingo (ISO), si es SEMANAL
            $table->unsignedTinyInteger('dia_mes')->nullable(); // 1 a 28, si es MENSUAL
            $table->string('hora_envio', 5)->default('08:00'); // HH:MM en UTC
            $table->unsignedSmallInteger('dias_anticipacion')->default(30); // Rango de "próximos a vencer"
            $table->boolean('enviar_a_clientes')->default(true); // Cada cliente recibe sus equipos
            $table->boolean('enviar_resumen_bahia')->default(true); // Cada usuario de bahia_user recibe los de sus bahías
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recordatorios');
    }
};
