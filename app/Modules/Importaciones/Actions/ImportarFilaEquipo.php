<?php

namespace App\Modules\Importaciones\Actions;

use App\Enums\TipoDocumentoCliente;
use App\Modules\Catalogos\Models\Area;
use App\Modules\Catalogos\Models\Bahia;
use App\Modules\Catalogos\Models\Cliente;
use App\Modules\Catalogos\Models\Fabricante;
use App\Modules\Catalogos\Models\TipoEquipo;
use App\Modules\Catalogos\Models\TipoMagnitud;
use App\Modules\Catalogos\Models\UnidadMedida;
use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoProgramacion;
use App\Modules\Importaciones\Support\CamposImportacionEquipo;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crea un equipo a partir de una fila del CSV ya relacionada con los campos del sistema.
 * Los catálogos que no existan (tipo de equipo, fabricante, cliente, área, bahía, tipo de
 * magnitud y unidad de medida) se crean en el tenant, y se recuerdan para no buscarlos de
 * nuevo en cada fila.
 */
class ImportarFilaEquipo
{
    /** Días de aviso antes del vencimiento cuando el CSV no los trae (el mismo valor por defecto de la tabla). */
    private const DIAS_PLAZO_VENCIMIENTO = 30;

    /** @var array<string, array<string, int>> catálogo => llave normalizada => id */
    private array $catalogos = [];

    /** @var array<string, int> número de documento normalizado => id del cliente */
    private array $clientes = [];

    /** @var Collection<int, UnidadMedida>|null */
    private ?Collection $unidades = null;

    public function __construct(private readonly int $tenantId) {}

    /**
     * @param  array<string, string|null>  $valores  campo del sistema => valor de la fila
     *
     * @throws ValidationException cuando la fila no es válida o el equipo ya existe
     */
    public function importar(array $valores): Equipo
    {
        $datos = $this->validar($this->completarEspecificacion($this->normalizar($valores)));

        if (Equipo::withTrashed()->whereRaw('LOWER(codigo) = ?', [Str::lower($datos['codigo'])])->exists()) {
            throw ValidationException::withMessages([
                'codigo' => "Ya existe un equipo con el código {$datos['codigo']}.",
            ]);
        }

        // El cliente va primero: si su documento no es válido, no se crea ningún otro catálogo.
        $clienteId = $this->cliente(
            $datos['cliente_numero_documento'],
            $datos['cliente'],
            $datos['cliente_tipo_documento'] ?? TipoDocumentoCliente::Nit->value,
            $datos['cliente_email'] ?? null,
        );
        $areaId = $this->area($datos['area']);

        $atributos = [
            'codigo' => $datos['codigo'],
            'tipo_equipo_id' => $this->tipoEquipo($datos['tipo_equipo'], $datos['tipo_mantenimiento'] ?? 'A'),
            'tipo_tecnologia' => $datos['tipo_tecnologia'],
            'modelo' => $datos['modelo'],
            'fabricante_id' => $this->fabricante($datos['fabricante']),
            'numero_serie' => $datos['numero_serie'],
            'ficha_tecnica' => $this->fichaTecnica($datos),
            'area_id' => $areaId,
            'bahia_id' => $this->bahia($datos['bahia'], $areaId),
            'condicion_actual' => $datos['condicion_actual'],
            'notas' => $datos['notas'] ?? null,
            'activo' => $datos['activo'],
            'patron_referencia' => $datos['patron_referencia'],
            'requiere_programacion' => $datos['requiere_programacion'],
            'cliente_id' => $clienteId,
            'tenant_id' => $this->tenantId,
        ];

        $especificacion = filled($datos['tipo_magnitud'] ?? null) ? [
            'tipo_magnitud_id' => $this->tipoMagnitud($datos['tipo_magnitud']),
            'unidad_medida_id' => $this->unidadMedida($datos['unidad_medida'], $datos['simbolo_unidad'] ?? null),
            'alcance_indicacion' => $datos['alcance_indicacion'],
            'precision' => $datos['precision'],
            'resolucion' => $datos['resolucion'].$datos['simbolo_unidad'],
            'tenant_id' => $this->tenantId,
        ] : null;

        return DB::transaction(function () use ($atributos, $especificacion, $datos): Equipo {
            $equipo = Equipo::create($atributos);

            if ($especificacion !== null) {
                $equipo->equipoEspecificacionTecnica()->create($especificacion);
            }

            if (filled($datos['intervalo_servicio'] ?? null)) {
                $this->crearPrimeraProgramacion($equipo, $datos);
            }

            return $equipo;
        });
    }

    /**
     * Primera programación de servicio del equipo. El tipo de servicio sale de las columnas
     * Mantenimiento y Calibración; si las dos vienen vacías, del tipo de equipo (A:
     * mantenimiento y calibración; B: solo mantenimiento). La fecha del próximo servicio, si
     * no viene, se calcula con el último servicio y el intervalo.
     *
     * @param  array<string, mixed>  $datos
     */
    private function crearPrimeraProgramacion(Equipo $equipo, array $datos): void
    {
        $tiposServicio = array_keys(array_filter([
            'MANTENIMIENTO' => $datos['servicio_mantenimiento'] === true,
            'CALIBRACION' => $datos['servicio_calibracion'] === true,
        ]));

        $equipo->equipoProgramaciones()->create([
            'tipo_servicio' => implode(',', $tiposServicio ?: $equipo->tipoEquipo->tiposServicio()),
            'intervalo_servicio' => $datos['intervalo_servicio'],
            'intervalo_unidad' => $datos['intervalo_unidad'],
            'fecha_ultimo_servicio' => $datos['fecha_ultimo_servicio'],
            'fecha_proximo_servicio' => $datos['fecha_proximo_servicio'] ?? EquipoProgramacion::calcularFechaProximoServicio(
                $datos['fecha_ultimo_servicio'],
                $datos['intervalo_servicio'],
                $datos['intervalo_unidad'],
            ),
            'dias_plazo_vencimiento' => $datos['dias_plazo_vencimiento'] ?? self::DIAS_PLAZO_VENCIMIENTO,
            'tenant_id' => $this->tenantId,
        ]);
    }

    /**
     * Limpia los valores: recorta espacios, deja vacíos como null y unifica formatos
     * (tecnología, tipo de mantenimiento, costo, fechas, intervalo y los campos Sí/No).
     *
     * @param  array<string, string|null>  $valores
     * @return array<string, mixed>
     */
    private function normalizar(array $valores): array
    {
        $datos = [];

        foreach (CamposImportacionEquipo::claves() as $clave) {
            $valor = trim((string) ($valores[$clave] ?? ''));
            $datos[$clave] = $valor === '' ? null : $valor;
        }

        if ($datos['tipo_tecnologia'] !== null) {
            $datos['tipo_tecnologia'] = Str::upper(Str::ascii($datos['tipo_tecnologia']));
        }

        if ($datos['cliente_tipo_documento'] !== null) {
            $datos['cliente_tipo_documento'] = Str::upper(Str::ascii($datos['cliente_tipo_documento']));
        }

        if ($datos['tipo_mantenimiento'] !== null) {
            $datos['tipo_mantenimiento'] = Str::upper($datos['tipo_mantenimiento']);
        }

        if ($datos['costo_usd'] !== null) {
            $costo = str_replace([' ', '$'], '', $datos['costo_usd']);
            $datos['costo_usd'] = str_contains($costo, '.') ? str_replace(',', '', $costo) : str_replace(',', '.', $costo);
        }

        foreach (['fecha_adquisicion', 'fecha_ultimo_servicio', 'fecha_proximo_servicio'] as $clave) {
            if ($datos[$clave] !== null) {
                $datos[$clave] = $this->fecha($datos[$clave]);
            }
        }

        if ($datos['intervalo_servicio'] !== null) {
            $datos['intervalo_servicio'] = str_replace(',', '.', $datos['intervalo_servicio']);
        }

        if ($datos['intervalo_unidad'] !== null) {
            $datos['intervalo_unidad'] = $this->unidadIntervalo($datos['intervalo_unidad']);
        }

        foreach (['activo' => true, 'patron_referencia' => false, 'requiere_programacion' => true] as $clave => $defecto) {
            $texto = $datos[$clave];
            $datos[$clave] = is_string($texto) ? $this->booleano($texto) : $defecto;
        }

        // Vacías las dos, el tipo de servicio sale del tipo de equipo; si solo viene una, la
        // otra se toma como "No".
        if ($datos['servicio_mantenimiento'] !== null || $datos['servicio_calibracion'] !== null) {
            foreach (['servicio_mantenimiento', 'servicio_calibracion'] as $clave) {
                $texto = $datos[$clave];
                $datos[$clave] = is_string($texto) ? $this->booleano($texto) : false;
            }
        }

        return $datos;
    }

    /**
     * Agrega los símbolos a la especificación técnica para que el CSV solo traiga números:
     * a la precisión el "±" (adelante) o el "%" (al final) de la columna Símbolo de la
     * precisión, y al alcance y la resolución el símbolo de la unidad de medida. Los valores
     * que ya traen su símbolo se dejan como están.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function completarEspecificacion(array $datos): array
    {
        if ($datos['simbolo_precision'] !== null) {
            $datos['simbolo_precision'] = match (Str::upper(str_replace(' ', '', $datos['simbolo_precision']))) {
                '±', '+-', '+/-' => '±',
                '%', 'PORCENTAJE' => '%',
                default => $datos['simbolo_precision'],
            };
        }

        if ($datos['precision'] !== null) {
            $precision = $this->decimalConPunto(str_replace(' ', '', $datos['precision']));
            $datos['precision'] = match (true) {
                str_starts_with($precision, '±'), str_ends_with($precision, '%') => $precision,
                $datos['simbolo_precision'] === '±' => "±{$precision}",
                $datos['simbolo_precision'] === '%' => "{$precision}%",
                default => $precision,
            };
        }

        if ($datos['tipo_magnitud'] === null || $datos['unidad_medida'] === null) {
            return $datos;
        }

        $simbolo = $this->simboloUnidad($datos['unidad_medida'], $datos['simbolo_unidad']);
        $datos['simbolo_unidad'] = $simbolo;

        if ($datos['alcance_indicacion'] !== null) {
            $datos['alcance_indicacion'] = $this->conSimbolo($datos['alcance_indicacion'], $simbolo);
        }

        // La resolución lleva solo el número; el símbolo de la unidad se le agrega al guardar,
        // después de validar que la columna no traiga otra cosa (ver importar()).
        if ($datos['resolucion'] !== null) {
            $datos['resolucion'] = $this->decimalConPunto(str_replace(' ', '', $datos['resolucion']));
        }

        return $datos;
    }

    /**
     * Deja el valor terminado en el símbolo de la unidad tal como está escrito en la unidad:
     * "0.1" y "0.1BAR" => "0.1bar".
     */
    private function conSimbolo(string $valor, string $simbolo): string
    {
        $largo = mb_strlen($simbolo);

        if (mb_strtolower(mb_substr($valor, -$largo)) === mb_strtolower($simbolo)) {
            return rtrim(mb_substr($valor, 0, -$largo)).$simbolo;
        }

        return $valor.$simbolo;
    }

    /**
     * Las columnas Mantenimiento y Calibración no pueden venir las dos en "No": la
     * programación necesita al menos un tipo de servicio.
     *
     * @param  array<string, mixed>  $datos
     */
    private function algunServicioMarcado(array $datos): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($datos): void {
            if ($datos['servicio_mantenimiento'] === false && $value === false) {
                $fail('Marca "Sí" en Mantenimiento, en Calibración o en las dos.');
            }
        };
    }

    /**
     * Unifica el separador decimal en punto, porque según la configuración de Excel llega con
     * coma o con punto: "0,0005" => "0.0005", ",5" => "0.5". También convierte la notación
     * científica con que Excel exporta los números muy pequeños: "1E-04" => "0.0001".
     */
    private function decimalConPunto(string $valor): string
    {
        if (preg_match('/^\d+([.,]\d+)?E[-+]?\d+$/i', $valor) === 1) {
            return rtrim(rtrim(number_format((float) str_replace(',', '.', $valor), 10, '.', ''), '0'), '.');
        }

        $valor = preg_replace('/^(±?)[.,](\d)/u', '${1}0.$2', $valor) ?? $valor;

        return preg_replace('/^(±?\d+),(\d+)/u', '$1.$2', $valor) ?? $valor;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validar(array $datos): array
    {
        $texto = ['required', 'string', 'max:255'];
        $opcional = ['nullable', 'string', 'max:255'];

        // Mismos formatos que exige el formulario de equipos (ver StoreEquipoRequest).
        $simbolo = isset($datos['simbolo_unidad']) ? preg_quote($datos['simbolo_unidad'], '/') : null;
        $formatoAlcance = $simbolo !== null ? ["regex:/^.+{$simbolo}$/u"] : [];

        return Validator::make($datos, [
            'codigo' => $texto,
            'tipo_equipo' => $texto,
            'tipo_mantenimiento' => ['nullable', 'in:A,B'],
            'tipo_tecnologia' => ['required', 'in:ANALOGICO,DIGITAL'],
            'modelo' => $texto,
            'fabricante' => $texto,
            'numero_serie' => $texto,
            'cliente' => $texto,
            'cliente_numero_documento' => ['required', 'string', 'max:50'],
            'cliente_tipo_documento' => ['nullable', Rule::enum(TipoDocumentoCliente::class)],
            'cliente_email' => ['nullable', 'email', 'max:255'],
            'area' => $texto,
            'bahia' => $texto,
            'condicion_actual' => $texto,
            'notas' => $opcional,
            'activo' => ['boolean'],
            'patron_referencia' => ['boolean'],
            'requiere_programacion' => ['boolean'],
            'servicio_mantenimiento' => ['nullable', 'boolean'],
            'servicio_calibracion' => ['nullable', 'boolean', $this->algunServicioMarcado($datos)],
            'intervalo_servicio' => [
                'required_with:intervalo_unidad,fecha_ultimo_servicio,fecha_proximo_servicio,dias_plazo_vencimiento',
                'required_if_accepted:servicio_mantenimiento',
                'required_if_accepted:servicio_calibracion',
                'nullable', 'numeric', 'min:1',
            ],
            'intervalo_unidad' => ['required_with:intervalo_servicio', 'nullable', 'in:DIAS,SEMANAS,MESES'],
            // Basta una de las dos fechas: sin la del próximo servicio, se calcula con la del último.
            'fecha_ultimo_servicio' => [
                Rule::requiredIf($datos['intervalo_servicio'] !== null && $datos['fecha_proximo_servicio'] === null),
                'nullable', 'date_format:Y-m-d',
            ],
            'fecha_proximo_servicio' => [
                'nullable', 'date_format:Y-m-d',
                ...($datos['fecha_ultimo_servicio'] !== null ? ['after:fecha_ultimo_servicio'] : []),
            ],
            'dias_plazo_vencimiento' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'pais_procedencia' => $opcional,
            'numero_activo' => $opcional,
            'proveedor' => $opcional,
            'costo_usd' => ['nullable', 'numeric', 'min:0'],
            'fecha_adquisicion' => ['nullable', 'date_format:Y-m-d'],
            'tipo_magnitud' => $opcional,
            'unidad_medida' => ['required_with:tipo_magnitud', 'nullable', 'string', 'max:255'],
            'simbolo_unidad' => $opcional,
            'alcance_indicacion' => ['required_with:tipo_magnitud', 'nullable', 'string', 'max:50', ...$formatoAlcance],
            'simbolo_precision' => ['nullable', 'in:±,%'],
            'precision' => ['required_with:tipo_magnitud', 'nullable', 'string', 'max:20', 'regex:/^(±\d+(\.\d{1,2})?|\d+(\.\d{1,2})?%)$/u'],
            // Solo el número (el símbolo se agrega al guardar), con los mismos 4 decimales del formulario.
            'resolucion' => ['required_with:tipo_magnitud', 'nullable', 'string', 'max:40', 'regex:/^\d+(\.\d{1,4})?$/'],
        ], [
            '*.date_format' => 'La :attribute no es una fecha válida: usa DD/MM/AAAA (ej. 15/06/2025).',
            'simbolo_precision.in' => 'El símbolo de la precisión debe ser ± o %.',
            'precision.regex' => 'La precisión debe ser un número con ± adelante o % al final (ej. ±0.5 o 0.5%): indica el símbolo en la columna Símbolo de la precisión.',
            'alcance_indicacion.regex' => 'El alcance de indicación debe terminar con el símbolo de la unidad de medida ('.($datos['simbolo_unidad'] ?? '').').',
            'resolucion.regex' => 'La resolución debe ser solo el número, con máximo 4 decimales (ej. 0.0001): el símbolo de la unidad ('.($datos['simbolo_unidad'] ?? '').') se agrega solo.',
        ], array_column(CamposImportacionEquipo::todos(), 'etiqueta', 'clave'))->validate();
    }

    /**
     * Acepta "Meses", "mes", "Días", "semana"... y los lleva a DIAS, SEMANAS o MESES; cualquier
     * otro texto se deja tal cual para que la validación lo reporte.
     */
    private function unidadIntervalo(string $valor): string
    {
        $unidad = Str::upper(Str::ascii($valor));

        return match ($unidad) {
            'DIA', 'DIAS' => 'DIAS',
            'SEMANA', 'SEMANAS' => 'SEMANAS',
            'MES', 'MESES' => 'MESES',
            default => $unidad,
        };
    }

    /**
     * Interpreta "Sí/No", "1/0", "true/false" y "x"; cualquier otro texto se deja tal cual
     * para que la validación lo reporte.
     */
    private function booleano(string $valor): bool|string
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
    private function fecha(string $valor): string
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

    /**
     * @param  array<string, mixed>  $datos
     * @return array{pais_procedencia: string|null, numero_activo: string|null, proveedor: string|null, costo_usd: float|null, fecha_adquisicion: string|null}|null
     */
    private function fichaTecnica(array $datos): ?array
    {
        $ficha = [
            'pais_procedencia' => $datos['pais_procedencia'] ?? null,
            'numero_activo' => $datos['numero_activo'] ?? null,
            'proveedor' => $datos['proveedor'] ?? null,
            'costo_usd' => isset($datos['costo_usd']) ? (float) $datos['costo_usd'] : null,
            'fecha_adquisicion' => $datos['fecha_adquisicion'] ?? null,
        ];

        return array_filter($ficha, fn (mixed $valor): bool => $valor !== null) === [] ? null : $ficha;
    }

    private function tipoEquipo(string $nombre, string $tipoMantenimiento): int
    {
        return $this->buscarOCrear(TipoEquipo::class, $nombre, ['tipo_mantenimiento' => $tipoMantenimiento]);
    }

    private function fabricante(string $nombre): int
    {
        return $this->buscarOCrear(Fabricante::class, $nombre);
    }

    private function area(string $nombre): int
    {
        return $this->buscarOCrear(Area::class, $nombre);
    }

    private function bahia(string $nombre, int $areaId): int
    {
        return $this->buscarOCrear(Bahia::class, $nombre, ['area_id' => $areaId], ['area_id' => $areaId]);
    }

    private function tipoMagnitud(string $nombre): int
    {
        return $this->buscarOCrear(TipoMagnitud::class, $nombre);
    }

    /**
     * Símbolo de la unidad de la fila: el de la unidad si ya existe; si no, el de la columna
     * Símbolo de la unidad o, sin ella, el mismo nombre (así se creará la unidad).
     */
    private function simboloUnidad(string $nombre, ?string $simboloCsv): string
    {
        return $this->buscarUnidad($nombre, $simboloCsv)->simbolo ?? $simboloCsv ?? $nombre;
    }

    private function unidadMedida(string $nombre, ?string $simbolo): int
    {
        $unidad = $this->buscarUnidad($nombre, $simbolo);

        if ($unidad === null) {
            $unidad = UnidadMedida::create([
                'nombre' => $nombre,
                'simbolo' => $simbolo ?? $nombre,
                'tenant_id' => $this->tenantId,
            ]);
            $this->unidades?->push($unidad);
        }

        return $unidad->id;
    }

    /**
     * Busca la unidad, en este orden:
     * 1. Por la columna Símbolo de la unidad, el dato más confiable.
     * 2. Por la columna Unidad de medida, que puede traer el símbolo ("mm") o el nombre
     *    ("Milímetro"). El nombre se compara sin mayúsculas, tildes, espacios ni signos.
     *
     * Los símbolos se comparan sin mayúsculas, pero sin quitarles caracteres: sin tildes ni
     * signos, "µm" sería "m" y "°C" sería "C". Un símbolo idéntico va antes que uno que solo
     * coincide sin mayúsculas, porque en algunas unidades la mayúscula sí cambia el
     * significado (mA no es MA).
     */
    private function buscarUnidad(string $nombre, ?string $simbolo): ?UnidadMedida
    {
        $this->unidades ??= UnidadMedida::query()
            ->where('tenant_id', $this->tenantId)
            ->orderBy('id')
            ->get(['id', 'nombre', 'simbolo']);

        $claveNombre = self::claveNombreUnidad($nombre);

        return ($simbolo !== null ? $this->unidadConSimbolo($simbolo) : null)
            ?? $this->unidadConSimbolo($nombre)
            ?? ($claveNombre !== '' ? $this->unidades->first(
                fn (UnidadMedida $unidad): bool => self::claveNombreUnidad($unidad->nombre) === $claveNombre,
            ) : null);
    }

    private function unidadConSimbolo(string $simbolo): ?UnidadMedida
    {
        $simbolo = trim($simbolo);

        return $this->unidades?->first(fn (UnidadMedida $unidad): bool => $unidad->simbolo === $simbolo)
            ?? $this->unidades?->first(fn (UnidadMedida $unidad): bool => mb_strtolower($unidad->simbolo) === mb_strtolower($simbolo));
    }

    /**
     * Nombre de unidad para comparar sin mayúsculas, tildes, espacios ni signos:
     * "Milí-metro (mm)" y "milimetro mm" => "milimetromm".
     */
    private static function claveNombreUnidad(string $nombre): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($nombre)));
    }

    /**
     * Busca el cliente por su número de documento. Si no existe:
     * - y hay un cliente sin documento (creado antes de exigirlo) con el mismo nombre, se le
     *   asigna el documento, para no duplicarlo;
     * - si no, se crea con el email de la fila o, si no viene o ya lo usa otro cliente, con
     *   uno provisional único (la columna email es obligatoria y única por tenant).
     *
     * @throws ValidationException cuando el documento es de un cliente eliminado
     */
    private function cliente(string $numeroDocumento, string $nombre, string $tipoDocumento, ?string $email): int
    {
        $llave = self::clave($numeroDocumento);

        if (isset($this->clientes[$llave])) {
            return $this->clientes[$llave];
        }

        $existente = Cliente::withTrashed()
            ->where('tenant_id', $this->tenantId)
            ->whereRaw('LOWER(numero_documento) = ?', [$llave])
            ->first();

        if ($existente?->trashed()) {
            throw ValidationException::withMessages([
                'cliente_numero_documento' => "El cliente con documento {$numeroDocumento} fue eliminado; restáuralo o usa otro documento.",
            ]);
        }

        $cliente = $existente ?? $this->clienteSinDocumento($nombre);

        if ($cliente !== null && $cliente->numero_documento === null) {
            $cliente->update(['numero_documento' => $numeroDocumento, 'tipo_documento' => $tipoDocumento]);
        }

        $cliente ??= Cliente::create([
            'nombre' => $nombre,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroDocumento,
            'email' => $email !== null && ! $this->emailEnUso($email) ? $email : $this->emailProvisional($numeroDocumento),
            'tenant_id' => $this->tenantId,
        ]);

        return $this->clientes[$llave] = $cliente->id;
    }

    /**
     * El nombre se compara en PHP y no con LOWER() de la base, para ignorar también las
     * tildes (en SQLite LOWER() ni siquiera convierte "Á" en "á").
     */
    private function clienteSinDocumento(string $nombre): ?Cliente
    {
        $clave = self::clave($nombre);

        return Cliente::query()
            ->where('tenant_id', $this->tenantId)
            ->whereNull('numero_documento')
            ->orderBy('id')
            ->get()
            ->first(fn (Cliente $cliente): bool => self::clave($cliente->nombre) === $clave);
    }

    private function emailEnUso(string $email): bool
    {
        return Cliente::withTrashed()
            ->where('tenant_id', $this->tenantId)
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->exists();
    }

    private function emailProvisional(string $numeroDocumento): string
    {
        $base = 'doc-'.(Str::slug($numeroDocumento) ?: 'cliente');
        $email = "{$base}@sin-correo.local";
        $sufijo = 2;

        while (Cliente::withTrashed()->where('tenant_id', $this->tenantId)->where('email', $email)->exists()) {
            $email = "{$base}-{$sufijo}@sin-correo.local";
            $sufijo++;
        }

        return $email;
    }

    /**
     * Busca el registro por nombre sin importar mayúsculas, tildes ni espacios repetidos, y
     * lo crea si no existe. Cada catálogo se carga una sola vez y se compara en
     * PHP (ver clienteSinDocumento() sobre LOWER() en SQLite).
     *
     * @param  class-string<TipoEquipo|Fabricante|Area|Bahia|TipoMagnitud>  $modelo
     * @param  array<string, mixed>  $alCrear  atributos extra al crear el registro
     * @param  array<string, int>  $filtros  columnas que también deben coincidir (ej. el área de la bahía)
     */
    private function buscarOCrear(string $modelo, string $nombre, array $alCrear = [], array $filtros = []): int
    {
        $columnas = array_keys($filtros);
        $catalogo = $modelo.'|'.implode(',', $columnas);

        if (! isset($this->catalogos[$catalogo])) {
            $this->catalogos[$catalogo] = [];

            $registros = $modelo::query()
                ->where('tenant_id', $this->tenantId)
                ->orderBy('id')
                ->get(['id', 'nombre', ...$columnas]);

            foreach ($registros as $registro) {
                $llaveRegistro = implode('|', [...array_map(fn (string $columna): mixed => $registro->getAttribute($columna), $columnas), self::clave($registro->nombre)]);
                $this->catalogos[$catalogo][$llaveRegistro] ??= $registro->id;
            }
        }

        $llave = implode('|', [...array_values($filtros), self::clave($nombre)]);

        return $this->catalogos[$catalogo][$llave] ??= $modelo::create([
            ...$alCrear,
            'nombre' => $nombre,
            'tenant_id' => $this->tenantId,
        ])->id;
    }

    /**
     * Texto para comparar sin importar mayúsculas, tildes ni espacios repetidos:
     * " ÁREA  Norte " y "area norte" => "area norte".
     */
    private static function clave(string $texto): string
    {
        return Str::lower(Str::ascii(trim((string) preg_replace('/\s+/u', ' ', $texto))));
    }
}
