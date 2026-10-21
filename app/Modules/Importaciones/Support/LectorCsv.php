<?php

namespace App\Modules\Importaciones\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

/**
 * Lee los CSV de importación fila por fila, sin cargarlos completos en memoria.
 *
 * Detecta el separador (Excel en español guarda con ";") y la codificación (Excel en Windows
 * guarda en Windows-1252, no en UTF-8) para que las tildes y columnas lleguen bien.
 */
class LectorCsv
{
    private const BYTES_MUESTRA = 1048576;

    public function __construct(
        private readonly string $ruta,
        private readonly string $delimitador,
        private readonly string $codificacion,
    ) {}

    /**
     * Abre el archivo detectando su separador y codificación.
     */
    public static function detectar(string $ruta): self
    {
        $muestra = (string) file_get_contents($ruta, length: self::BYTES_MUESTRA);

        return new self($ruta, self::detectarDelimitador($muestra), self::detectarCodificacion($muestra));
    }

    /**
     * Copia a un archivo temporal el CSV guardado en el disco por defecto, para leerlo desde
     * una ruta local. En Laravel Cloud ese disco es un bucket: la web y los workers de la
     * cola no comparten disco, así que el archivo no está en el servidor que lo procesa.
     * Quien la llame debe borrar la copia al terminar.
     */
    public static function copiaTemporal(string $archivo): string
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'importacion-');
        $origen = Storage::disk()->readStream($archivo);
        $destino = fopen($ruta, 'wb');

        stream_copy_to_stream($origen, $destino);
        fclose($origen);
        fclose($destino);

        return $ruta;
    }

    public function delimitador(): string
    {
        return $this->delimitador;
    }

    public function codificacion(): string
    {
        return $this->codificacion;
    }

    /**
     * Textos de la primera fila; las columnas sin título se muestran como "Columna N".
     *
     * @return list<string>
     */
    public function encabezados(): array
    {
        $primera = $this->filasCrudas()->first() ?? [];

        return array_map(
            fn (mixed $valor, int $indice): string => trim((string) $valor) !== '' ? trim((string) $valor) : 'Columna '.($indice + 1),
            $primera,
            array_keys($primera),
        );
    }

    /**
     * Filas de datos (sin la de encabezados), indexadas por número de fila del archivo.
     * Las filas en blanco se omiten, pero conservan su número para reportar errores.
     *
     * @return LazyCollection<int, list<string>>
     */
    public function filas(): LazyCollection
    {
        return LazyCollection::make(function () {
            foreach ($this->filasCrudas()->skip(1) as $indice => $fila) {
                $valores = array_map(fn (mixed $valor): string => trim((string) $valor), $fila);

                if (implode('', $valores) === '') {
                    continue;
                }

                yield $indice + 1 => $valores;
            }
        });
    }

    /**
     * @return LazyCollection<int, array<int, mixed>>
     */
    private function filasCrudas(): LazyCollection
    {
        return LectorSimpleExcel::create($this->ruta, 'csv')
            ->conservarFilasVaciasCsv()
            ->useDelimiter($this->delimitador)
            ->useEncoding($this->codificacion)
            ->noHeaderRow()
            ->getRows();
    }

    private static function detectarDelimitador(string $muestra): string
    {
        $primeraLinea = strtok($muestra, "\r\n") ?: '';
        $conteos = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];

        foreach (array_keys($conteos) as $delimitador) {
            $conteos[$delimitador] = substr_count($primeraLinea, $delimitador);
        }

        arsort($conteos);

        return (string) array_key_first($conteos);
    }

    private static function detectarCodificacion(string $muestra): string
    {
        // Si la muestra corta un carácter multibyte al final, se ignora ese último fragmento.
        $muestra = mb_strcut($muestra, 0, max(strlen($muestra) - 4, 0), 'UTF-8');

        return mb_check_encoding($muestra, 'UTF-8') ? 'UTF-8' : 'Windows-1252';
    }
}
