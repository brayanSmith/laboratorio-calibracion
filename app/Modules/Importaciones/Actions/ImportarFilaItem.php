<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Inventario\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Crea un ítem del inventario a partir de una fila del CSV. Si la fila trae una cantidad,
 * se registra como una entrada ya recibida (stock inicial), con su código de compra.
 */
class ImportarFilaItem extends ImportadorFila
{
    public const OBSERVACION_STOCK_INICIAL = 'Stock inicial (importación)';

    /** Datos propios del ítem que la fila puede reemplazar (la cantidad y el código de compra van a la entrada). */
    private const CAMPOS_DEL_ITEM = ['nombre', 'fabricante', 'numero_parte', 'descripcion', 'ubicacion', 'precio_unitario'];

    public static function campos(): array
    {
        return [
            self::campo('codigo', 'Código', true, 'Único: si ya existe un ítem con este código, antes de importar eliges si reemplazarlo u omitirlo.'),
            // No se exige relacionar la columna: al reemplazar ítems puede venir solo lo que cambia.
            self::campo('nombre', 'Nombre', false, 'Obligatorio en los ítems nuevos. Al reemplazar, si viene vacío se conserva el actual.'),
            self::campo('fabricante', 'Fabricante'),
            self::campo('numero_parte', 'N/P (número de parte)'),
            self::campo('descripcion', 'Descripción'),
            self::campo('ubicacion', 'Ubicación'),
            self::campo('precio_unitario', 'Precio unitario', false, 'Número, con coma o punto decimal (ej. 12500,50).'),
            self::campo('cantidad', 'Cantidad', false, 'Stock inicial: se registra como una entrada ya recibida.'),
            self::campo('codigo_compra', 'Código de compra', false, 'Código de compra del stock inicial (solo si hay cantidad).'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'codigo' => 'REP-0001',
            'nombre' => 'Empaque de nitrilo',
            'fabricante' => 'Parker',
            'numero_parte' => 'NP-2-012',
            'descripcion' => 'Fila de ejemplo: bórrala antes de importar',
            'ubicacion' => 'Estante A1',
            'precio_unitario' => '12500,50',
            'cantidad' => '10',
            'codigo_compra' => 'OC-2026-001',
        ];
    }

    public static function nota(): ?string
    {
        return 'La cantidad se registra como el stock inicial del ítem (una entrada ya recibida, con su código de compra). Después el stock se mueve con las entradas y lo usado en los mantenimientos.';
    }

    public static function campoExistente(): ?string
    {
        return 'codigo';
    }

    public function existentes(array $valores): array
    {
        $codigos = array_values(array_unique(array_filter(array_map(trim(...), $valores), fn (string $codigo): bool => $codigo !== '')));

        return collect($codigos)
            ->chunk(500)
            ->flatMap(fn ($tanda) => Item::query()
                ->where('tenant_id', $this->tenantId)
                ->whereIn('codigo', $tanda->all())
                ->pluck('codigo'))
            ->values()
            ->all();
    }

    public function importar(array $valores): Item
    {
        $datos = $this->limpiar($valores);

        foreach (['precio_unitario', 'cantidad'] as $campo) {
            if ($datos[$campo] !== null) {
                $datos[$campo] = self::numero($datos[$campo]);
            }
        }

        $existente = $this->reemplazarExistentes && $datos['codigo'] !== null
            ? Item::query()->where('tenant_id', $this->tenantId)->where('codigo', $datos['codigo'])->first()
            : null;

        // Al reemplazar solo cambia lo que trae la fila: lo vacío (o sin columna) conserva el dato guardado.
        if ($existente !== null) {
            foreach (self::CAMPOS_DEL_ITEM as $campo) {
                $datos[$campo] ??= $existente->getRawOriginal($campo);
            }
        }

        // Mismas reglas que los formularios de ítems y de entradas (ver StoreItemRequest y StoreEntradaItemRequest).
        $datos = $this->validar($datos, [
            'codigo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('items', 'codigo')->where('tenant_id', $this->tenantId)->ignore($existente?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'fabricante' => ['nullable', 'string', 'max:255'],
            'numero_parte' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cantidad' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'codigo_compra' => ['nullable', 'string', 'max:255'],
        ], [
            'codigo.unique' => 'Ya existe un ítem con el código :input.',
        ]);

        $cantidad = $datos['cantidad'] ?? null;
        $codigoCompra = $datos['codigo_compra'] ?? null;
        unset($datos['cantidad'], $datos['codigo_compra']);

        return DB::transaction(function () use ($existente, $datos, $cantidad, $codigoCompra): Item {
            $existente?->update($datos);

            $item = $existente ?? Item::create([...$datos, 'tenant_id' => $this->tenantId]);

            if ($cantidad !== null) {
                $this->registrarStockInicial($item, (float) $cantidad, $codigoCompra);
            }

            return $item;
        });
    }

    /**
     * Registra la cantidad como una entrada ya recibida. Al reemplazar un ítem, corrige la
     * entrada de stock inicial que dejó una importación anterior en vez de sumar otra, para
     * que volver a importar el mismo archivo no duplique el stock.
     */
    private function registrarStockInicial(Item $item, float $cantidad, ?string $codigoCompra): void
    {
        $precioUnitario = (float) ($item->precio_unitario ?? 0);
        $datos = [
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'precio_total' => round($cantidad * $precioUnitario, 2),
            'recibido' => true,
            'observaciones' => self::OBSERVACION_STOCK_INICIAL,
            'tenant_id' => $this->tenantId,
        ];

        $anterior = $item->wasRecentlyCreated
            ? null
            : $item->entradas()->where('observaciones', self::OBSERVACION_STOCK_INICIAL)->latest('id')->first();

        if ($anterior !== null) {
            $anterior->update([...$datos, 'codigo_compra' => $codigoCompra ?? $anterior->codigo_compra]);

            return;
        }

        $item->entradas()->create([...$datos, 'codigo_compra' => $codigoCompra, 'fecha_recibido' => now()->toDateString()]);
    }
}
