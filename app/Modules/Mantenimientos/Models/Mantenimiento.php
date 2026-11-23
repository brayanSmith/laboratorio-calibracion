<?php

namespace App\Modules\Mantenimientos\Models;

use App\Concerns\RestringidoAlTecnico;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VersionDocumento;
use App\Modules\Catalogos\Models\Novedad;
use App\Modules\OrdenesTrabajo\Http\Controllers\OrdenTrabajoController;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $orden_trabajo_id
 * @property Carbon|null $fecha_programada Día para el que se agendó; lo elige quien agenda
 * @property Carbon|null $fecha_realizacion Día en que realmente se terminó; se fija sola al finalizar
 * @property string $tipo_mantenimiento
 * @property Carbon $fecha_mantenimiento
 * @property string|null $descripcion Se completa al realizar el mantenimiento
 * @property string|null $estado_inicial_equipo OPERATIVO o INOPERATIVO; se completa al realizar el mantenimiento
 * @property string|null $observaciones_estado_inicial Cómo se recibió el equipo
 * @property string|null $estado_final_equipo Se completa al realizar el mantenimiento
 * @property string $estado_mantenimiento PENDIENTE por defecto
 * @property int $tecnico_id
 * @property bool $firmado
 * @property int|null $novedad_id Se completa si surge una novedad durante el mantenimiento
 * @property string|null $pdf_informe Ruta del informe PDF, generado al finalizar el mantenimiento
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OrdenTrabajo $ordenTrabajo
 * @property-read User $tecnico
 * @property-read Novedad|null $novedad
 * @property-read Tenant $tenant
 * @property-read Collection<int, MantenimientoCheckList> $mantenimientoCheckLists
 * @property-read Collection<int, MantenimientoDefectoIdentificado> $defectoIdentificados
 * @property-read Collection<int, ItemMantenimiento> $itemMantenimientos
 * @property-read Collection<int, ComentarioMantenimiento> $comentarioMantenimientos
 * @property-read Collection<int, GaleriaMantenimiento> $galeriaMantenimientos
 */
#[Fillable([
    'orden_trabajo_id', 'fecha_programada', 'fecha_realizacion', 'tipo_mantenimiento', 'fecha_mantenimiento', 'descripcion',
    'estado_inicial_equipo', 'observaciones_estado_inicial', 'estado_final_equipo', 'estado_mantenimiento',
    'tecnico_id', 'firmado', 'novedad_id', 'pdf_informe', 'tenant_id',
])]
class Mantenimiento extends Model
{
    use RestringidoAlTecnico;

    public static function permisoVerTodos(): TenantPermission
    {
        return TenantPermission::MantenimientosVerTodos;
    }

    public static function columnaTecnico(): string
    {
        return 'tecnico_id';
    }

    use SoftDeletes;

    /**
     * Same defaults as the table, so a just-created model already carries them.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'estado_mantenimiento' => 'PENDIENTE',
    ];

    /**
     * Registra un seguimiento de fase MANTENIMIENTO al crearse el mantenimiento o cambiar
     * su estado, siempre que la orden de trabajo esté lista para mantenimiento.
     */
    /**
     * Deja el equipo inactivo si se le reportó una novedad que lo pide, o si el mantenimiento
     * finalizó con el equipo fuera de servicio.
     */
    private function inactivarEquipoSiCorresponde(): void
    {
        $equipo = $this->ordenTrabajo?->equipoProgramacion?->equipo;

        if ($equipo === null) {
            return;
        }

        if ($this->novedad_id !== null && ($this->wasRecentlyCreated || $this->wasChanged('novedad_id'))) {
            $equipo->inactivarSiLaNovedadLoPide($this->novedad_id, 'mantenimiento');
        }

        $finalizoFueraDeServicio = $this->estado_mantenimiento === 'FINALIZADO'
            && $this->estado_final_equipo === 'FUERA_DE_SERVICIO'
            && ($this->wasRecentlyCreated || $this->wasChanged(['estado_mantenimiento', 'estado_final_equipo']));

        if ($finalizoFueraDeServicio) {
            // La descripción del técnico explica al cliente por qué quedó fuera de servicio.
            $equipo->inactivar('Mantenimiento: Fuera de servicio', $this->descripcion);
        }
    }

    /**
     * Si el mantenimiento ya se terminó (ver fecha_realizacion).
     */
    public function terminado(): bool
    {
        return $this->estado_mantenimiento === 'FINALIZADO';
    }

    protected static function booted(): void
    {
        // La fecha real del servicio se fija la primera vez que termina y no vuelve a cambiar.
        static::saving(function (self $servicio): void {
            if ($servicio->fecha_realizacion === null && $servicio->terminado()) {
                $servicio->fecha_realizacion = now()->toDateString();
            }
        });

        static::saved(function (Mantenimiento $mantenimiento): void {
            $mantenimiento->inactivarEquipoSiCorresponde();

            if (! $mantenimiento->wasRecentlyCreated && ! $mantenimiento->wasChanged('estado_mantenimiento')) {
                return;
            }

            $ordenTrabajo = $mantenimiento->ordenTrabajo;

            if ($ordenTrabajo?->listo_para_mantenimiento) {
                $ordenTrabajo->equipoProgramacion->registrarSeguimiento('MANTENIMIENTO', $mantenimiento->estado_mantenimiento);
            }
        });
    }

    /**
     * Get the orden de trabajo this mantenimiento belongs to.
     *
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
    }

    /**
     * Get the tecnico that performed this mantenimiento.
     *
     * @return BelongsTo<User, $this>
     */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the novedad reported during this mantenimiento.
     *
     * @return BelongsTo<Novedad, $this>
     */
    public function novedad(): BelongsTo
    {
        return $this->belongsTo(Novedad::class);
    }

    /**
     * Get the tenant this mantenimiento belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the checklist items copied from the tipo de equipo when this mantenimiento
     * was agendado (ver OrdenTrabajoController::store()).
     *
     * @return HasMany<MantenimientoCheckList, $this>
     */
    public function mantenimientoCheckLists(): HasMany
    {
        return $this->hasMany(MantenimientoCheckList::class);
    }

    /**
     * Get the defectos identificados during this mantenimiento.
     *
     * @return HasMany<MantenimientoDefectoIdentificado, $this>
     */
    public function defectoIdentificados(): HasMany
    {
        return $this->hasMany(MantenimientoDefectoIdentificado::class);
    }

    /**
     * Si estaba en FALTA_REPUESTOS pero ya no tiene ítems registrados sin stock, vuelve a
     * EN_PROCESO. Así el estado guardado no queda desactualizado al quitar o surtir ítems.
     */
    public function sincronizarEstadoRepuestos(): void
    {
        if ($this->estado_mantenimiento === 'FALTA_REPUESTOS'
            && ! $this->itemMantenimientos()->where('con_stock', false)->exists()) {
            $this->update(['estado_mantenimiento' => 'EN_PROCESO']);
        }
    }

    /**
     * Resumen de todo lo que sale en el informe (datos, checklist, defectos, ítems,
     * comentarios y fotos), para saber si algo cambió al guardar.
     */
    public function huellaInforme(): string
    {
        return md5((string) json_encode([
            $this->only(['fecha_mantenimiento', 'descripcion', 'estado_inicial_equipo', 'observaciones_estado_inicial', 'estado_final_equipo', 'tecnico_id', 'firmado', 'novedad_id']),
            $this->mantenimientoCheckLists()->orderBy('id')->get(['id', 'cumple', 'observacion'])->toArray(),
            $this->defectoIdentificados()->orderBy('id')->pluck('id')->all(),
            $this->itemMantenimientos()->orderBy('id')->pluck('id')->all(),
            $this->comentarioMantenimientos()->orderBy('id')->pluck('id')->all(),
            $this->galeriaMantenimientos()->orderBy('id')->pluck('id')->all(),
        ]));
    }

    /**
     * Si cambió algo después de emitir la última versión del informe (ver
     * marcarInformeDesactualizado()).
     */
    public function informeDesactualizado(): bool
    {
        $ultima = $this->versiones()->reorder('numero', 'desc')->first();

        return $ultima !== null && $this->updated_at?->gt($ultima->created_at) === true;
    }

    /**
     * Al quitar un defecto, ítem, comentario o foto de un mantenimiento finalizado, deja
     * constancia de que su informe quedó desactualizado: al guardar se emite uno nuevo.
     */
    public function marcarInformeDesactualizado(): void
    {
        if ($this->estado_mantenimiento === 'FINALIZADO') {
            $this->touch();
        }
    }

    /**
     * Get the items (repuestos/insumos) used during this mantenimiento.
     *
     * @return HasMany<ItemMantenimiento, $this>
     */
    public function itemMantenimientos(): HasMany
    {
        return $this->hasMany(ItemMantenimiento::class);
    }

    /**
     * Get the comentarios left on this mantenimiento.
     *
     * @return HasMany<ComentarioMantenimiento, $this>
     */
    public function comentarioMantenimientos(): HasMany
    {
        return $this->hasMany(ComentarioMantenimiento::class);
    }

    /**
     * Get the fotos de la galería of this mantenimiento.
     *
     * @return HasMany<GaleriaMantenimiento, $this>
     */
    public function galeriaMantenimientos(): HasMany
    {
        return $this->hasMany(GaleriaMantenimiento::class);
    }

    /**
     * Los PDF emitidos, del original a la corrección más reciente.
     *
     * @return MorphMany<VersionDocumento, $this>
     */
    public function versiones(): MorphMany
    {
        return $this->morphMany(VersionDocumento::class, 'documentable')->orderBy('numero');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'fecha_realizacion' => 'date',
            'fecha_mantenimiento' => 'date',
            'firmado' => 'boolean',
        ];
    }
}
