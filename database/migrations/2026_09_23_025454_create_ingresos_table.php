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
        Schema::create('ingresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bahia_id')->constrained('bahias')->onDelete('cascade');
            $table->date('desde'); // campo de filtro
            $table->date('hasta'); // campo de filtro
            $table->foreignId('tecnico_recibe_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cliente_entrega_id')->constrained('clientes')->onDelete('cascade');
            $table->string('firma_cliente_entrega')->nullable();
            $table->boolean('ingreso_exitoso')->default(true);
            $table->text('novedad')->nullable();
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
        Schema::dropIfExists('ingresos');
    }
};
