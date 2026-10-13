<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltraListados
{
    /**
     * Filtros por columna de la solicitud (`filtro[columna]=valor`), solo los de las columnas
     * permitidas y con valor.
     *
     * @param  list<string>  $permitidos
     * @return array<string, string>
     */
    protected function filtrosDe(Request $request, array $permitidos): array
    {
        $recibidos = $request->query('filtro', []);

        if (! is_array($recibidos)) {
            return [];
        }

        $filtros = [];

        foreach ($permitidos as $columna) {
            $valor = $recibidos[$columna] ?? null;

            if (is_string($valor) && trim($valor) !== '') {
                $filtros[$columna] = trim($valor);
            }
        }

        return $filtros;
    }

    /**
     * Restringe la consulta a las filas cuya columna contiene el texto, sin interpretar los
     * comodines % y _ que escriba la persona.
     *
     * @param  Builder<*>  $consulta
     */
    protected function whereContiene(Builder $consulta, string $columna, string $texto): void
    {
        $escapado = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto);
        $columna = $consulta->getQuery()->getGrammar()->wrap($consulta->getModel()->getTable().'.'.$columna);

        $consulta->whereRaw("{$columna} LIKE ? ESCAPE '!'", ["%{$escapado}%"]);
    }
}
