<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El número del certificado (o informe) que entregó el tercero y la fecha en que
     * realmente hizo el servicio, para registrarlos en el servicio de tercero.
     */
    public function up(): void
    {
        Schema::table('servicio_terceros', function (Blueprint $table) {
            if (! Schema::hasColumn('servicio_terceros', 'numero_certificado')) {
                $table->string('numero_certificado', 100)->nullable()->after('codigo_despacho');
            }

            if (! Schema::hasColumn('servicio_terceros', 'fecha_servicio')) {
                $table->date('fecha_servicio')->nullable()->after('numero_certificado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servicio_terceros', function (Blueprint $table) {
            $table->dropColumn(['numero_certificado', 'fecha_servicio']);
        });
    }
};
