<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un ingreso puede recibir equipos de varias bahías a la vez: la bahía deja de ser una
     * columna del ingreso y pasa a esta tabla. Cada ingreso existente conserva la suya.
     */
    public function up(): void
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
     * Cada ingreso vuelve a una sola bahía: la de menor id entre las suyas.
     */
    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->foreignId('bahia_id')->nullable()->after('id')->constrained('bahias')->onDelete('cascade');
        });

        DB::table('bahia_ingreso')->orderBy('ingreso_id')->orderBy('bahia_id')->get()->unique('ingreso_id')->each(function (object $asignacion): void {
            DB::table('ingresos')->where('id', $asignacion->ingreso_id)->update(['bahia_id' => $asignacion->bahia_id]);
        });

        Schema::dropIfExists('bahia_ingreso');
    }
};
