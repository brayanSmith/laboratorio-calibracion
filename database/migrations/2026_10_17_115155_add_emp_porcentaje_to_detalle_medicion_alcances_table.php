<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El EMP se guarda en `emp` (valor absoluto, precisión "±") o en `emp_porcentaje`
     * (porcentaje, precisión "%"), según la precisión de los equipos del alcance.
     */
    public function up(): void
    {
        Schema::table('detalle_medicion_alcances', function (Blueprint $table) {
            $table->decimal('emp', 10, 2)->nullable()->change();
            $table->decimal('emp_porcentaje', 10, 2)->nullable()->after('emp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_medicion_alcances', function (Blueprint $table) {
            $table->dropColumn('emp_porcentaje');
        });
    }
};
