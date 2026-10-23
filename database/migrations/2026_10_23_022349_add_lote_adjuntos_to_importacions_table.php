<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lote de archivos subidos junto al CSV (ej. los adjuntos del histórico de equipos), que
     * cada fila referencia por su nombre.
     */
    public function up(): void
    {
        Schema::table('importacions', function (Blueprint $table) {
            $table->string('lote_adjuntos', 40)->nullable()->after('mapeo');
        });
    }

    public function down(): void
    {
        Schema::table('importacions', function (Blueprint $table) {
            $table->dropColumn('lote_adjuntos');
        });
    }
};
