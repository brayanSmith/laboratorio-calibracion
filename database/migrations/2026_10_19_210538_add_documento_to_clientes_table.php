<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nulos para los clientes que ya existen; los formularios los exigen desde ahora.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('tipo_documento')->nullable()->after('nombre'); // NIT, CC, CE o PASAPORTE
            $table->string('numero_documento', 50)->nullable()->after('tipo_documento');

            // El número de documento identifica al cliente dentro del tenant (por ejemplo, al importar equipos).
            $table->unique(['tenant_id', 'numero_documento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'numero_documento']);
            $table->dropColumn(['tipo_documento', 'numero_documento']);
        });
    }
};
