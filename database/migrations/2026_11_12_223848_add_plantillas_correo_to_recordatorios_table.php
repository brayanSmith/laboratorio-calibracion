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
        Schema::table('recordatorios', function (Blueprint $table) {
            $table->string('asunto_cliente', 150)->nullable()->after('correos_copia');
            $table->text('mensaje_cliente')->nullable()->after('asunto_cliente');
            $table->string('asunto_bahia', 150)->nullable()->after('mensaje_cliente');
            $table->text('mensaje_bahia')->nullable()->after('asunto_bahia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recordatorios', function (Blueprint $table) {
            $table->dropColumn(['asunto_cliente', 'mensaje_cliente', 'asunto_bahia', 'mensaje_bahia']);
        });
    }
};
