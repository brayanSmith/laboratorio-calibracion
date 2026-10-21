<?php

namespace App\Modules\Importaciones\Http\Controllers;

use App\Enums\TipoImportacion;
use App\Http\Controllers\Controller;
use App\Modules\Importaciones\Http\Requests\LeerEncabezadosImportacionRequest;
use App\Modules\Importaciones\Http\Requests\MarcarImportacionLeidaRequest;
use App\Modules\Importaciones\Http\Requests\StoreImportacionRequest;
use App\Modules\Importaciones\Jobs\ProcesarImportacion;
use App\Modules\Importaciones\Models\Importacion;
use App\Modules\Importaciones\Support\LectorCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Importación de registros desde un CSV, de cualquiera de los tipos de TipoImportacion:
 * plantilla, lectura de columnas, importación en segundo plano y su historial.
 */
class ImportacionController extends Controller
{
    /** Los CSV que se subieron pero nunca se importaron se borran pasado este tiempo. */
    private const HORAS_ARCHIVOS_HUERFANOS = 24;

    private const IMPORTACIONES_EN_HISTORIAL = 10;

    /**
     * Descarga un CSV con los encabezados de todos los campos del tipo y una fila de ejemplo.
     * Va separado por ";" y con BOM para que Excel en español lo abra en columnas y con tildes.
     */
    public function plantilla(Request $request, TipoImportacion $tipo): BinaryFileResponse
    {
        abort_unless($tipo->permitidoPara($request->user()), 403);

        $importador = $tipo->importador();
        $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plantilla-'.Str::uuid().'.csv';

        SimpleExcelWriter::create($ruta, 'csv', delimiter: ';')
            ->noHeaderRow()
            ->addRow(array_column($importador::campos(), 'etiqueta'))
            ->addRow(array_map(
                fn (array $campo): string => $importador::ejemplo()[$campo['clave']] ?? '',
                $importador::campos(),
            ))
            ->close();

        return response()->download($ruta, "plantilla-importacion-{$tipo->value}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend();
    }

    /**
     * Guarda el CSV subido y devuelve sus encabezados, una vista previa y los campos del
     * tipo, para que el usuario relacione cada campo con una columna antes de importar.
     */
    public function encabezados(LeerEncabezadosImportacionRequest $request): JsonResponse
    {
        $tipo = $request->tipo();
        $importador = $tipo->importador();
        $carpeta = $this->carpeta($request);
        $this->borrarArchivosHuerfanos($carpeta);

        $archivo = Str::random(40).'.csv';
        $lector = LectorCsv::detectar($request->file('archivo')->getRealPath());
        $encabezados = $lector->encabezados();

        if ($encabezados === [] || $lector->filas()->isEmpty()) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no tiene filas para importar (la primera fila debe ser la de encabezados).',
            ]);
        }

        $request->file('archivo')->storeAs($carpeta, $archivo);

        return response()->json([
            'tipo' => $tipo->value,
            'tipo_etiqueta' => $tipo->etiqueta(),
            'nota' => $importador::nota(),
            'archivo' => $archivo,
            'nombre_original' => $request->file('archivo')->getClientOriginalName(),
            'encabezados' => $encabezados,
            'vista_previa' => $lector->filas()->take(3)->values()->all(),
            'campos' => $importador::campos(),
            'sugerencias' => $importador::sugerirMapeo($encabezados),
        ]);
    }

    /**
     * Crea la importación con el mapeo elegido y la deja corriendo en segundo plano.
     */
    public function store(StoreImportacionRequest $request): JsonResponse
    {
        $ruta = $this->carpeta($request).'/'.$request->validated('archivo');

        if (! Storage::disk()->exists($ruta)) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo ya no está disponible, vuelve a subirlo.',
            ]);
        }

        $copia = LectorCsv::copiaTemporal($ruta);

        try {
            $lector = LectorCsv::detectar($copia);
        } finally {
            @unlink($copia);
        }

        $importacion = Importacion::create([
            'tipo' => $request->tipo(),
            'archivo' => $ruta,
            'nombre_original' => $request->validated('nombre_original'),
            'delimitador' => $lector->delimitador(),
            'codificacion' => $lector->codificacion(),
            'mapeo' => array_map(intval(...), array_filter($request->validated('mapeo'), fn (mixed $indice): bool => $indice !== null)),
            'estado' => Importacion::ESTADO_PENDIENTE,
            'user_id' => $request->user()->id,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        ProcesarImportacion::dispatch($importacion);

        return response()->json($importacion->fresh()->resumen(), 201);
    }

    /**
     * Avance y resultado de una importación, para mostrarlo mientras corre.
     */
    public function show(Importacion $importacion): JsonResponse
    {
        Gate::authorize('view', $importacion);

        return response()->json($importacion->resumen());
    }

    /**
     * Importaciones que el usuario tiene en curso (pueden correr varias a la vez, de
     * cualquier tipo), para retomar su avance al recargar la página.
     */
    public function enCurso(Request $request): JsonResponse
    {
        $importaciones = Importacion::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('estado', [Importacion::ESTADO_PENDIENTE, Importacion::ESTADO_PROCESANDO])
            ->oldest('id')
            ->get()
            ->map(fn (Importacion $importacion): array => $importacion->resumen());

        return response()->json(['importaciones' => $importaciones]);
    }

    /**
     * Últimas importaciones del usuario, de todos los tipos: las que siguen cargando, para ver
     * su avance, y las terminadas, para revisar su resultado y sus errores después de que el
     * aviso de "terminada" haya desaparecido.
     */
    public function historial(Request $request): JsonResponse
    {
        // Las en curso y luego las sin leer van primero, para que ninguna quede fuera del límite.
        $importaciones = Importacion::query()
            ->where('user_id', $request->user()->id)
            ->orderByRaw('(estado IN (?, ?)) DESC', [Importacion::ESTADO_PENDIENTE, Importacion::ESTADO_PROCESANDO])
            ->orderByRaw('(leida_en IS NULL AND estado IN (?, ?)) DESC', [Importacion::ESTADO_COMPLETADA, Importacion::ESTADO_FALLIDA])
            ->latest('id')
            ->limit(self::IMPORTACIONES_EN_HISTORIAL)
            ->get()
            ->map(fn (Importacion $importacion): array => $importacion->resumen());

        return response()->json(['importaciones' => $importaciones]);
    }

    /**
     * Marca el resultado de la importación como leído o no leído, para llevar el seguimiento
     * de cuáles ya se revisaron (ver el globito del botón Importar).
     */
    public function marcarLeida(MarcarImportacionLeidaRequest $request, Importacion $importacion): JsonResponse
    {
        $importacion->update(['leida_en' => $request->boolean('leida') ? now() : null]);

        return response()->json($importacion->resumen());
    }

    private function carpeta(Request $request): string
    {
        return "importaciones/{$request->user()->tenant_id}";
    }

    private function borrarArchivosHuerfanos(string $carpeta): void
    {
        $limite = now()->subHours(self::HORAS_ARCHIVOS_HUERFANOS)->getTimestamp();
        $enUso = Importacion::query()
            ->whereIn('estado', [Importacion::ESTADO_PENDIENTE, Importacion::ESTADO_PROCESANDO])
            ->pluck('archivo')
            ->all();

        foreach (Storage::disk()->files($carpeta) as $archivo) {
            if (! in_array($archivo, $enUso, true) && Storage::disk()->lastModified($archivo) < $limite) {
                Storage::disk()->delete($archivo);
            }
        }
    }
}
