<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('password');
        });

        $tenantId = DB::table('tenants')->value('id');

        if (! $tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'nombre' => 'Tenant por defecto',
                'slug' => 'tenant-por-defecto',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
