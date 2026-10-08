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
        Schema::create('despachos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('orden_trabajo_id')->constrained('orden_trabajos')->onDelete('cascade');
            // Nulos al crearse (ver CalibracionController::finalizar()); se completan
            // cuando se agenda y se realiza el despacho.
            $table->foreignId('tecnico_entrega_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('cliente_recibe_id')->nullable()->constrained('clientes')->onDelete('cascade');
            $table->boolean('entrega_autorizada')->default(false);
            $table->string('firma_cliente_recibe')->nullable();
            $table->boolean('entrega_recibida')->default(false);
            $table->foreignId('novedad_id')->nullable()->constrained('novedads')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('despachos');
    }
};
