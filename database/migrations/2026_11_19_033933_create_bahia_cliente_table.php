<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clientes asignados a cada bahía: una bahía puede tener varios clientes y un cliente
     * varias bahías.
     */
    public function up(): void
    {
        Schema::create('bahia_cliente', function (Blueprint $table) {
            $table->foreignId('bahia_id')->constrained('bahias')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->timestamps();

            $table->primary(['bahia_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahia_cliente');
    }
};
