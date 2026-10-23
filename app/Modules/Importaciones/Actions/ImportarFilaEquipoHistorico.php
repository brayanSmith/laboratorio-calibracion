<?php

namespace App\Modules\Importaciones\Actions;

use App\Modules\Equipos\Models\Equipo;
use App\Modules\Equipos\Models\EquipoHistorico;
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
            self::campo('nombre_laboratorio', 'Laboratorio', true, 'Nombre del laboratorio o empresa que hizo el servicio.'),
            self::campo('es_tercero', 'Es tercero', false, 'Sí si lo hizo un tercero; No si fue el laboratorio propio. Por defecto: No.'),
            self::campo('adjunto', 'Adjunto', false, 'Nombre del archivo (ej. certificado-001.pdf), que debe estar en la carpeta de adjuntos.'),
        ];
    }

    public static function ejemplo(): array
    {
        return [
            'equipo' => 'EQ-0001',
            'tipo_servicio' => 'Calibración',
            'fecha_servicio' => '15/06/2024',
            'nombre_laboratorio' => 'Laboratorio de ejemplo',
            'es_tercero' => 'Sí',
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

        $datos = $this->validar($datos, [
            'equipo' => ['required', 'string', 'max:255'],
            'tipo_servicio' => ['required', Rule::in(EquipoHistorico::TIPOS_SERVICIO)],
            'fecha_servicio' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'nombre_laboratorio' => ['required', 'string', 'max:255'],
            'es_tercero' => ['boolean'],
            'adjunto' => ['nullable', 'string', 'max:255'],
        ], [
            'tipo_servicio.in' => 'El tipo de servicio debe ser Mantenimiento o Calibración.',
            'fecha_servicio.date_format' => 'La fecha del servicio no es válida: usa DD/MM/AAAA (ej. 15/06/2024).',
            'fecha_servicio.before_or_equal' => 'La fecha del servicio no puede ser futura.',
            'es_tercero.boolean' => 'Es tercero debe ser Sí o No.',
        ]);

        $equipoId = $this->equipoId($datos['equipo']);
        $adjunto = $datos['adjunto'] === null ? null : $this->archivoAdjunto($datos['adjunto'], 'adjunto');
        $this->reservarServicio($equipoId, $datos);

        return EquipoHistorico::create([
            'equipo_id' => $equipoId,
            'tipo_servicio' => $datos['tipo_servicio'],
            'fecha_servicio' => $datos['fecha_servicio'],
            'nombre_laboratorio' => $datos['nombre_laboratorio'],
            'es_tercero' => $datos['es_tercero'],
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
     * Falla si el equipo ya tiene ese servicio (mismo tipo, fecha y laboratorio), registrado
     * antes o en otra fila del archivo: así volver a importar el mismo CSV no lo duplica.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    private function reservarServicio(int $equipoId, array $datos): void
    {
        $llave = fn (string $tipo, string $fecha, string $laboratorio): string => implode('|', [$tipo, $fecha, self::clave($laboratorio)]);

        if (! isset($this->registrados[$equipoId])) {
            $this->registrados[$equipoId] = [];

            EquipoHistorico::query()
                ->where('equipo_id', $equipoId)
                ->get(['tipo_servicio', 'fecha_servicio', 'nombre_laboratorio'])
                ->each(function (EquipoHistorico $historico) use ($equipoId, $llave): void {
                    $this->registrados[$equipoId][$llave($historico->tipo_servicio, $historico->fecha_servicio->toDateString(), $historico->nombre_laboratorio)] = true;
                });
        }

        $actual = $llave($datos['tipo_servicio'], $datos['fecha_servicio'], $datos['nombre_laboratorio']);

        if (isset($this->registrados[$equipoId][$actual])) {
            throw ValidationException::withMessages([
                'equipo' => "El equipo {$datos['equipo']} ya tiene registrado ese servicio del {$datos['fecha_servicio']} en {$datos['nombre_laboratorio']}.",
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
