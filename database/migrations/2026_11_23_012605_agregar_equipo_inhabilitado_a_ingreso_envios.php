<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El historial de correos de la campanita también guarda los avisos de equipo
     * inhabilitado: de qué tipo es el correo, el equipo y el motivo (sin ingreso).
     */
    public function up(): void
    {
        Schema::table('ingreso_envios', function (Blueprint $table) {
            $table->string('tipo', 20)->default('INGRESO')->after('id');
            $table->foreignId('ingreso_id')->nullable()->change();
            $table->foreignId('equipo_id')->nullable()->after('ingreso_id')->constrained()->cascadeOnDelete();
            $table->string('motivo')->nullable()->after('correo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingreso_envios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipo_id');
            $table->dropColumn(['tipo', 'motivo']);
        });
    }
};
