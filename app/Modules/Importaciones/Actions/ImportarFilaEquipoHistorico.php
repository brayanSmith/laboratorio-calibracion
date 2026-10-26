<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoHistorico;
use App\Modules\ServiciosTerceros\Models\EmpresaTercero;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registra un servicio histórico (de otro sistema o anterior a este) de un equipo que ya
 * existe, a partir de una fila del CSV. El adjunto se toma, por su nombre, de la carpeta de
 * archivos que se subió junto al CSV.
 */
class ImportarFilaEquipoHistorico extends ImportadorFila
{
    /** @var array<string, int>|null código del equipo en minúsculas => id */
    private ?array $equipos = null;

    /** @var array<string, true> servicios ya registrados o importados en este archivo */
    private array $registrados = [];

    public static function campos(): array
    {
        return [
            self::campo('equipo', 'Código del equipo', true, 'Código de un equipo ya registrado.'),
            self::campo('tipo_servicio', 'Tipo de servicio', true, 'Mantenimiento o Calibración.'),
            self::campo('fecha_servicio', 'Fecha del servicio', true, 'DD/MM/AAAA (ej. 15/06/2024).'),
            self::campo('es_tercero', 'Es tercero', false, 'Sí si lo hizo un tercero; No si fue el laboratorio propio. Por defecto: No.'),
            self::campo('empresa_tercero', 'Empresa tercera', false, 'Nombre de una empresa tercera ya registrada. Obligatoria si Es tercero es Sí; si es No, se ignora.'),
            self::campo('adjunto', 'Adjunto', false, 'Nombre del archivo (ej. certificado-001.pdf), que debe estar en la carpeta de adjuntos.'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'equipo' => 'EQ-0001',
            'tipo_servicio' => 'Calibración',
            'fecha_servicio' => '15/06/2024',
            'es_tercero' => 'Sí',
            'empresa_tercero' => 'Empresa tercera de ejemplo',
            'adjunto' => 'certificado-001.pdf',
        ];
    }

    public static function nota(): ?string
    {
        return 'Los equipos deben existir antes de importar su histórico. Si el CSV indica adjuntos, selecciona la carpeta que los contiene.';
    }

    public static function aceptaAdjuntos(): bool
    {
        return true;
    }

    public function importar(array $valores): EquipoHistorico
    {
        $datos = $this->limpiar($valores);

        if ($datos['tipo_servicio'] !== null) {
            $datos['tipo_servicio'] = Str::upper(Str::ascii($datos['tipo_servicio']));
        }

        if ($datos['fecha_servicio'] !== null) {
            $datos['fecha_servicio'] = $this->fecha($datos['fecha_servicio']);
        }

        $datos['es_tercero'] = $datos['es_tercero'] === null ? false : $this->booleano($datos['es_tercero']);

        if ($datos['es_tercero'] !== true) {
            $datos['empresa_tercero'] = null;
        }

        $datos = $this->validar($datos, [
            'equipo' => ['required', 'string', 'max:255'],
            'tipo_servicio' => ['required', Rule::in(EquipoHistorico::TIPOS_SERVICIO)],
            'fecha_servicio' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'es_tercero' => ['boolean'],
            'empresa_tercero' => ['nullable', Rule::requiredIf($datos['es_tercero'] === true), 'string', 'max:255'],
            'adjunto' => ['nullable', 'string', 'max:255'],
        ], [
            'empresa_tercero.required' => 'Indica la empresa tercera que hizo el servicio.',
            'tipo_servicio.in' => 'El tipo de servicio debe ser Mantenimiento o Calibración.',
            'fecha_servicio.date_format' => 'La fecha del servicio no es válida: usa DD/MM/AAAA (ej. 15/06/2024).',
            'fecha_servicio.before_or_equal' => 'La fecha del servicio no puede ser futura.',
            'es_tercero.boolean' => 'Es tercero debe ser Sí o No.',
        ]);

        $equipoId = $this->equipoId($datos['equipo']);
        $empresaTerceroId = $datos['empresa_tercero'] === null ? null : $this->idExistente(
            EmpresaTercero::class,
            $datos['empresa_tercero'],
            'empresa_tercero',
            "No existe la empresa tercera {$datos['empresa_tercero']}; créala o impórtala primero.",
        );
        $adjunto = $datos['adjunto'] === null ? null : $this->archivoAdjunto($datos['adjunto'], 'adjunto');
        $this->reservarServicio($equipoId, $empresaTerceroId, $datos);

        return EquipoHistorico::create([
            'equipo_id' => $equipoId,
            'tipo_servicio' => $datos['tipo_servicio'],
            'fecha_servicio' => $datos['fecha_servicio'],
            'es_tercero' => $datos['es_tercero'],
            'empresa_tercero_id' => $empresaTerceroId,
            'adjunto' => $adjunto === null ? null : $this->guardarAdjunto($adjunto, $equipoId),
            'tenant_id' => $this->tenantId,
        ]);
    }

    /**
     * Id del equipo del tenant con ese código, sin importar mayúsculas.
     *
     * @throws ValidationException si no existe
     */
    private function equipoId(string $codigo): int
    {
        $this->equipos ??= Equipo::query()
            ->where('tenant_id', $this->tenantId)
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn (int $id, string $codigoEquipo): array => [Str::lower($codigoEquipo) => $id])
            ->all();

        return $this->equipos[Str::lower($codigo)]
            ?? throw ValidationException::withMessages(['equipo' => "No existe el equipo {$codigo}; regístralo o impórtalo primero."]);
    }

    /**
     * Falla si el equipo ya tiene ese servicio (mismo tipo, fecha y empresa tercera, o propio),
     * registrado antes o en otra fila del archivo: así volver a importar el mismo CSV no lo
     * duplica.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    private function reservarServicio(int $equipoId, ?int $empresaTerceroId, array $datos): void
    {
        $llave = fn (string $tipo, string $fecha, ?int $empresa): string => implode('|', [$tipo, $fecha, $empresa ?? 'propio']);

        if (! isset($this->registrados[$equipoId])) {
            $this->registrados[$equipoId] = [];

            EquipoHistorico::query()
                ->where('equipo_id', $equipoId)
                ->get(['tipo_servicio', 'fecha_servicio', 'empresa_tercero_id'])
                ->each(function (EquipoHistorico $historico) use ($equipoId, $llave): void {
                    $this->registrados[$equipoId][$llave($historico->tipo_servicio, $historico->fecha_servicio->toDateString(), $historico->empresa_tercero_id)] = true;
                });
        }

        $actual = $llave($datos['tipo_servicio'], $datos['fecha_servicio'], $empresaTerceroId);

        if (isset($this->registrados[$equipoId][$actual])) {
            $lugar = $datos['empresa_tercero'] ?? 'el laboratorio propio';

            throw ValidationException::withMessages([
                'equipo' => "El equipo {$datos['equipo']} ya tiene registrado ese servicio del {$datos['fecha_servicio']} en {$lugar}.",
            ]);
        }

        $this->registrados[$equipoId][$actual] = true;
    }

    /**
     * Copia el archivo subido al disco público, junto a los demás archivos del equipo. Se
     * copia (no se mueve) porque varias filas pueden usar el mismo adjunto.
     */
    private function guardarAdjunto(string $ruta, int $equipoId): string
    {
        $destino = EquipoHistorico::carpetaAdjuntos($this->tenantId, $equipoId).'/'.EquipoHistorico::nombreArchivoAdjunto($ruta);

        Storage::disk('public')->writeStream($destino, Storage::disk()->readStream($ruta));

        return $destino;
    }
}
