<?php

namespace App\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Series por mes para los gráficos de los indicadores del dashboard: un punto por cada mes
 * del periodo, aunque no tenga datos, para que el eje no salte meses.
 */
trait SerieMensual
{
    /**
     * Meses del periodo en formato AAAA-MM, del primero al último.
     *
     * @return list<string>
     */
    protected function mesesDelPeriodo(string $desde, string $hasta): array
    {
        $mes = Carbon::parse($desde)->startOfMonth();
        $ultimo = Carbon::parse($hasta)->startOfMonth();
        $meses = [];

        while ($mes->lte($ultimo)) {
            $meses[] = $mes->format('Y-m');
            $mes->addMonth();
        }

        return $meses;
    }

    /**
     * Un punto por mes del periodo con lo que calcula `$punto` sobre las filas de ese mes
     * (una colección vacía si el mes no tuvo nada).
     *
     * @template TFila
     *
     * @param  Collection<int, TFila>  $filas
     * @param  callable(TFila): string  $mesDeFila  mes AAAA-MM de cada fila
     * @param  callable(Collection<int, TFila>): array<string, mixed>  $punto
     * @return list<array<string, mixed>>
     */
    protected function serieMensual(string $desde, string $hasta, Collection $filas, callable $mesDeFila, callable $punto): array
    {
        $porMes = $filas->groupBy($mesDeFila);

        return array_map(
            fn (string $mes): array => ['mes' => $mes, ...$punto($porMes->get($mes, collect()))],
            $this->mesesDelPeriodo($desde, $hasta),
        );
    }
}
