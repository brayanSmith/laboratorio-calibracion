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
        Schema::create('entrada_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('precio_total', 10, 2);
            $table->string('codigo_compra')->nullable();
            $table->boolean('recibido')->default(false);
            $table->date('fecha_recibido')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('tenant_id')->constrained('tenants')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrada_items');
    }
};
