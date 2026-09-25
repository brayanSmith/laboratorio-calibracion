<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Empresa> $empresas
 * @property-read Collection<int, Cliente> $clientes
 * @property-read Collection<int, Area> $areas
 * @property-read Collection<int, Bahia> $bahias
 * @property-read Collection<int, Fabricante> $fabricantes
 * @property-read Collection<int, Laboratorio> $laboratorios
 * @property-read Collection<int, Tercero> $terceros
 * @property-read Collection<int, EmpresaTercero> $empresaTerceros
 * @property-read Collection<int, TipoEquipo> $tiposEquipo
 * @property-read Collection<int, TipoEquipoCheckList> $tiposEquipoCheckList
 * @property-read Collection<int, TipoMagnitud> $tiposMagnitud
 * @property-read Collection<int, UnidadMedida> $unidadesMedida
 * @property-read Collection<int, Item> $items
 * @property-read Collection<int, ProcedimientoCalibracion> $procedimientosCalibracion
 * @property-read Collection<int, Equipo> $equipos
 * @property-read Collection<int, EquipoEspecificacionTecnica> $equiposEspecificacionesTecnicas
 * @property-read Collection<int, EquipoProgramacion> $equiposProgramaciones
 * @property-read Collection<int, DocumentoEquipo> $documentosEquipo
 * @property-read Collection<int, Ingreso> $ingresos
 * @property-read Collection<int, Novedad> $novedades
 * @property-read Collection<int, OrdenTrabajo> $ordenesTrabajo
 * @property-read Collection<int, Despacho> $despachos
 * @property-read Collection<int, Mantenimiento> $mantenimientos
 * @property-read Collection<int, MantenimientoCheckList> $mantenimientosCheckList
 * @property-read Collection<int, MantenimientoDefectoIdentificado> $mantenimientosDefectosIdentificados
 * @property-read Collection<int, ItemMantenimiento> $itemsMantenimiento
 * @property-read Collection<int, ComentarioMantenimiento> $comentariosMantenimiento
 * @property-read Collection<int, GaleriaMantenimiento> $galeriasMantenimiento
 * @property-read Collection<int, Calibracion> $calibraciones
 * @property-read Collection<int, MedicionAlcance> $medicionesAlcance
 * @property-read Collection<int, DetalleMedicionAlcance> $detallesMedicionAlcance
 * @property-read Collection<int, DetalleMedicionCalibracion> $detallesMedicionCalibracion
 * @property-read Collection<int, TiempoServicio> $tiemposServicio
 * @property-read Collection<int, ServicioTercero> $serviciosTerceros
 */
#[Fillable(['nombre', 'slug', 'activo'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active tenants.
     *
     * @param  Builder<Tenant>  $query
     */
    public function scopeActivo(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Empresa, $this>
     */
    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    /**
     * @return HasMany<Cliente, $this>
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    /**
     * @return HasMany<Area, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    /**
     * @return HasMany<Bahia, $this>
     */
    public function bahias(): HasMany
    {
        return $this->hasMany(Bahia::class);
    }

    /**
     * @return HasMany<Fabricante, $this>
     */
    public function fabricantes(): HasMany
    {
        return $this->hasMany(Fabricante::class);
    }

    /**
     * @return HasMany<Laboratorio, $this>
     */
    public function laboratorios(): HasMany
    {
        return $this->hasMany(Laboratorio::class);
    }

    /**
     * @return HasMany<Tercero, $this>
     */
    public function terceros(): HasMany
    {
        return $this->hasMany(Tercero::class);
    }

    /**
     * @return HasMany<EmpresaTercero, $this>
     */
    public function empresaTerceros(): HasMany
    {
        return $this->hasMany(EmpresaTercero::class);
    }

    /**
     * @return HasMany<TipoEquipo, $this>
     */
    public function tiposEquipo(): HasMany
    {
        return $this->hasMany(TipoEquipo::class);
    }

    /**
     * @return HasMany<TipoEquipoCheckList, $this>
     */
    public function tiposEquipoCheckList(): HasMany
    {
        return $this->hasMany(TipoEquipoCheckList::class);
    }

    /**
     * @return HasMany<TipoMagnitud, $this>
     */
    public function tiposMagnitud(): HasMany
    {
        return $this->hasMany(TipoMagnitud::class);
    }

    /**
     * @return HasMany<UnidadMedida, $this>
     */
    public function unidadesMedida(): HasMany
    {
        return $this->hasMany(UnidadMedida::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return HasMany<ProcedimientoCalibracion, $this>
     */
    public function procedimientosCalibracion(): HasMany
    {
        return $this->hasMany(ProcedimientoCalibracion::class);
    }

    /**
     * @return HasMany<Equipo, $this>
     */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * @return HasMany<EquipoEspecificacionTecnica, $this>
     */
    public function equiposEspecificacionesTecnicas(): HasMany
    {
        return $this->hasMany(EquipoEspecificacionTecnica::class);
    }

    /**
     * @return HasMany<EquipoProgramacion, $this>
     */
    public function equiposProgramaciones(): HasMany
    {
        return $this->hasMany(EquipoProgramacion::class);
    }

    /**
     * @return HasMany<DocumentoEquipo, $this>
     */
    public function documentosEquipo(): HasMany
    {
        return $this->hasMany(DocumentoEquipo::class);
    }

    /**
     * @return HasMany<Ingreso, $this>
     */
    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class);
    }

    /**
     * @return HasMany<Novedad, $this>
     */
    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class);
    }

    /**
     * @return HasMany<OrdenTrabajo, $this>
     */
    public function ordenesTrabajo(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

    /**
     * @return HasMany<Despacho, $this>
     */
    public function despachos(): HasMany
    {
        return $this->hasMany(Despacho::class);
    }

    /**
     * @return HasMany<Mantenimiento, $this>
     */
    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    /**
     * @return HasMany<MantenimientoCheckList, $this>
     */
    public function mantenimientosCheckList(): HasMany
    {
        return $this->hasMany(MantenimientoCheckList::class);
    }

    /**
     * @return HasMany<MantenimientoDefectoIdentificado, $this>
     */
    public function mantenimientosDefectosIdentificados(): HasMany
    {
        return $this->hasMany(MantenimientoDefectoIdentificado::class);
    }

    /**
     * @return HasMany<ItemMantenimiento, $this>
     */
    public function itemsMantenimiento(): HasMany
    {
        return $this->hasMany(ItemMantenimiento::class);
    }

    /**
     * @return HasMany<ComentarioMantenimiento, $this>
     */
    public function comentariosMantenimiento(): HasMany
    {
        return $this->hasMany(ComentarioMantenimiento::class);
    }

    /**
     * @return HasMany<GaleriaMantenimiento, $this>
     */
    public function galeriasMantenimiento(): HasMany
    {
        return $this->hasMany(GaleriaMantenimiento::class);
    }

    /**
     * @return HasMany<Calibracion, $this>
     */
    public function calibraciones(): HasMany
    {
        return $this->hasMany(Calibracion::class);
    }

    /**
     * @return HasMany<MedicionAlcance, $this>
     */
    public function medicionesAlcance(): HasMany
    {
        return $this->hasMany(MedicionAlcance::class);
    }

    /**
     * @return HasMany<DetalleMedicionAlcance, $this>
     */
    public function detallesMedicionAlcance(): HasMany
    {
        return $this->hasMany(DetalleMedicionAlcance::class);
    }

    /**
     * @return HasMany<DetalleMedicionCalibracion, $this>
     */
    public function detallesMedicionCalibracion(): HasMany
    {
        return $this->hasMany(DetalleMedicionCalibracion::class);
    }

    /**
     * @return HasMany<TiempoServicio, $this>
     */
    public function tiemposServicio(): HasMany
    {
        return $this->hasMany(TiempoServicio::class);
    }

    /**
     * @return HasMany<ServicioTercero, $this>
     */
    public function serviciosTerceros(): HasMany
    {
        return $this->hasMany(ServicioTercero::class);
    }
}
