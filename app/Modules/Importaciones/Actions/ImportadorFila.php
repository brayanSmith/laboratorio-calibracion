<?php

namespace App\Modules\Importaciones\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea un registro a partir de una fila del CSV ya relacionada con los campos del sistema.
 * Cada tipo de importación (ver TipoImportacion) define sus campos, la fila de ejemplo de
 * la plantilla y cómo se valida y guarda la fila.
 */
abstract class ImportadorFila
{
    /** @var array<string, array<string, true>> catálogo => llaves de los nombres ya registrados */
    private array $nombres = [];

    /** @var array<class-string<Model>, array<string, int>> modelo => nombre normalizado => id */
    private array $ids = [];

    /** Carpeta (disco por defecto) con los archivos subidos junto al CSV, si el tipo los acepta. */
    protected ?string $carpetaAdjuntos = null;

    /** @var array<string, string>|null nombre de archivo normalizado => ruta, en la carpeta de adjuntos */
    private ?array $adjuntos = null;

    public function __construct(protected readonly int $tenantId) {}

    /**
     * Si al importar este tipo se puede subir una carpeta de archivos que las filas
     * referencian por su nombre (ver archivoAdjunto()).
     */
    public static function aceptaAdjuntos(): bool
    {
        return false;
    }

    /**
     * Usa los archivos de esta carpeta como los adjuntos de la importación.
     */
    public function conAdjuntos(?string $carpeta): static
    {
        $this->carpetaAdjuntos = $carpeta;
        $this->adjuntos = null;

        return $this;
    }

    /**
     * Campos disponibles en el orden en que se muestran en la modal de relacionar columnas.
     *
     * @return list<array{clave: string, etiqueta: string, requerido: bool, ayuda: string|null}>
     */
    abstract public static function campos(): array;

    /**
     * Valores de la fila de ejemplo de la plantilla descargable, por campo.
     *
     * @return array<string, string>
     */
    abstract public static function ejemplo(): array;

    /**
     * @param  array<string, string|null>  $valores  campo del sistema => valor de la fila
     *
     * @throws ValidationException cuando la fila no es válida o el registro ya existe
     */
    abstract public function importar(array $valores): Model;

    /**
     * Aviso que se muestra al relacionar las columnas (ej. qué catálogos se crean solos).
     */
    public static function nota(): ?string
    {
        return null;
    }

    /**
     * Claves de todos los campos.
     *
     * @return list<string>
     */
    public static function claves(): array
    {
        return array_column(static::campos(), 'clave');
    }

    /**
     * Claves de los campos que deben relacionarse con una columna del CSV.
     *
     * @return list<string>
     */
    public static function requeridos(): array
    {
        return array_column(array_filter(static::campos(), fn (array $campo): bool => $campo['requerido']), 'clave');
    }

    /**
     * Sugiere, para cada campo, el índice de la columna del CSV cuyo encabezado se le parece
     * (por clave o etiqueta), ignorando mayúsculas, tildes, espacios y signos.
     *
     * @param  list<string>  $encabezados
     * @return array<string, int|null>
     */
    public static function sugerirMapeo(array $encabezados): array
    {
        $porNormalizado = [];

        foreach ($encabezados as $indice => $encabezado) {
            $porNormalizado[self::normalizarEncabezado($encabezado)] ??= $indice;
        }

        $sugerencias = [];

        foreach (static::campos() as $campo) {
            $sugerencias[$campo['clave']] = $porNormalizado[self::normalizarEncabezado($campo['clave'])]
                ?? $porNormalizado[self::normalizarEncabezado($campo['etiqueta'])]
                ?? null;
        }

        return $sugerencias;
    }

    /**
     * Normaliza un texto para comparar encabezados: "Número de Serie" => "numerodeserie".
     */
    public static function normalizarEncabezado(string $texto): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texto))) ?? '';
    }

    /**
     * @return array{clave: string, etiqueta: string, requerido: bool, ayuda: string|null}
     */
    protected static function campo(string $clave, string $etiqueta, bool $requerido = false, ?string $ayuda = null): array
    {
        return ['clave' => $clave, 'etiqueta' => $etiqueta, 'requerido' => $requerido, 'ayuda' => $ayuda];
    }

    /**
     * Recorta los espacios de cada campo y deja los vacíos como null.
     *
     * @param  array<string, string|null>  $valores
     * @return array<string, string|null>
     */
    protected function limpiar(array $valores): array
    {
        $datos = [];

        foreach (static::claves() as $clave) {
            $valor = trim((string) ($valores[$clave] ?? ''));
            $datos[$clave] = $valor === '' ? null : $valor;
        }

        return $datos;
    }

    /**
     * Valida la fila con los nombres de los campos en los mensajes.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $reglas
     * @param  array<string, string>  $mensajes
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validar(array $datos, array $reglas, array $mensajes = []): array
    {
        return Validator::make($datos, $reglas, $mensajes, array_column(static::campos(), 'etiqueta', 'clave'))->validate();
    }

    /**
     * Unifica un número con separadores de miles o decimales de Excel en uno con punto
     * decimal: "1.250,50" => "1250.50", "1,250.50" => "1250.50", "$ 12,5" => "12.5".
     */
    protected static function numero(string $valor): string
    {
        $numero = str_replace([' ', '$'], '', $valor);

        if (str_contains($numero, '.') && str_contains($numero, ',')) {
            return strrpos($numero, ',') > strrpos($numero, '.')
                ? str_replace(['.', ','], ['', '.'], $numero)
                : str_replace(',', '', $numero);
        }

        return str_replace(',', '.', $numero);
    }

    /**
     * Texto para comparar sin importar mayúsculas, tildes ni espacios repetidos:
     * " ÁREA  Norte " y "area norte" => "area norte".
     */
    protected static function clave(string $texto): string
    {
        return Str::lower(Str::ascii(trim((string) preg_replace('/\s+/u', ' ', $texto))));
    }

    /**
     * Falla si ya hay un registro (sin eliminar) con ese nombre, sin importar mayúsculas,
     * tildes ni espacios; si no, lo reserva para que otra fila del mismo archivo no lo repita.
     * Los nombres se cargan una sola vez y se comparan en PHP (en SQLite LOWER() no
     * convierte las tildes).
     *
     * @param  class-string<Model>  $modelo
     * @param  array<string, string>  $filtros  columnas que también deben coincidir (ej. la categoría)
     *
     * @throws ValidationException
     */
    protected function reservarNombre(string $modelo, string $nombre, string $mensaje, array $filtros = []): void
    {
        $catalogo = $modelo.'|'.implode(',', array_keys($filtros));

        if (! isset($this->nombres[$catalogo])) {
            $this->nombres[$catalogo] = [];

            $registros = $modelo::query()
                ->where('tenant_id', $this->tenantId)
                ->get(['nombre', ...array_keys($filtros)]);

            foreach ($registros as $registro) {
                $llave = implode('|', [...array_map(fn (string $columna): mixed => $registro->getAttribute($columna), array_keys($filtros)), self::clave($registro->nombre)]);
                $this->nombres[$catalogo][$llave] = true;
            }
        }

        $llave = implode('|', [...array_values($filtros), self::clave($nombre)]);

        if (isset($this->nombres[$catalogo][$llave])) {
            throw ValidationException::withMessages(['nombre' => $mensaje]);
        }

        $this->nombres[$catalogo][$llave] = true;
    }

    /**
     * Id del registro (sin eliminar) con ese nombre, sin importar mayúsculas, tildes ni
     * espacios. Falla si no existe: los catálogos de los que depende la fila (ej. el área de
     * una bahía) deben crearse antes, para que un error de tipeo no cree uno nuevo.
     *
     * @param  class-string<Model>  $modelo
     *
     * @throws ValidationException
     */
    protected function idExistente(string $modelo, string $nombre, string $campo, string $mensaje): int
    {
        if (! isset($this->ids[$modelo])) {
            $this->ids[$modelo] = [];

            $registros = $modelo::query()
                ->where('tenant_id', $this->tenantId)
                ->orderBy('id')
                ->get(['id', 'nombre']);

            foreach ($registros as $registro) {
                $this->ids[$modelo][self::clave($registro->nombre)] ??= $registro->id;
            }
        }

        return $this->ids[$modelo][self::clave($nombre)]
            ?? throw ValidationException::withMessages([$campo => $mensaje]);
    }

    /**
     * Ruta (disco por defecto) del archivo subido con ese nombre, sin importar mayúsculas ni
     * tildes. Se busca solo por el nombre: las subcarpetas de la carpeta elegida no cuentan.
     *
     * @throws ValidationException si no se subió una carpeta o no está el archivo
     */
    protected function archivoAdjunto(string $nombre, string $campo): string
    {
        if ($this->carpetaAdjuntos === null) {
            throw ValidationException::withMessages([
                $campo => "La fila indica el adjunto {$nombre}, pero no se seleccionó la carpeta de adjuntos.",
            ]);
        }

        if ($this->adjuntos === null) {
            $this->adjuntos = [];

            foreach (Storage::disk()->files($this->carpetaAdjuntos) as $ruta) {
                $this->adjuntos[self::clave(basename($ruta))] ??= $ruta;
            }
        }

        return $this->adjuntos[self::clave(basename(str_replace('\\', '/', $nombre)))]
            ?? throw ValidationException::withMessages([
                $campo => "No se encontró el archivo {$nombre} en la carpeta de adjuntos.",
            ]);
    }

    /**
     * Interpreta "Sí/No", "1/0", "true/false" y "x"; cualquier otro texto se deja tal cual
     * para que la validación lo reporte.
     */
    protected function booleano(string $valor): bool|string
    {
        return match (Str::lower(Str::ascii($valor))) {
            'si', 's', 'yes', 'y', '1', 'true', 'verdadero', 'x' => true,
            'no', 'n', '0', 'false', 'falso' => false,
            default => $valor,
        };
    }

    /**
     * Convierte la fecha a "AAAA-MM-DD". Acepta cómo suele exportarla Excel: día primero
     * ("15/06/2025", "5/6/2025", "15-06-25", "15.06.2025"), año primero ("2025-06-15"), con
     * hora al final ("15/06/2025 0:00") o como número de serie de Excel ("45823"). Si no se
     * reconoce o no existe (ej. 31/02), se deja tal cual para que la validación la reporte.
     */
    protected function fecha(string $valor): string
    {
        $fecha = trim((string) preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?(\s*[ap]\.?\s?m\.?)?$/iu', '', trim($valor)));

        if (preg_match('/^(\d{4})[\/.-](\d{1,2})[\/.-](\d{1,2})$/', $fecha, $partes) === 1) {
            [, $anio, $mes, $dia] = $partes;
        } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2}|\d{4})$/', $fecha, $partes) === 1) {
            [, $dia, $mes, $anio] = $partes;
            $anio = strlen($anio) === 2 ? "20{$anio}" : $anio;
        } elseif (preg_match('/^\d{5}$/', $fecha) === 1) {
            // Excel cuenta los días desde el 30/12/1899.
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $fecha)->format('Y-m-d');
        } else {
            return $valor;
        }

        return checkdate((int) $mes, (int) $dia, (int) $anio)
            ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia)
            : $valor;
    }
}
