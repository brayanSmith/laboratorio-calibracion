<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deja tablas como recién creadas: borra todas sus filas (también las eliminadas
 * lógicamente) y el próximo id vuelve a ser 1. Sigue las llaves foráneas de la base: las
 * tablas que dependen de una vaciada con "on delete cascade" (o sin acción) también se
 * vacían, y en las que tienen "on delete set null" esa columna queda en null. También limpia
 * las relaciones polimórficas que no tienen llave foránea (versiones de documentos, roles y
 * permisos de los usuarios).
 */
#[Signature('tablas:resetear
    {tablas* : Tablas a vaciar (ej. items equipos)}
    {--force : No pide confirmación}')]
#[Description('Vacía tablas y sus tablas dependientes y reinicia sus ids en 1')]
class ResetearTablas extends Command
{
    /** Tablas que nunca se vacían: sin ellas la aplicación no sabe qué migraciones corrió. */
    private const PROTEGIDAS = ['migrations'];

    /** Relaciones polimórficas sin llave foránea: tabla => columna con la clase del modelo. */
    private const POLIMORFICAS = [
        'version_documentos' => 'documentable_type',
        'model_has_roles' => 'model_type',
        'model_has_permissions' => 'model_type',
    ];

    /** @var array<string, string> tabla a vaciar => por qué (pedida o de qué tabla depende) */
    private array $vaciar = [];

    /** @var array<string, list<string>> tabla => columnas que quedan en null */
    private array $anular = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $existentes = array_column(Schema::getTables(), 'name');
        $pedidas = array_values(array_unique($this->argument('tablas')));
        $desconocidas = array_diff($pedidas, $existentes);

        if ($desconocidas !== []) {
            $this->error('No existen las tablas: '.implode(', ', $desconocidas));

            return self::INVALID;
        }

        $protegidas = array_intersect($pedidas, self::PROTEGIDAS);

        if ($protegidas !== []) {
            $this->error('No se pueden resetear: '.implode(', ', $protegidas));

            return self::INVALID;
        }

        $this->resolverDependencias($pedidas, $existentes);
        $this->mostrarPlan();

        if (! $this->option('force') && ! $this->confirm('Se borrarán DEFINITIVAMENTE todas las filas de las '.count($this->vaciar).' tabla(s) y sus ids volverán a empezar en 1. Esto no se puede deshacer. ¿Continuar?')) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $this->resetear();

        $this->info('Listo: se resetearon '.count($this->vaciar).' tabla(s).');

        return self::SUCCESS;
    }

    /**
     * Agrega, de forma recursiva, las tablas que apuntan con llave foránea a una tabla que se
     * vacía: se vacían también, o solo se anula la columna si la llave es "on delete set null".
     *
     * @param  list<string>  $pedidas
     * @param  list<string>  $existentes
     */
    private function resolverDependencias(array $pedidas, array $existentes): void
    {
        /** @var array<string, list<array{tabla: string, columnas: list<string>, on_delete: string}>> $hijas tabla padre => llaves que la apuntan */
        $hijas = [];

        foreach ($existentes as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $llave) {
                $hijas[$llave['foreign_table']][] = [
                    'tabla' => $tabla,
                    'columnas' => $llave['columns'],
                    'on_delete' => strtolower((string) $llave['on_delete']),
                ];
            }
        }

        $pendientes = $pedidas;

        foreach ($pedidas as $tabla) {
            $this->vaciar[$tabla] = 'pedida';
        }

        while ($pendientes !== []) {
            $padre = array_shift($pendientes);

            foreach ($hijas[$padre] ?? [] as $llave) {
                if (isset($this->vaciar[$llave['tabla']])) {
                    continue;
                }

                if ($llave['on_delete'] === 'set null') {
                    $this->anular[$llave['tabla']] = [...($this->anular[$llave['tabla']] ?? []), ...$llave['columnas']];

                    continue;
                }

                $this->vaciar[$llave['tabla']] = "depende de {$padre}";
                $pendientes[] = $llave['tabla'];
            }
        }

        // Una tabla que se vacía no necesita que se le anule nada.
        $this->anular = array_map(
            fn (array $columnas): array => array_values(array_unique($columnas)),
            array_diff_key($this->anular, $this->vaciar),
        );
    }

    private function mostrarPlan(): void
    {
        $filas = [];

        foreach ($this->vaciar as $tabla => $motivo) {
            $filas[] = [$tabla, DB::table($tabla)->count(), "Vaciar ({$motivo})"];
        }

        foreach ($this->anular as $tabla => $columnas) {
            $filas[] = [$tabla, DB::table($tabla)->count(), 'Conservar, con '.implode(', ', $columnas).' en null'];
        }

        foreach ($this->polimorficasAfectadas() as $tabla => $clases) {
            if (! isset($this->vaciar[$tabla])) {
                $filas[] = [$tabla, DB::table($tabla)->whereIn(self::POLIMORFICAS[$tabla], $clases)->count(), 'Borrar las filas de: '.implode(', ', array_map(class_basename(...), $clases))];
            }
        }

        $this->table(['Tabla', 'Filas', 'Acción'], $filas);

        if (array_intersect(array_keys($this->vaciar), ['tenants', 'users']) !== []) {
            $this->warn('Atención: se vaciarán tenants o usuarios, y con ellos casi todos los datos del sistema.');
        }
    }

    /**
     * Clases de modelo guardadas en las tablas polimórficas cuya tabla se vacía.
     *
     * @return array<string, list<class-string<Model>>> tabla polimórfica => clases
     */
    private function polimorficasAfectadas(): array
    {
        $afectadas = [];

        foreach (self::POLIMORFICAS as $tabla => $columna) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            $clases = DB::table($tabla)
                ->distinct()
                ->pluck($columna)
                ->filter(fn (string $clase): bool => is_subclass_of($clase, Model::class) && isset($this->vaciar[(new $clase)->getTable()]))
                ->values()
                ->all();

            if ($clases !== []) {
                $afectadas[$tabla] = $clases;
            }
        }

        return $afectadas;
    }

    /**
     * Sin las llaves foráneas activas, para poder vaciar cada tabla sin importar el orden.
     * TRUNCATE (o su equivalente en SQLite) también reinicia el autoincremental.
     */
    private function resetear(): void
    {
        $polimorficas = $this->polimorficasAfectadas();

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($this->anular as $tabla => $columnas) {
                DB::table($tabla)->update(array_fill_keys($columnas, null));
            }

            foreach ($polimorficas as $tabla => $clases) {
                if (! isset($this->vaciar[$tabla])) {
                    DB::table($tabla)->whereIn(self::POLIMORFICAS[$tabla], $clases)->delete();
                }
            }

            foreach (array_keys($this->vaciar) as $tabla) {
                DB::table($tabla)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
