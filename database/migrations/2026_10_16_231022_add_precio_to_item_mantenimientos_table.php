<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * El precio se guarda en el uso (no se lee del ítem) para que no cambie si luego se
     * actualiza el precio del ítem. Los usos ya registrados toman el precio actual del ítem.
     */
    public function up(): void
    {
        Schema::table('item_mantenimientos', function (Blueprint $table) {
            $table->decimal('precio_unitario', 10, 2)->default(0)->after('con_stock');
            $table->decimal('subtotal', 12, 2)->default(0)->after('precio_unitario');
        });

        DB::table('item_mantenimientos')
            ->join('items', 'items.id', '=', 'item_mantenimientos.item_id')
            ->select('item_mantenimientos.id', 'item_mantenimientos.cantidad', 'items.precio_unitario')
            ->orderBy('item_mantenimientos.id')
            ->each(function (object $uso): void {
                $precio = (float) ($uso->precio_unitario ?? 0);

                DB::table('item_mantenimientos')->where('id', $uso->id)->update([
                    'precio_unitario' => $precio,
                    'subtotal' => round((float) $uso->cantidad * $precio, 2),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_mantenimientos', function (Blueprint $table) {
            $table->dropColumn(['precio_unitario', 'subtotal']);
        });
    }
};
