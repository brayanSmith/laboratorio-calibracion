<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tipos de documento de Colombia a los de Perú (ver TipoDocumentoCliente): el NIT pasa a
     * código SAP, la cédula de ciudadanía a DNI, y la cédula de extranjería (CE) se conserva
     * como carnet de extranjería, igual que el pasaporte.
     *
     * @var array<string, string>
     */
    private const CAMBIOS = [
        'NIT' => 'SAP',
        'CC' => 'DNI',
        'PASAPORTE' => 'CE',
    ];

    public function up(): void
    {
        foreach (self::CAMBIOS as $anterior => $nuevo) {
            DB::table('clientes')->where('tipo_documento', $anterior)->update(['tipo_documento' => $nuevo]);
        }
    }

    public function down(): void
    {
        DB::table('clientes')->where('tipo_documento', 'SAP')->update(['tipo_documento' => 'NIT']);
        DB::table('clientes')->where('tipo_documento', 'DNI')->update(['tipo_documento' => 'CC']);
    }
};
