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
        Schema::table('novedads', function (Blueprint $table) {
            $table->boolean('es_motivo_cancelacion')->default(false)->after('inactivar_equipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('novedads', function (Blueprint $table) {
            $table->dropColumn('es_motivo_cancelacion');
        });
    }
};
