<?php

namespace App\Modules\Ingresos\Models;

use App\Models\Tenant;
use App\Modules\Equipos\Models\EquipoProgramacion;
use Database\Factories\FotoRecepcionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Foto tomada al recibir un equipo en un ingreso: evidencia de cómo llegó.
 *
 * @property int $id
 * @property int $equipo_programacion_id
 * @property string $ruta Archivo en el disco public
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EquipoProgramacion $equipoProgramacion
 * @property-read Tenant $tenant
 */
#[Fillable(['equipo_programacion_id', 'ruta', 'tenant_id'])]
#[UseFactory(FotoRecepcionFactory::class)]
class FotoRecepcion extends Model
{
    /** @use HasFactory<FotoRecepcionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // El archivo se va con su registro.
        static::deleted(fn (FotoRecepcion $foto) => Storage::disk('public')->delete($foto->ruta));
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }

    /**
     * @return BelongsTo<EquipoProgramacion, $this>
     */
    public function equipoProgramacion(): BelongsTo
    {
        return $this->belongsTo(EquipoProgramacion::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
