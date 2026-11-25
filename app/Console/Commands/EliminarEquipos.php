<?php

namespace App\Console\Commands;

use App\Modules\Calibraciones\Models\Calibracion;
use App\Modules\Mantenimientos\Models\Mantenimiento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra definitivamente equipos con toda su traza: programaciones, ingresos que solo los
 * traían a ellos, órdenes de trabajo, mantenimientos, calibraciones, despachos, servicios de
 * terceros, documentos, históricos y los archivos que solo ellos usaban (PDFs, fotos, firmas).
 * Al final deja el autoincremental de cada tabla tocada en el id más alto que quedó + 1.
 */
#[Signature('equipos:eliminar
    {codigos?* : Códigos de los equipos a eliminar (requiere --tenant)}
    {--tenant= : Id del tenant de los códigos}
    {--eliminados : Incluye los equipos que ya estaban eliminados (en todos los tenants, o solo en --tenant)}
    {--force : No pide confirmación}')]
#[Description('Elimina definitivamente equipos con todos sus datos relacionados y sus archivos')]
class EliminarEquipos extends Command
{
    /** Tablas de las que se borran filas, para reajustar su autoincremental al terminar. */
    private const TABLAS = [
        'version_documentos', 'comentario_mantenimientos', 'galeria_mantenimientos', 'item_mantenimientos',
        'mantenimiento_check_lists', 'mantenimiento_defecto_identificados', 'mantenimientos',
        'detalle_medicion_calibracions', 'calibracions', 'despacho_reagendamientos', 'despachos',
        'servicio_terceros', 'tiempo_servicios', 'orden_trabajos', 'foto_recepcions',
        'seguimiento_programacion_equipos', 'equipo_programacions', 'ingreso_envios', 'ingresos',
        'documento_equipos', 'equipo_especificacion_tecnicas', 'equipo_historicos', 'equipo_cambio_estados',
        'equipos', 'detalle_medicion_alcances', 'medicion_alcances',
    ];

    /** @var list<string> archivos del disco public que dejan de usarse */
    private array $archivos = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $codigos = array_values(array_unique($this->argument('codigos')));
        $tenantId = $this->option('tenant');

        if ($codigos === [] && ! $this->option('eliminados')) {
            $this->error('Indica los códigos de los equipos o usa --eliminados.');

            return self::INVALID;
        }

        if ($codigos !== [] && $tenantId === null) {
            $this->error('Para eliminar por código indica el tenant con --tenant=ID (los códigos se repiten entre tenants).');

            return self::INVALID;
        }

        $equipos = $this->equipos($codigos, $tenantId);
        $noEncontrados = array_diff($codigos, $equipos->pluck('codigo')->all());

        if ($noEncontrados !== []) {
            $this->warn('No se encontraron en el tenant: '.implode(', ', $noEncontrados));
        }

        if ($equipos->isEmpty()) {
            $this->info('No hay equipos para eliminar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Código', 'Tenant', 'Estado', 'Programaciones', 'Órdenes de trabajo'],
            $equipos->map(fn (object $equipo): array => [
                $equipo->id,
                $equipo->codigo,
                $equipo->tenant_id,
                $equipo->deleted_at === null ? 'Activo en el sistema' : 'Ya eliminado',
                DB::table('equipo_programacions')->where('equipo_id', $equipo->id)->count(),
                DB::table('orden_trabajos')
                    ->whereIn('equipo_programacion_id', DB::table('equipo_programacions')->select('id')->where('equipo_id', $equipo->id))
                    ->count(),
            ])->all(),
        );

        if (! $this->option('force') && ! $this->confirm("Se eliminarán DEFINITIVAMENTE {$equipos->count()} equipo(s) con toda su traza y sus archivos. Esto no se puede deshacer. ¿Continuar?")) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $this->eliminar($equipos->pluck('id')->all()));

        $this->borrarArchivos();
        $this->reajustarAutoincrementales();

        $this->info("Se eliminaron {$equipos->count()} equipo(s) y ".count($this->archivos).' archivo(s).');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $codigos
     * @return Collection<int, object{id: int, codigo: string, tenant_id: int, deleted_at: string|null}>
     */
    private function equipos(array $codigos, ?string $tenantId): Collection
    {
        return DB::table('equipos')
            ->select(['id', 'codigo', 'tenant_id', 'deleted_at'])
            ->where(fn (Builder $query) => $query
                ->when($codigos !== [], fn (Builder $porCodigo) => $porCodigo->orWhere(fn (Builder $q) => $q
                    ->where('tenant_id', $tenantId)
                    ->whereIn('codigo', $codigos)))
                ->when($this->option('eliminados'), fn (Builder $eliminados) => $eliminados->orWhere(fn (Builder $q) => $q
                    ->whereNotNull('deleted_at')
                    ->when($tenantId !== null, fn (Builder $delTenant) => $delTenant->where('tenant_id', $tenantId)))))
            ->orderBy('id')
            ->get();
    }

    /**
     * Borra de abajo hacia arriba (hijos antes que padres), sin pasar por los modelos para
     * que el borrado lógico no aplique.
     *
     * @param  list<int>  $equipoIds
     */
    private function eliminar(array $equipoIds): void
    {
        // Tipo de equipo y modelo de cada equipo, para luego retirar los alcances que se queden sin equipos.
        $modelos = DB::table('equipos')
            ->whereIn('id', $equipoIds)
            ->distinct()
            ->get(['tenant_id', 'tipo_equipo_id', 'modelo']);

        $programacionIds = $this->ids('equipo_programacions', 'equipo_id', $equipoIds);
        $ingresoIds = DB::table('equipo_programacions')->whereIn('id', $programacionIds)->whereNotNull('ingreso_id')->distinct()->pluck('ingreso_id')->all();
        $ordenIds = $this->ids('orden_trabajos', 'equipo_programacion_id', $programacionIds);
        $mantenimientoIds = $this->ids('mantenimientos', 'orden_trabajo_id', $ordenIds);
        $calibracionIds = $this->ids('calibracions', 'orden_trabajo_id', $ordenIds);
        $despachoIds = $this->ids('despachos', 'orden_trabajo_id', $ordenIds);

        // Mantenimientos y calibraciones, con sus versiones de PDF.
        $versiones = DB::table('version_documentos')->where(fn (Builder $query) => $query
            ->where(fn (Builder $q) => $q->where('documentable_type', Mantenimiento::class)->whereIn('documentable_id', $mantenimientoIds))
            ->orWhere(fn (Builder $q) => $q->where('documentable_type', Calibracion::class)->whereIn('documentable_id', $calibracionIds)));
        $this->recordarArchivos((clone $versiones)->pluck('ruta'));
        $versiones->delete();

        foreach (['comentario_mantenimientos', 'item_mantenimientos', 'mantenimiento_check_lists', 'mantenimiento_defecto_identificados'] as $tabla) {
            DB::table($tabla)->whereIn('mantenimiento_id', $mantenimientoIds)->delete();
        }

        $this->borrarConArchivos('galeria_mantenimientos', 'mantenimiento_id', $mantenimientoIds, ['imagen']);
        $this->borrarConArchivos('mantenimientos', 'id', $mantenimientoIds, ['pdf_informe']);
        DB::table('detalle_medicion_calibracions')->whereIn('calibracion_id', $calibracionIds)->delete();
        $this->borrarConArchivos('calibracions', 'id', $calibracionIds, ['pdf_certificado']);
        DB::table('despacho_reagendamientos')->whereIn('despacho_id', $despachoIds)->delete();
        $this->borrarConArchivos('despachos', 'id', $despachoIds, ['firma_cliente_recibe']);
        $this->borrarConArchivos('servicio_terceros', 'orden_trabajo_id', $ordenIds, ['pdf_servicio']);
        DB::table('tiempo_servicios')->whereIn('orden_trabajo_id', $ordenIds)->delete();
        DB::table('orden_trabajos')->whereIn('id', $ordenIds)->delete();

        // Programaciones y los ingresos que solo traían a estos equipos.
        $this->borrarConArchivos('foto_recepcions', 'equipo_programacion_id', $programacionIds, ['ruta']);
        DB::table('seguimiento_programacion_equipos')->whereIn('programacion_equipo_id', $programacionIds)->delete();
        DB::table('equipo_programacions')->whereIn('re_agendada_de_id', $programacionIds)->update(['re_agendada_de_id' => null]);
        DB::table('equipo_programacions')->whereIn('id', $programacionIds)->delete();
        DB::table('ingreso_envios')->whereIn('equipo_id', $equipoIds)->delete();

        $ingresosVacios = DB::table('ingresos')
            ->whereIn('id', $ingresoIds)
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                ->from('equipo_programacions')
                ->whereColumn('equipo_programacions.ingreso_id', 'ingresos.id'))
            ->pluck('id')
            ->all();
        DB::table('ingreso_envios')->whereIn('ingreso_id', $ingresosVacios)->delete();
        $this->borrarConArchivos('ingresos', 'id', $ingresosVacios, ['firma_cliente_entrega']);

        // Datos propios del equipo. Un documento compartido (documentación por modelo) solo
        // pierde su archivo si ningún otro equipo lo usa (ver borrarArchivos()).
        $this->borrarConArchivos('documento_equipos', 'equipo_id', $equipoIds, ['archivo']);
        $this->borrarConArchivos('equipo_historicos', 'equipo_id', $equipoIds, ['adjunto']);
        DB::table('equipo_especificacion_tecnicas')->whereIn('equipo_id', $equipoIds)->delete();
        DB::table('equipo_cambio_estados')->whereIn('equipo_id', $equipoIds)->delete();
        DB::table('equipos')->whereIn('id', $equipoIds)->delete();

        $this->eliminarAlcancesSinEquipos($modelos);
    }

    /**
     * Los alcances de medición son de un tipo de equipo y modelo, no de un equipo: se borran
     * (con sus detalles) los de los modelos que ya no tienen ningún equipo, ni siquiera uno
     * eliminado lógicamente. Si una calibración que se conserva usa alguno de sus detalles,
     * el alcance se deja.
     *
     * @param  Collection<int, object{tenant_id: int, tipo_equipo_id: int, modelo: string|null}>  $modelos
     */
    private function eliminarAlcancesSinEquipos(Collection $modelos): void
    {
        foreach ($modelos as $modelo) {
            $quedanEquipos = DB::table('equipos')
                ->where('tenant_id', $modelo->tenant_id)
                ->where('tipo_equipo_id', $modelo->tipo_equipo_id)
                ->when(
                    $modelo->modelo === null,
                    fn (Builder $query) => $query->whereNull('modelo'),
                    fn (Builder $query) => $query->where('modelo', $modelo->modelo),
                )
                ->exists();

            if ($quedanEquipos) {
                continue;
            }

            $alcanceIds = DB::table('medicion_alcances')
                ->where('tenant_id', $modelo->tenant_id)
                ->where('tipo_equipo_id', $modelo->tipo_equipo_id)
                ->where('modelo', $modelo->modelo ?? '')
                ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))
                    ->from('detalle_medicion_alcances')
                    ->join('detalle_medicion_calibracions', 'detalle_medicion_calibracions.detalle_medicion_alcance_id', '=', 'detalle_medicion_alcances.id')
                    ->whereColumn('detalle_medicion_alcances.medicion_alcance_id', 'medicion_alcances.id'))
                ->pluck('id')
                ->all();

            DB::table('detalle_medicion_alcances')->whereIn('medicion_alcance_id', $alcanceIds)->delete();
            DB::table('medicion_alcances')->whereIn('id', $alcanceIds)->delete();
        }
    }

    /**
     * @param  list<int>  $valores
     * @return list<int>
     */
    private function ids(string $tabla, string $columna, array $valores): array
    {
        return $valores === [] ? [] : DB::table($tabla)->whereIn($columna, $valores)->pluck('id')->all();
    }

    /**
     * @param  list<int>  $valores
     * @param  list<string>  $columnasArchivo
     */
    private function borrarConArchivos(string $tabla, string $columna, array $valores, array $columnasArchivo): void
    {
        $filas = DB::table($tabla)->whereIn($columna, $valores);

        foreach ($columnasArchivo as $columnaArchivo) {
            $this->recordarArchivos((clone $filas)->pluck($columnaArchivo));
        }

        $filas->delete();
    }

    /**
     * @param  Collection<int, string|null>  $rutas
     */
    private function recordarArchivos(Collection $rutas): void
    {
        array_push($this->archivos, ...$rutas->filter()->all());
    }

    /**
     * Borra los archivos después de confirmar la transacción, salvo los que todavía usa otro
     * registro (ej. un documento compartido por varios equipos del mismo modelo).
     */
    private function borrarArchivos(): void
    {
        $enUso = DB::table('documento_equipos')->whereIn('archivo', $this->archivos)->pluck('archivo')->all();
        $this->archivos = array_values(array_diff(array_unique($this->archivos), $enUso));

        Storage::disk('public')->delete($this->archivos);
    }

    /**
     * Deja el próximo id de cada tabla en el más alto que quedó + 1 (o en 1 si quedó vacía).
     * Los ids intermedios que quedaron libres no se reutilizan.
     */
    private function reajustarAutoincrementales(): void
    {
        $conexion = DB::connection();

        foreach (self::TABLAS as $tabla) {
            $maximo = (int) DB::table($tabla)->max('id');

            match ($conexion->getDriverName()) {
                'sqlite' => DB::table('sqlite_sequence')->where('name', $tabla)->update(['seq' => $maximo]),
                'mysql', 'mariadb' => DB::statement('ALTER TABLE `'.$conexion->getTablePrefix().$tabla.'` AUTO_INCREMENT = '.($maximo + 1)),
                'pgsql' => DB::select("SELECT setval(pg_get_serial_sequence(?, 'id'), ?, ?)", [$conexion->getTablePrefix().$tabla, max($maximo, 1), $maximo > 0]),
                default => null,
            };
        }
    }
}
