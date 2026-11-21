<?php

use App\Modules\Ingresos\Models\Ingreso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los códigos de ingreso pasan de "ING-" a "IN-" (el "ING-" se usa para otras cosas). Si
     * el código nuevo ya lo tiene otro ingreso, se le genera uno nuevo.
     */
    public function up(): void
    {
        $this->cambiarPrefijo('ING-', 'IN-');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->cambiarPrefijo('IN-', 'ING-');
    }

    private function cambiarPrefijo(string $desde, string $hacia): void
    {
        DB::table('ingresos')
            ->where('codigo', 'like', $desde.'%')
            ->orderBy('id')
            ->get(['id', 'codigo'])
            ->each(function (object $ingreso) use ($desde, $hacia): void {
                $codigo = $hacia.substr($ingreso->codigo, strlen($desde));

                if (DB::table('ingresos')->where('codigo', $codigo)->exists()) {
                    $codigo = Ingreso::generarCodigo();
                }

                DB::table('ingresos')->where('id', $ingreso->id)->update(['codigo' => $codigo]);
            });
    }
};
