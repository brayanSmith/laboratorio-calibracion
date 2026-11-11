<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('equipo_programacions', function (Blueprint $table) {
            // La programación no recibida de la que sale esta, al re-agendar el equipo.
            $table->foreignId('re_agendada_de_id')->nullable()->after('datos_re_agendamiento')
                ->constrained('equipo_programacions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipo_programacions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('re_agendada_de_id');
        });
    }
};
