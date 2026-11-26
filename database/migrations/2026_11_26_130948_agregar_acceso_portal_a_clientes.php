<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acceso del cliente al portal (ver PortalAuthController): su usuario (único en toda la
     * plataforma, para saber de qué laboratorio es al iniciar sesión), su contraseña y si el
     * laboratorio le tiene habilitado el acceso.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'portal_usuario')) {
                $table->string('portal_usuario', 100)->nullable()->unique()->after('direccion');
            }

            if (! Schema::hasColumn('clientes', 'portal_password')) {
                $table->string('portal_password')->nullable()->after('portal_usuario');
            }

            if (! Schema::hasColumn('clientes', 'portal_activo')) {
                $table->boolean('portal_activo')->default(false)->after('portal_password');
            }

            if (! Schema::hasColumn('clientes', 'portal_ultimo_acceso')) {
                $table->timestamp('portal_ultimo_acceso')->nullable()->after('portal_activo');
            }

            if (! Schema::hasColumn('clientes', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['portal_usuario']);
            $table->dropColumn(['portal_usuario', 'portal_password', 'portal_activo', 'portal_ultimo_acceso', 'remember_token']);
        });
    }
};
