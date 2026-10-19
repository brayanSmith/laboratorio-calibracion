<?php

namespace App\Modules\Calibraciones\Actions;

use App\Modules\Calibraciones\Models\Calibracion;

class GraficoErrorCalibracion
{
    private const ANCHO = 640;

    private const ALTO = 220;

    private const MARGEN_IZQUIERDO = 60;

    private const MARGEN_DERECHO = 20;

    private const MARGEN_SUPERIOR = 16;

    private const MARGEN_INFERIOR = 62;

    /**
     * Gráfico de líneas (SVG), con cuadrícula, de tres series frente al valor del instrumento de
     * cada punto: EMP + (el mayor EMP de los detalles, en positivo, igual en todos los puntos),
     * EMP - (ese mismo EMP máximo en negativo) y Error (el error_encontrado). Entre EMP + y EMP -
     * se colorea el área del rango permitido: verde si todos los errores están dentro del EMP,
     * roja si alguno lo supera. Todas en la unidad de medida del punto. Los puntos que todavía no
     * tienen valor del instrumento no se pueden ubicar en el eje horizontal y se omiten. Devuelve
     * null si no hay ninguno.
     */
    public function handle(Calibracion $calibracion): ?string
    {
        $puntos = $calibracion->detalleMedicionCalibracion
            ->filter(fn ($detalle) => $detalle->valor_instrumento !== null
                && $detalle->error_encontrado !== null
                && $detalle->emp !== null)
            ->sortBy(fn ($detalle) => (float) $detalle->valor_instrumento)
            ->values();

        if ($puntos->isEmpty()) {
            return null;
        }

        $x = $puntos->map(fn ($d) => (float) $d->valor_instrumento)->all();
        $error = $puntos->map(fn ($d) => (float) $d->error_encontrado)->all();
        $empMaximo = (float) $puntos->max(fn ($d) => (float) $d->emp);
        $empPositivo = array_fill(0, count($x), $empMaximo);
        $empNegativo = array_fill(0, count($x), -$empMaximo);

        [$minY, $maxY] = $this->rangoVertical([...$error, ...$empPositivo, ...$empNegativo]);
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

        // Verde mientras todos los errores estén dentro del EMP de su punto; rojo si alguno lo supera.
        $aprobado = $puntos->every(fn ($d) => (float) $d->error_encontrado <= (float) $d->emp);
        $colorLimite = $aprobado ? '#16a34a' : '#dc2626';
        $colorError = '#2563eb';
        $fuente = "font-family=\"'DejaVu Sans', sans-serif\"";

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::ANCHO.'" height="'.self::ALTO.'" viewBox="0 0 '.self::ANCHO.' '.self::ALTO.'" '.$fuente.'>';
        $svg .= '<rect x="0.5" y="0.5" width="'.(self::ANCHO - 1).'" height="'.(self::ALTO - 1).'" fill="#ffffff" stroke="#e5e5e5" rx="6"/>';

        // Área del rango permitido, entre EMP - y EMP +.
        $arribaRango = round($py($empMaximo), 2);
        $svg .= '<rect x="'.$izquierda.'" y="'.$arribaRango.'" width="'.($derecha - $izquierda).'" height="'.round($py(-$empMaximo) - $arribaRango, 2).'" fill="'.$colorLimite.'" fill-opacity="0.12"/>';

        // Cuadrícula horizontal suave y etiquetas del eje vertical.
        $pasos = 6;
        for ($i = 0; $i <= $pasos; $i++) {
            $valor = $minY + ($maxY - $minY) * $i / $pasos;
            $y = round($py($valor), 2);
            $svg .= '<line x1="'.$izquierda.'" y1="'.$y.'" x2="'.$derecha.'" y2="'.$y.'" stroke="#e5e5e5" stroke-width="0.6"/>';
            $svg .= '<text x="'.($izquierda - 8).'" y="'.($y + 3).'" font-size="8" fill="#737373" text-anchor="end">'.$this->numero($valor).'</text>';
        }

        // Cuadrícula vertical: una por cada punto de medición.
        foreach ($x as $valor) {
            $posicion = round($px($valor), 2);
            $svg .= '<line x1="'.$posicion.'" y1="'.$arriba.'" x2="'.$posicion.'" y2="'.$abajo.'" stroke="#e5e5e5" stroke-width="0.6"/>';
        }

        // Línea del cero, un poco más marcada.
        $svg .= '<line x1="'.$izquierda.'" y1="'.round($py(0), 2).'" x2="'.$derecha.'" y2="'.round($py(0), 2).'" stroke="#a3a3a3" stroke-width="0.9"/>';

        // Etiquetas del eje horizontal: un valor por punto de medición.
        foreach ($x as $valor) {
            $posicion = round($px($valor), 2);
            $svg .= '<line x1="'.$posicion.'" y1="'.$abajo.'" x2="'.$posicion.'" y2="'.($abajo + 4).'" stroke="#a3a3a3" stroke-width="0.8"/>';
            $svg .= '<text x="'.$posicion.'" y="'.($abajo + 15).'" font-size="8" fill="#737373" text-anchor="middle">'.$this->numero($valor).'</text>';
        }

        $svg .= '<line x1="'.$izquierda.'" y1="'.$abajo.'" x2="'.$derecha.'" y2="'.$abajo.'" stroke="#a3a3a3" stroke-width="0.9"/>';

        // EMP + y EMP -: líneas punteadas de borde a borde, que delimitan el rango.
        foreach ([$empMaximo, -$empMaximo] as $limite) {
            $y = round($py($limite), 2);
            $svg .= '<line x1="'.$izquierda.'" y1="'.$y.'" x2="'.$derecha.'" y2="'.$y.'" stroke="'.$colorLimite.'" stroke-width="1.3" stroke-dasharray="5,3"/>';
        }

        $svg .= $this->serie($x, $error, $px, $py, $colorError);

        // Valor del error sobre cada punto.
        foreach ($x as $i => $valor) {
            $svg .= '<text x="'.round($px($valor), 2).'" y="'.(round($py($error[$i]), 2) - 8).'" font-size="7.5" fill="'.$colorError.'" text-anchor="middle">'.$this->numero($error[$i]).'</text>';
        }

        $svg .= '<text x="'.(($izquierda + $derecha) / 2).'" y="'.($abajo + 31).'" font-size="9" fill="#525252" text-anchor="middle">Valor instrumento</text>';
        $svg .= '<text x="14" y="'.(($arriba + $abajo) / 2).'" font-size="9" fill="#525252" text-anchor="middle" transform="rotate(-90 14 '.(($arriba + $abajo) / 2).')">Error / EMP</text>';

        // Leyenda con muestras de línea.
        $leyenda = [['EMP +', $colorLimite, '5,3'], ['Error', $colorError, null], ['EMP -', $colorLimite, '5,3']];
        $xLeyenda = ($izquierda + $derecha) / 2 - 120;
        foreach ($leyenda as [$texto, $color, $trazo]) {
            $svg .= '<line x1="'.$xLeyenda.'" y1="'.($abajo + 46).'" x2="'.($xLeyenda + 18).'" y2="'.($abajo + 46).'" stroke="'.$color.'" stroke-width="1.6"'.($trazo ? ' stroke-dasharray="'.$trazo.'"' : '').'/>';
            $svg .= '<text x="'.($xLeyenda + 24).'" y="'.($abajo + 49).'" font-size="8" fill="#525252">'.$texto.'</text>';
            $xLeyenda += 85;
        }

        return $svg.'</svg>';
    }

    /**
     * Línea de datos con sus puntos (círculos con borde de color y centro blanco).
     *
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    private function serie(array $x, array $y, callable $px, callable $py, string $color): string
    {
        $coordenadas = [];

        foreach ($x as $i => $valor) {
            $coordenadas[] = round($px($valor), 2).','.round($py($y[$i]), 2);
        }

        $svg = count($coordenadas) > 1
            ? '<polyline points="'.implode(' ', $coordenadas).'" fill="none" stroke="'.$color.'" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/>'
            : '';

        foreach ($coordenadas as $par) {
            [$cx, $cy] = explode(',', $par);
            $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="3.2" fill="#ffffff" stroke="'.$color.'" stroke-width="1.8"/>';
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
