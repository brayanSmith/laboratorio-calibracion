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
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'fabricante')) {
                $table->string('fabricante')->nullable()->after('nombre');
            }

            if (! Schema::hasColumn('items', 'numero_parte')) {
                $table->string('numero_parte')->nullable()->after('fabricante');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['fabricante', 'numero_parte']);
        });
    }
};
