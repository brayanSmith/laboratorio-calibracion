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
            if (! Schema::hasColumn('importacions', 'reemplazar_existentes')) {
                $table->boolean('reemplazar_existentes')->default(false)->after('lote_adjuntos');
            }

            if (! Schema::hasColumn('importacions', 'filas_actualizadas')) {
                $table->unsignedInteger('filas_actualizadas')->default(0)->after('filas_creadas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('importacions', function (Blueprint $table) {
            $table->dropColumn(['reemplazar_existentes', 'filas_actualizadas']);
        });
    }
};
