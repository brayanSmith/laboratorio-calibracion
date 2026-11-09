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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo_documento')->nullable(); // DNI, SAP o CE (ver TipoDocumentoCliente)
            $table->string('numero_documento', 50)->nullable();
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            // El número de documento identifica al cliente dentro del tenant (por ejemplo, al importar equipos).
            $table->unique(['tenant_id', 'numero_documento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
