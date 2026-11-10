<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El estado final "Rechazado" de un servicio de tercero pasa a llamarse "Fuera de
     * servicio", como en los mantenimientos y calibraciones propios.
     */
    public function up(): void
    {
        $this->renombrar('RECHAZADO', 'FUERA_DE_SERVICIO');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renombrar('FUERA_DE_SERVICIO', 'RECHAZADO');
    }

    /**
     * Admite los dos valores mientras se actualizan los registros y luego deja solo el nuevo.
     */
    private function renombrar(string $anterior, string $nuevo): void
    {
        Schema::table('servicio_terceros', function (Blueprint $table) use ($anterior, $nuevo) {
            $table->enum('estado_final_equipo', ['APROBADO', $anterior, $nuevo])->nullable()->change();
        });

        DB::table('servicio_terceros')->where('estado_final_equipo', $anterior)->update(['estado_final_equipo' => $nuevo]);

        Schema::table('servicio_terceros', function (Blueprint $table) use ($nuevo) {
            $table->enum('estado_final_equipo', ['APROBADO', $nuevo])->nullable()->change();
        });
    }
};
