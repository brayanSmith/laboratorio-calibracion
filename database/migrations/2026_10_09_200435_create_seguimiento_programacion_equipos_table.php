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
        Schema::create('seguimiento_programacion_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacion_equipo_id')->constrained('equipo_programacions')->onDelete('cascade');
            $table->enum('fase', ['BAHIA', 'INGRESO', 'MANTENIMIENTO', 'CALIBRACION', 'DESPACHO']);
            $table->string('seguimiento')->nullable();
            $table->text('observaciones')->nullable();
            // Nulo cuando el cambio ocurre fuera de una sesión (seeders, comandos).
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
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
        Schema::dropIfExists('seguimiento_programacion_equipos');
    }
};
