<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Los campos ya están en la migración de creación; esta migración
     * solo los agrega a las bases de datos donde servicio_terceros se creó antes.
     */
    public function up(): void
    {
        Schema::table('servicio_terceros', function (Blueprint $table) {
            if (! Schema::hasColumn('servicio_terceros', 'estado_final_equipo')) {
                $table->enum('estado_final_equipo', ['APROBADO', 'RECHAZADO'])->nullable()->after('pdf_servicio');
            }

            if (! Schema::hasColumn('servicio_terceros', 're_agendar')) {
                $table->boolean('re_agendar')->default(false)->after('estado_final_equipo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
