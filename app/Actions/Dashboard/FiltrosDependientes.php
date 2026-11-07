<?php

namespace App\Actions\Dashboard;

use Illuminate\Support\Collection;

/**
 * Filtros de selección múltiple que dependen unos de otros, en orden (ej. laboratorio →
 * técnico → magnitud → tipo de equipo). Cada lista ofrece solo lo que tienen las filas que
 * cumplen los filtros anteriores, y una fila pasa un filtro si tiene alguno de los valores
 * elegidos (una lista vacía no filtra).
 *
 * Cada fila trae en `filtros` los valores que tiene para cada filtro, como listas de
 * opciones `{id, nombre}` (vacía si no tiene ninguno; ej. un equipo sin magnitud).
 */
class FiltrosDependientes
{
    /** Id del laboratorio propio: va primero en su lista. */
    public const PROPIO = 'propio';

    /**
     * @param  Collection<int, array{filtros: array<string, list<array{id: int|string, nombre: string}>>}>  $filas
     * @param  array<string, list<int|string>>  $seleccion  valores elegidos por filtro, en el orden de dependencia
     * @return array{filas: Collection<int, array<string, mixed>>, opciones: array<string, list<array{id: int|string, nombre: string}>>}
     */
    public static function aplicar(Collection $filas, array $seleccion): array
    {
        $opciones = [];

        foreach ($seleccion as $filtro => $elegidos) {
            // La lista de este filtro sale de lo que dejaron los anteriores.
            $opciones[$filtro] = self::opciones($filas, $filtro);

            if ($elegidos !== []) {
                $filas = $filas->filter(fn (array $fila): bool => array_intersect(
                    array_column($fila['filtros'][$filtro], 'id'),
                    $elegidos,
                ) !== []);
            }
        }

        return ['filas' => $filas->values(), 'opciones' => $opciones];
    }

    /**
     * Opciones únicas de un filtro entre las filas, ordenadas por nombre (el laboratorio
     * propio primero).
     *
     * @param  Collection<int, array{filtros: array<string, list<array{id: int|string, nombre: string}>>}>  $filas
     * @return list<array{id: int|string, nombre: string}>
     */
    private static function opciones(Collection $filas, string $filtro): array
    {
        return $filas
            ->flatMap(fn (array $fila): array => $fila['filtros'][$filtro])
            ->unique('id')
            ->sortBy(fn (array $opcion): string => ($opcion['id'] === self::PROPIO ? '0' : '1').$opcion['nombre'])
            ->values()
            ->all();
    }

    /**
     * Una opción `{id, nombre}` de un modelo, o ninguna si no lo hay (para `filtros`).
     *
     * @return list<array{id: int|string, nombre: string}>
     */
    public static function opcion(?object $modelo, string $campoNombre = 'nombre'): array
    {
        return $modelo ? [['id' => $modelo->id, 'nombre' => $modelo->{$campoNombre}]] : [];
    }
}
