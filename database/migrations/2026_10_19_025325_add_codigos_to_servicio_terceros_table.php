<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Solo se usan en los servicios de tercero de mantenimiento; ambos son opcionales.
     */
    public function up(): void
    {
        Schema::table('servicio_terceros', function (Blueprint $table) {
            $table->string('codigo_compra', 50)->nullable()->after('pdf_servicio');
            $table->string('codigo_despacho', 50)->nullable()->after('codigo_compra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servicio_terceros', function (Blueprint $table) {
            $table->dropColumn(['codigo_compra', 'codigo_despacho']);
        });
    }
};
