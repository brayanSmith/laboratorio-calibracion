<?php

use App\Models\VersionDocumento;
use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada PDF que se emite de un mantenimiento (informe) o de una calibración (certificado):
     * el original y las correcciones (-A, -B, ...), para poder consultar las anteriores. Los
     * PDF que ya existían quedan como la versión original.
     */
    public function up(): void
    {
        Schema::create('version_documentos', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->unsignedInteger('numero'); // 0 = original, 1 = -A, 2 = -B, ...
            $table->string('codigo', 30);
            $table->string('ruta');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id', 'numero']);
        });

        foreach ([
            [Mantenimiento::class, 'mantenimientos', 'pdf_informe'],
            [Calibracion::class, 'calibracions', 'pdf_certificado'],
        ] as [$clase, $tabla, $columna]) {
            DB::table($tabla)->whereNotNull($columna)->orderBy('id')->get(['id', $columna, 'tenant_id', 'updated_at'])
                ->each(fn (object $documento) => DB::table('version_documentos')->insert([
                    'documentable_type' => $clase,
                    'documentable_id' => $documento->id,
                    'numero' => 0,
                    'codigo' => VersionDocumento::codigoBase($clase, $documento->id),
                    'ruta' => $documento->{$columna},
                    'tenant_id' => $documento->tenant_id,
                    'created_at' => $documento->updated_at,
                    'updated_at' => $documento->updated_at,
                ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('version_documentos');
    }
};
