<?php

use App\Modules\Ingresos\Models\Ingreso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            // Código aleatorio (no adivinable) que se le envía al cliente al recibir sus
            // equipos, para consultarlos sin iniciar sesión.
            $table->string('codigo', 20)->nullable()->unique()->after('id');
        });

        // Los ingresos que ya existían también reciben su código.
        DB::table('ingresos')->whereNull('codigo')->orderBy('id')->pluck('id')->each(
            fn (int $id) => DB::table('ingresos')->where('id', $id)->update(['codigo' => Ingreso::generarCodigo()]),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn('codigo');
        });
    }
};
