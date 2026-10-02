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
            // Nullable: al crear el ingreso solo se sabe la bahía y el rango de fechas;
            // el técnico y el cliente se completan después, al editar el ingreso.
            $table->foreignId('tecnico_recibe_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('cliente_entrega_id')->nullable()->constrained('clientes')->onDelete('cascade');
            $table->string('firma_cliente_entrega')->nullable();
            $table->enum('estado_ingreso', ['PENDIENTE', 'INGRESADO', 'CANCELADO'])->default('PENDIENTE');
            $table->text('novedad')->nullable(); // Notas generales cuando el ingreso queda aprobado
            $table->text('motivo_cancelacion')->nullable(); // Solo cuando estado_ingreso es CANCELADO
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
