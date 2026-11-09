<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los objetivos de desempeño por técnico pasan a ser por mes y tipo de equipo (los de
     * tiempos de atención y cumplimiento siguen siendo anuales y generales: mes y tipo de
     * equipo vacíos). Los objetivos anuales de desempeño que ya había se descartan, porque
     * no tienen mes ni tipo de equipo.
     */
    public function up(): void
    {
        DB::table('objetivos_dashboard')->where('modulo', 'DESEMPENO_TECNICOS')->delete();

        Schema::table('objetivos_dashboard', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'anio', 'concepto']);
            $table->unsignedTinyInteger('mes')->nullable()->after('anio');
            $table->foreignId('tipo_equipo_id')->nullable()->after('mes')->constrained('tipo_equipos')->cascadeOnDelete();
            $table->unique(['tenant_id', 'anio', 'mes', 'tipo_equipo_id', 'concepto'], 'objetivos_dashboard_unico');
        });
    }

    public function down(): void
    {
        DB::table('objetivos_dashboard')->whereNotNull('mes')->delete();

        Schema::table('objetivos_dashboard', function (Blueprint $table) {
            $table->dropUnique('objetivos_dashboard_unico');
            $table->dropConstrainedForeignId('tipo_equipo_id');
            $table->dropColumn('mes');
            $table->unique(['tenant_id', 'anio', 'concepto']);
        });
    }
};
