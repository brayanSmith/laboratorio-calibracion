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
        Schema::table('item_mantenimientos', function (Blueprint $table) {
            $table->boolean('con_stock')->default(true)->after('cantidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_mantenimientos', function (Blueprint $table) {
            $table->dropColumn('con_stock');
        });
    }
};
