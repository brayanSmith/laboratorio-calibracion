<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Códigos de las áreas por defecto (ver AreaSeeder).
     *
     * @var list<string>
     */
    private const CODIGOS = ['CRC', 'TMM', 'TSOL', 'TMAQ', 'TCE'];

    /**
     * Run the migrations.
     *
     * Las áreas por defecto se guardaron con el nombre completo en "nombre" y el código en
     * "descripcion". Se intercambian para que el código quede en "nombre". Solo se tocan las
     * que aún tienen el código en "descripcion", así que repetirla no las vuelve a invertir.
     */
    public function up(): void
    {
        $this->intercambiar('descripcion', 'nombre');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->intercambiar('nombre', 'descripcion');
    }

    /**
     * Intercambia nombre y descripcion de las áreas cuyo código está hoy en $columnaCodigo.
     */
    private function intercambiar(string $columnaCodigo, string $columnaTexto): void
    {
        DB::table('areas')
            ->whereIn($columnaCodigo, self::CODIGOS)
            ->orderBy('id')
            ->each(function (object $area) use ($columnaCodigo, $columnaTexto): void {
                DB::table('areas')->where('id', $area->id)->update([
                    $columnaCodigo => $area->{$columnaTexto},
                    $columnaTexto => $area->{$columnaCodigo},
                ]);
            });
    }
};
