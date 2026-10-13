<?php

namespace App\Actions\Calibraciones;

use App\Models\Calibracion;

class GraficoErrorCalibracion
{
    private const ANCHO = 640;

    private const ALTO = 300;

    private const MARGEN_IZQUIERDO = 60;

    private const MARGEN_DERECHO = 20;

    private const MARGEN_SUPERIOR = 16;

    private const MARGEN_INFERIOR = 62;

    /**
     * Gráfico de líneas (SVG), con cuadrícula, del error máximo permitido en cada punto de medición, como
     * porcentaje (emp_porcentaje, emp_porcentaje_positivo y emp_porcentaje_negativo) frente
     * al valor del instrumento de cada punto. Los puntos que todavía no tienen valor del
     * instrumento no se pueden ubicar en el eje horizontal y se omiten. Devuelve null si no
     * hay ninguno.
     */
    public function handle(Calibracion $calibracion): ?string
    {
        $puntos = $calibracion->detalleMedicionCalibracion
            ->filter(fn ($detalle) => $detalle->valor_instrumento !== null
                && $detalle->emp_porcentaje !== null
                && $detalle->emp_porcentaje_positivo !== null
                && $detalle->emp_porcentaje_negativo !== null)
            ->sortBy(fn ($detalle) => (float) $detalle->valor_instrumento)
            ->values();

        if ($puntos->isEmpty()) {
            return null;
        }

        $x = $puntos->map(fn ($d) => (float) $d->valor_instrumento)->all();
        // Los EMP porcentuales se guardan como fracción (EMP / valor de referencia, ej. 0.05);
        // el gráfico los muestra como porcentaje (5%).
        $empPorcentaje = $puntos->map(fn ($d) => (float) $d->emp_porcentaje * 100)->all();
        $empPositivo = $puntos->map(fn ($d) => (float) $d->emp_porcentaje_positivo * 100)->all();
        $empNegativo = $puntos->map(fn ($d) => (float) $d->emp_porcentaje_negativo * 100)->all();

        [$minY, $maxY] = $this->rangoVertical([...$empPorcentaje, ...$empPositivo, ...$empNegativo]);
        $minX = min($x);
        $maxX = max($x);

        $izquierda = self::MARGEN_IZQUIERDO;
        $derecha = self::ANCHO - self::MARGEN_DERECHO;
        $arriba = self::MARGEN_SUPERIOR;
        $abajo = self::ALTO - self::MARGEN_INFERIOR;

        $px = fn (float $valor): float => $maxX === $minX
            ? ($izquierda + $derecha) / 2
            : $izquierda + ($valor - $minX) / ($maxX - $minX) * ($derecha - $izquierda);
        $py = fn (float $valor): float => $abajo - ($valor - $minY) / ($maxY - $minY) * ($abajo - $arriba);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::ANCHO.'" height="'.self::ALTO.'" viewBox="0 0 '.self::ANCHO.' '.self::ALTO.'">';
        $svg .= '<rect x="0" y="0" width="'.self::ANCHO.'" height="'.self::ALTO.'" fill="#ececec"/>';
        $svg .= '<rect x="'.$izquierda.'" y="'.$arriba.'" width="'.($derecha - $izquierda).'" height="'.($abajo - $arriba).'" fill="#f3f4f6"/>';

        // Cuadrícula horizontal y etiquetas del eje vertical.
        $pasos = 6;
        for ($i = 0; $i <= $pasos; $i++) {
            $valor = $minY + ($maxY - $minY) * $i / $pasos;
            $y = round($py($valor), 2);
            $svg .= '<line x1="'.$izquierda.'" y1="'.$y.'" x2="'.$derecha.'" y2="'.$y.'" stroke="#d1d5db" stroke-width="0.6"/>';
            $svg .= '<text x="'.($izquierda - 6).'" y="'.($y + 3).'" font-size="8" fill="#4b5563" text-anchor="end">'.$this->numero($valor).'%</text>';
        }

        // Cuadrícula vertical y etiquetas del eje horizontal: un valor por punto de medición.
        foreach ($x as $valor) {
            $posicion = round($px($valor), 2);
            $svg .= '<line x1="'.$posicion.'" y1="'.$arriba.'" x2="'.$posicion.'" y2="'.$abajo.'" stroke="#d1d5db" stroke-width="0.6"/>';
            $svg .= '<line x1="'.$posicion.'" y1="'.$abajo.'" x2="'.$posicion.'" y2="'.($abajo + 4).'" stroke="#6b7280" stroke-width="0.8"/>';
            $svg .= '<text x="'.$posicion.'" y="'.($abajo + 14).'" font-size="8" fill="#4b5563" text-anchor="middle">'.$this->numero($valor).'</text>';
        }

        $svg .= '<line x1="'.$izquierda.'" y1="'.$abajo.'" x2="'.$derecha.'" y2="'.$abajo.'" stroke="#6b7280" stroke-width="1"/>';
        $svg .= '<line x1="'.$izquierda.'" y1="'.$arriba.'" x2="'.$izquierda.'" y2="'.$abajo.'" stroke="#6b7280" stroke-width="1"/>';

        $svg .= $this->serie($x, $empPositivo, $px, $py, '#111827', false);
        $svg .= $this->serie($x, $empNegativo, $px, $py, '#111827', false, '5,3');
        $svg .= $this->serie($x, $empPorcentaje, $px, $py, '#005aa7', true);

        $svg .= '<text x="'.(($izquierda + $derecha) / 2).'" y="'.($abajo + 30).'" font-size="9" fill="#374151" text-anchor="middle">Valor instrumento</text>';
        $svg .= '<text x="14" y="'.(($arriba + $abajo) / 2).'" font-size="9" fill="#374151" text-anchor="middle" transform="rotate(-90 14 '.(($arriba + $abajo) / 2).')">EMP (%)</text>';

        // Leyenda.
        $leyenda = [['EMP (+)', '#111827'], ['EMP %', '#005aa7'], ['EMP (-)', '#111827']];
        $xLeyenda = ($izquierda + $derecha) / 2 - 130;
        foreach ($leyenda as [$texto, $color]) {
            $svg .= '<rect x="'.$xLeyenda.'" y="'.($abajo + 40).'" width="14" height="6" fill="'.$color.'"'.($texto === 'EMP (-)' ? ' fill-opacity="0.55"' : '').'/>';
            $svg .= '<text x="'.($xLeyenda + 20).'" y="'.($abajo + 46).'" font-size="8" fill="#374151">'.$texto.'</text>';
            $xLeyenda += 90;
        }

        return $svg.'</svg>';
    }

    /**
     * Una línea de datos (con puntos si se pide).
     *
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    private function serie(array $x, array $y, callable $px, callable $py, string $color, bool $conPuntos, ?string $trazo = null): string
    {
        $coordenadas = [];

        foreach ($x as $i => $valor) {
            $coordenadas[] = round($px($valor), 2).','.round($py($y[$i]), 2);
        }

        $svg = count($coordenadas) > 1
            ? '<polyline points="'.implode(' ', $coordenadas).'" fill="none" stroke="'.$color.'" stroke-width="1.6"'.($trazo ? ' stroke-dasharray="'.$trazo.'"' : '').'/>'
            : '';

        if ($conPuntos || count($coordenadas) === 1) {
            foreach ($coordenadas as $par) {
                [$cx, $cy] = explode(',', $par);
                $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="3" fill="'.$color.'"/>';
            }
        }

        return $svg;
    }

    /**
     * Rango del eje vertical con un poco de margen, siempre incluyendo el cero.
     *
     * @param  list<float>  $valores
     * @return array{0: float, 1: float}
     */
    private function rangoVertical(array $valores): array
    {
        $min = min(0.0, ...$valores);
        $max = max(0.0, ...$valores);

        if ($max === $min) {
            return [-1.0, 1.0];
        }

        $holgura = ($max - $min) * 0.1;

        return [$min - $holgura, $max + $holgura];
    }

    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.') ?: '0';
    }
}
