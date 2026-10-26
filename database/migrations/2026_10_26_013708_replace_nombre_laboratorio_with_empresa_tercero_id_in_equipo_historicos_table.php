<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El histórico deja de guardar el laboratorio como texto: si lo hizo un tercero, apunta a
     * la Empresa tercera; si fue el laboratorio propio, queda sin empresa. Los históricos de
     * terceros existentes se asocian a la Empresa tercera del mismo nombre, o se crea una con
     * un NIT provisional para editar.
     */
    public function up(): void
    {
        Schema::table('equipo_historicos', function (Blueprint $table) {
            $table->foreignId('empresa_tercero_id')->nullable()->after('es_tercero')->constrained('empresa_terceros')->nullOnDelete();
        });

        DB::table('equipo_historicos')->where('es_tercero', true)->orderBy('id')->get()->each(function (object $historico): void {
            DB::table('equipo_historicos')->where('id', $historico->id)->update([
                'empresa_tercero_id' => $this->empresaTerceraId($historico),
            ]);
        });

        Schema::table('equipo_historicos', function (Blueprint $table) {
            $table->dropColumn('nombre_laboratorio');
        });
    }

    public function down(): void
    {
        Schema::table('equipo_historicos', function (Blueprint $table) {
            $table->string('nombre_laboratorio')->nullable()->after('fecha_servicio');
        });

        DB::table('equipo_historicos')->orderBy('id')->get()->each(function (object $historico): void {
            $nombre = $historico->empresa_tercero_id === null
                ? DB::table('empresas')->where('tenant_id', $historico->tenant_id)->value('laboratorio_nombre')
                : DB::table('empresa_terceros')->where('id', $historico->empresa_tercero_id)->value('nombre');

            DB::table('equipo_historicos')->where('id', $historico->id)->update([
                'nombre_laboratorio' => $nombre ?? 'Laboratorio propio',
            ]);
        });

        Schema::table('equipo_historicos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_tercero_id');
        });
    }

    /**
     * Id de la Empresa tercera del tenant con el nombre del laboratorio del histórico; si no
     * existe, la crea.
     */
    private function empresaTerceraId(object $historico): int
    {
        $empresaTerceraId = DB::table('empresa_terceros')
            ->where('tenant_id', $historico->tenant_id)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($historico->nombre_laboratorio))])
            ->value('id');

        return $empresaTerceraId ?? DB::table('empresa_terceros')->insertGetId([
            'nombre' => trim($historico->nombre_laboratorio),
            'nit' => "PENDIENTE-HIST-{$historico->id}",
            'tenant_id' => $historico->tenant_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
