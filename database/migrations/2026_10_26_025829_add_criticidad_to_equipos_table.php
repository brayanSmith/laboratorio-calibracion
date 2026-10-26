<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Criticidad del equipo (ALTA, MEDIA o BAJA), para filtrarlo en el listado. Nula en los
     * equipos que aún no la tienen asignada.
     */
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->string('criticidad')->nullable()->after('condicion_actual');
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn('criticidad');
        });
    }
};
