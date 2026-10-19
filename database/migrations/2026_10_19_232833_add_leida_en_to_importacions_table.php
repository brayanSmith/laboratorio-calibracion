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
        Schema::table('importacions', function (Blueprint $table) {
            // Cuándo el usuario revisó el resultado; null si aún no lo ha visto (ver el globito del botón Importar).
            $table->timestamp('leida_en')->nullable()->after('errores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('importacions', function (Blueprint $table) {
            $table->dropColumn('leida_en');
        });
    }
};
