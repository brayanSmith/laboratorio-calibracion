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
        Schema::create('equipo_programacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
            $table->string('tipo_servicio'); // CSV de tipos de servicio, ej: "MANTENIMIENTO,CALIBRACION"
            // PREVENTIVO: tiene fecha de programación (intervalo_servicio/fecha_proximo_servicio).
            // CORRECTIVO: se agenda desde un ingreso al detectar una falla, sin fecha programada.
            $table->enum('tipo_mantenimiento', ['PREVENTIVO', 'CORRECTIVO'])->default('PREVENTIVO');
            $table->text('falla_detectada')->nullable(); // Solo cuando tipo_mantenimiento es CORRECTIVO
            $table->decimal('intervalo_servicio', 10, 2)->nullable();
            $table->enum('intervalo_unidad', ['DIAS', 'SEMANAS', 'MESES'])->nullable();
            $table->date('fecha_apertura_historial_servicio')->nullable();
            $table->date('fecha_ultimo_servicio')->nullable();
            $table->date('fecha_proximo_servicio')->nullable(); // Calculada: fecha_ultimo_servicio + intervalo_servicio
            $table->decimal('dias_plazo_vencimiento')->default(30); // Margen para el estado de vencimiento calculado
            // Nullable: una programación existe desde que se registra el equipo, antes de
            // que cualquier ingreso la traiga a mantenimiento o calibración.
            $table->foreignId('ingreso_id')->nullable()->constrained('ingresos')->nullOnDelete();

            $table->boolean('agendar')->default(true);
            // Si el equipo efectivamente llegó al recibir el ingreso (ver "Recibir
            // equipos"). Por defecto true: se espera que todo lo agendado se reciba.
            $table->boolean('ingresado')->default(true);

            $table->enum('estado_programacion', ['PENDIENTE', 'AGENDADO', 'CANCELADO'])->default('PENDIENTE');
            // Solo cuando estado_programacion es CANCELADO. Catálogo configurable por tenant
            // (categoria INGRESO), en vez de una lista fija.
            $table->foreignId('novedad_ingreso_id')->nullable()->constrained('novedads')->nullOnDelete();
            $table->text('observacion_no_ingreso')->nullable(); // Detalle adicional opcional
            $table->boolean('re_agendar')->default(false); // Solo tiene sentido junto con novedad_ingreso_id
            $table->json('datos_re_agendamiento')->nullable(); // Fecha del próximo agendamiento, si re_agendar es true
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipo_programacions');
    }
};
