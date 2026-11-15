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
        Schema::create('recordatorio_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);
            $table->string('nombre', 80);
            $table->string('asunto', 150);
            $table->text('mensaje');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo', 'nombre']);
        });

        Schema::table('recordatorios', function (Blueprint $table) {
            // Null: se usa la plantilla predeterminada (asunto_* y mensaje_* del recordatorio).
            $table->foreignId('plantilla_cliente_id')->nullable()->after('mensaje_bahia')
                ->constrained('recordatorio_plantillas')->nullOnDelete();
            $table->foreignId('plantilla_bahia_id')->nullable()->after('plantilla_cliente_id')
                ->constrained('recordatorio_plantillas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recordatorios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plantilla_cliente_id');
            $table->dropConstrainedForeignId('plantilla_bahia_id');
        });

        Schema::dropIfExists('recordatorio_plantillas');
    }
};
