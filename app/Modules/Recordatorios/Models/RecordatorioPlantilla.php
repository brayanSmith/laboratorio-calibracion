<?php

namespace App\Modules\Recordatorios\Models;

use App\Models\Tenant;
use Database\Factories\RecordatorioPlantillaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una plantilla adicional del correo (ej. "Navidad"), que se puede poner en uso en lugar
 * de la predeterminada del recordatorio.
 *
 * @property int $id
 * @property string $tipo cliente o bahia
 * @property string $nombre
 * @property string $asunto
 * @property string $mensaje HTML limpio del editor
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['tipo', 'nombre', 'asunto', 'mensaje', 'tenant_id'])]
#[UseFactory(RecordatorioPlantillaFactory::class)]
class RecordatorioPlantilla extends Model
{
    /** @use HasFactory<RecordatorioPlantillaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const TIPOS = ['cliente', 'bahia'];

    /**
     * Get the tenant this plantilla belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
