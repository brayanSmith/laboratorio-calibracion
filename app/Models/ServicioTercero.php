<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $orden_trabajo_id
 * @property string $tipo_servicio
 * @property int $empresa_tercero_id
 * @property string|null $pdf_servicio
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OrdenTrabajo $ordenTrabajo
 * @property-read EmpresaTercero $empresaTercero
 * @property-read Tenant $tenant
 */
#[Fillable(['orden_trabajo_id', 'tipo_servicio', 'empresa_tercero_id', 'pdf_servicio', 'tenant_id'])]
class ServicioTercero extends Model
{
    use SoftDeletes;

    /**
     * Get the orden de trabajo this servicio de tercero belongs to.
     *
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
    }

    /**
     * Get the empresa tercero that performed this service.
     *
     * @return BelongsTo<EmpresaTercero, $this>
     */
    public function empresaTercero(): BelongsTo
    {
        return $this->belongsTo(EmpresaTercero::class);
    }

    /**
     * Get the tenant this servicio de tercero belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
