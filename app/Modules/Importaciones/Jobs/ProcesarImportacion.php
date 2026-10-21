<?php

namespace App\Modules\Importaciones\Jobs;

use App\Modules\Importaciones\Models\Importacion;
use App\Modules\Importaciones\Support\LectorCsv;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Importa los registros de un CSV en segundo plano, guardando el avance en la importación
 * para que el usuario lo vea mientras sigue trabajando.
 *
 * Procesa una tanda de filas y encola la siguiente: así cada job dura poco y no supera el
 * `retry_after` de la cola, que de lo contrario lo daría por perdido y lo volvería a tomar.
 */
class ProcesarImportacion implements ShouldQueue
{
    use Queueable;

    public const FILAS_POR_TANDA = 200;

    /** Máximo de errores que se guardan; el resto solo se cuenta. */
    private const MAXIMO_ERRORES = 200;

    /** No se reintenta: volver a correr una tanda reportaría sus filas como "ya existe". */
    public int $tries = 1;

    /**
     * @param  int  $desde  cuántas filas de datos ya procesaron las tandas anteriores
     */
    public function __construct(public Importacion $importacion, public int $desde = 0) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $copia = LectorCsv::copiaTemporal($this->importacion->archivo);

        try {
            $this->procesarTanda(new LectorCsv($copia, $this->importacion->delimitador, $this->importacion->codificacion));
        } finally {
            @unlink($copia);
        }
    }

    private function procesarTanda(LectorCsv $lector): void
    {
        $importacion = $this->importacion;

        if ($this->desde === 0) {
            $importacion->update([
                'estado' => Importacion::ESTADO_PROCESANDO,
                'total_filas' => $lector->filas()->count(),
            ]);
        }

        $importador = new ($importacion->tipo->importador())($importacion->tenant_id);
        $errores = $importacion->errores ?? [];
        $procesadas = $creadas = $conError = 0;

        foreach ($lector->filas()->skip($this->desde)->take(self::FILAS_POR_TANDA) as $numeroFila => $valores) {
            try {
                $importador->importar($this->valoresSegunMapeo($valores));
                $creadas++;
            } catch (ValidationException $excepcion) {
                $conError++;
                $this->agregarError($errores, $numeroFila, implode(' ', $excepcion->validator->errors()->all()));
            } catch (Throwable $excepcion) {
                $conError++;
                $this->agregarError($errores, $numeroFila, 'No se pudo guardar la fila por un error inesperado.');
                Log::error('Error al importar una fila', [
                    'tipo' => $importacion->tipo->value,
                    'importacion_id' => $importacion->id,
                    'fila' => $numeroFila,
                    'error' => $excepcion->getMessage(),
                ]);
            }

            $procesadas++;
        }

        $quedanFilas = $this->desde + $procesadas < $importacion->total_filas;

        $importacion->update([
            'estado' => $quedanFilas ? Importacion::ESTADO_PROCESANDO : Importacion::ESTADO_COMPLETADA,
            'filas_procesadas' => $this->desde + $procesadas,
            'filas_creadas' => $importacion->filas_creadas + $creadas,
            'filas_con_error' => $importacion->filas_con_error + $conError,
            'errores' => $errores,
        ]);

        if ($quedanFilas) {
            self::dispatch($importacion, $this->desde + $procesadas);

            return;
        }

        Storage::disk()->delete($importacion->archivo);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $excepcion): void
    {
        $this->importacion->update(['estado' => Importacion::ESTADO_FALLIDA]);

        Storage::disk()->delete($this->importacion->archivo);
    }

    /**
     * Arma los valores de la fila por campo del sistema según el índice de columna elegido.
     *
     * @param  list<string>  $valores
     * @return array<string, string|null>
     */
    private function valoresSegunMapeo(array $valores): array
    {
        return array_map(
            fn (int $indice): ?string => $valores[$indice] ?? null,
            $this->importacion->mapeo,
        );
    }

    /**
     * @param  list<array{fila: int, mensaje: string}>  $errores
     */
    private function agregarError(array &$errores, int $numeroFila, string $mensaje): void
    {
        if (count($errores) < self::MAXIMO_ERRORES) {
            $errores[] = ['fila' => $numeroFila, 'mensaje' => $mensaje];
        }
    }
}
