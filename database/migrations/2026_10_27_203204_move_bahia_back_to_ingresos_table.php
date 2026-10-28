<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada ingreso vuelve a ser de una sola bahía: al registrar varias bahías a la vez se
     * crea un ingreso por bahía. Un ingreso que ya tenía varias se separa en uno por bahía,
     * y cada equipo pasa al ingreso de su bahía.
     */
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->foreignId('bahia_id')->nullable()->after('id')->constrained('bahias')->onDelete('cascade');
        });

        DB::table('bahia_ingreso')
            ->orderBy('ingreso_id')
            ->orderBy('bahia_id')
            ->get()
            ->groupBy('ingreso_id')
            ->each(function ($asignaciones, int $ingresoId): void {
                $bahiaIds = $asignaciones->pluck('bahia_id')->all();

                DB::table('ingresos')->where('id', $ingresoId)->update(['bahia_id' => array_shift($bahiaIds)]);

                foreach ($bahiaIds as $bahiaId) {
                    $this->separarIngreso($ingresoId, $bahiaId);
                }
            });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->foreignId('bahia_id')->nullable(false)->change();
        });

        Schema::dropIfExists('bahia_ingreso');
    }

    public function down(): void
    {
        Schema::create('bahia_ingreso', function (Blueprint $table) {
            $table->foreignId('bahia_id')->constrained('bahias')->onDelete('cascade');
            $table->foreignId('ingreso_id')->constrained('ingresos')->onDelete('cascade');
            $table->timestamps();

            $table->primary(['bahia_id', 'ingreso_id']);
        });

        DB::table('ingresos')->orderBy('id')->get(['id', 'bahia_id'])->each(function (object $ingreso): void {
            DB::table('bahia_ingreso')->insert([
                'bahia_id' => $ingreso->bahia_id,
                'ingreso_id' => $ingreso->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bahia_id');
        });
    }

    /**
     * Copia el ingreso para la bahía dada y le pasa los equipos de esa bahía.
     */
    private function separarIngreso(int $ingresoId, int $bahiaId): void
    {
        $original = (array) DB::table('ingresos')->where('id', $ingresoId)->first();
        unset($original['id']);

        $copiaId = DB::table('ingresos')->insertGetId([...$original, 'bahia_id' => $bahiaId]);

        DB::table('equipo_programacions')
            ->where('ingreso_id', $ingresoId)
            ->whereIn('equipo_id', DB::table('equipos')->where('bahia_id', $bahiaId)->select('id'))
            ->update(['ingreso_id' => $copiaId]);
    }
};
