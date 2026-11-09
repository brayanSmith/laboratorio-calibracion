<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Objetivos anuales de los indicadores del dashboard: un valor por año y concepto (ver
     * ConceptoObjetivo). El módulo se guarda para leer la tabla con facilidad, pero siempre
     * sale del concepto.
     */
    public function up(): void
    {
        Schema::create('objetivos_dashboard', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes')->nullable(); // Solo en los módulos con objetivos por mes y tipo de equipo
            $table->foreignId('tipo_equipo_id')->nullable()->constrained('tipo_equipos')->cascadeOnDelete();
            $table->string('modulo', 40);
            $table->string('concepto', 60);
            $table->decimal('valor', 12, 2);
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['tenant_id', 'anio', 'mes', 'tipo_equipo_id', 'concepto'], 'objetivos_dashboard_unico');
            $table->index(['tenant_id', 'modulo', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objetivos_dashboard');
    }
};
