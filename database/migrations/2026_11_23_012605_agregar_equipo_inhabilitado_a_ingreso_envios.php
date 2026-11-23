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
        // Cada paso por separado y solo si falta: si un intento anterior se cortó a medias,
        // MySQL no deshace lo que alcanzó a hacer.
        if (! Schema::hasColumn('ingreso_envios', 'tipo')) {
            Schema::table('ingreso_envios', function (Blueprint $table) {
                $table->string('tipo', 20)->default('INGRESO')->after('id');
            });
        }

        Schema::table('ingreso_envios', function (Blueprint $table) {
            $table->unsignedBigInteger('ingreso_id')->nullable()->change();
        });

        if (! Schema::hasColumn('ingreso_envios', 'equipo_id')) {
            Schema::table('ingreso_envios', function (Blueprint $table) {
                $table->foreignId('equipo_id')->nullable()->after('ingreso_id')->constrained()->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('ingreso_envios', 'motivo')) {
            Schema::table('ingreso_envios', function (Blueprint $table) {
                $table->string('motivo')->nullable()->after('correo');
            });
        }
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
