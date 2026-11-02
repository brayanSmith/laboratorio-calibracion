<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traza de cada vez que cambió la fecha programada de un despacho (ej. al re-agendar
     * uno vencido): de qué fecha a cuál, por qué y quién lo hizo.
     */
    public function up(): void
    {
        Schema::create('despacho_reagendamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('despacho_id')->constrained('despachos')->onDelete('cascade');
            $table->date('fecha_anterior');
            $table->date('fecha_nueva');
            $table->string('motivo', 500);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despacho_reagendamientos');
    }
};
