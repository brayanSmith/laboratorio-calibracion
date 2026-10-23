<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los laboratorios dejan de ser una tabla propia:
     *
     * - El laboratorio principal (el propio, donde se hacen las calibraciones locales) pasa a
     *   la configuración de la Empresa del tenant.
     * - Los demás laboratorios son externos: se fusionan con la Empresa tercera del mismo
     *   nombre (completando los datos que le falten) o se crean como Empresa tercera.
     * - Cada calibración guarda como texto el lugar donde se hizo, para que los certificados
     *   ya emitidos no cambien.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('laboratorio_nombre')->nullable()->after('telefono');
            $table->string('laboratorio_direccion')->nullable()->after('laboratorio_nombre');
            $table->string('laboratorio_email')->nullable()->after('laboratorio_direccion');
            $table->string('laboratorio_telefono')->nullable()->after('laboratorio_email');
        });

        Schema::table('calibracions', function (Blueprint $table) {
            $table->string('lugar_calibracion')->nullable()->after('fecha_calibracion');
        });

        $laboratorios = DB::table('laboratorios')->get();

        foreach ($laboratorios as $laboratorio) {
            DB::table('calibracions')
                ->where('laboratorio_id', $laboratorio->id)
                ->update(['lugar_calibracion' => $laboratorio->nombre]);

            if ($laboratorio->deleted_at !== null) {
                continue;
            }

            if ($laboratorio->principal) {
                DB::table('empresas')->where('tenant_id', $laboratorio->tenant_id)->update([
                    'laboratorio_nombre' => $laboratorio->nombre,
                    'laboratorio_direccion' => $laboratorio->direccion,
                    'laboratorio_email' => $laboratorio->email,
                    'laboratorio_telefono' => $laboratorio->telefono,
                ]);

                continue;
            }

            $this->fusionarConEmpresaTercera($laboratorio);
        }

        Schema::table('calibracions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('laboratorio_id');
        });

        Schema::dropIfExists('laboratorios');

        $this->eliminarPermisos();
    }

    public function down(): void
    {
        Schema::create('laboratorios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->string('direccion')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->boolean('principal')->default(false);
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('calibracions', function (Blueprint $table) {
            $table->foreignId('laboratorio_id')->nullable()->after('fecha_calibracion')->constrained('laboratorios')->onDelete('cascade');
        });

        // Solo se puede reconstruir el laboratorio principal; los externos quedan en Empresas terceras.
        DB::table('empresas')->whereNotNull('laboratorio_nombre')->get()->each(function (object $empresa): void {
            $laboratorioId = DB::table('laboratorios')->insertGetId([
                'nombre' => $empresa->laboratorio_nombre,
                'direccion' => $empresa->laboratorio_direccion,
                'email' => $empresa->laboratorio_email,
                'telefono' => $empresa->laboratorio_telefono,
                'principal' => true,
                'tenant_id' => $empresa->tenant_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('calibracions')
                ->where('tenant_id', $empresa->tenant_id)
                ->where('lugar_calibracion', $empresa->laboratorio_nombre)
                ->update(['laboratorio_id' => $laboratorioId]);
        });

        Schema::table('calibracions', function (Blueprint $table) {
            $table->dropColumn('lugar_calibracion');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['laboratorio_nombre', 'laboratorio_direccion', 'laboratorio_email', 'laboratorio_telefono']);
        });
    }

    /**
     * Completa la Empresa tercera del mismo nombre con los datos del laboratorio, o la crea.
     * El NIT es obligatorio en Empresas terceras: si se crea, queda uno provisional para editar.
     */
    private function fusionarConEmpresaTercera(object $laboratorio): void
    {
        $empresaTercera = DB::table('empresa_terceros')
            ->where('tenant_id', $laboratorio->tenant_id)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($laboratorio->nombre))])
            ->first();

        if ($empresaTercera) {
            DB::table('empresa_terceros')->where('id', $empresaTercera->id)->update([
                'direccion' => $empresaTercera->direccion ?? $laboratorio->direccion,
                'email' => $empresaTercera->email ?? $laboratorio->email,
                'telefono' => $empresaTercera->telefono ?? $laboratorio->telefono,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('empresa_terceros')->insert([
            'nombre' => $laboratorio->nombre,
            'nit' => "PENDIENTE-LAB-{$laboratorio->id}",
            'direccion' => $laboratorio->direccion,
            'email' => $laboratorio->email,
            'telefono' => $laboratorio->telefono,
            'tenant_id' => $laboratorio->tenant_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Quita los permisos del módulo Laboratorios y el de importarlos (también de los roles).
     */
    private function eliminarPermisos(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $nombres = [
            'laboratorios.ver',
            'laboratorios.crear',
            'laboratorios.editar',
            'laboratorios.eliminar',
            'importaciones.laboratorios',
        ];

        $ids = DB::table('permissions')->whereIn('name', $nombres)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
