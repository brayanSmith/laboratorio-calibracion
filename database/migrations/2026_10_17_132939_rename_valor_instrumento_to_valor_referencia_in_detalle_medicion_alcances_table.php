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
        Schema::table('detalle_medicion_alcances', function (Blueprint $table) {
            $table->renameColumn('valor_instrumento', 'valor_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_medicion_alcances', function (Blueprint $table) {
            $table->renameColumn('valor_referencia', 'valor_instrumento');
        });
    }
};
