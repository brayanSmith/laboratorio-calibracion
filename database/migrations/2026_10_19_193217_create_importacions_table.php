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
        Schema::create('importacions', function (Blueprint $table) {
            $table->id();
            $table->string('tipo'); // Ej: "equipos"
            $table->string('archivo'); // Ruta del CSV en el disco local mientras se procesa
            $table->string('nombre_original');
            $table->string('delimitador', 1);
            $table->string('codificacion');
            $table->json('mapeo'); // campo del sistema => encabezado del CSV
            $table->enum('estado', ['PENDIENTE', 'PROCESANDO', 'COMPLETADA', 'FALLIDA'])->default('PENDIENTE');
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_procesadas')->default(0);
            $table->unsignedInteger('filas_creadas')->default(0);
            $table->unsignedInteger('filas_con_error')->default(0);
            $table->json('errores')->nullable(); // [{fila, mensaje}], limitado para no crecer sin control
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importacions');
    }
};
