<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $orden_trabajo_id
 * @property string $tipo_servicio
 * @property int $empresa_tercero_id
 * @property string|null $pdf_servicio
 * @property string|null $estado_final_equipo
 * @property bool $re_agendar
 * @property int $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OrdenTrabajo $ordenTrabajo
 * @property-read EmpresaTercero $empresaTercero
 * @property-read Tenant $tenant
 */
#[Fillable(['orden_trabajo_id', 'tipo_servicio', 'empresa_tercero_id', 'pdf_servicio', 'estado_final_equipo', 're_agendar', 'tenant_id'])]
class ServicioTercero extends Model
{
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            're_agendar' => 'boolean',
        ];
    }

    /**
     * Get the public URL path of the documento (PDF) del servicio, if it has one.
     */
    public function pdfUrl(): ?string
    {
        if (! $this->pdf_servicio) {
            return null;
        }

        $url = Storage::disk('public')->url($this->pdf_servicio);

        if (config('filesystems.disks.public.driver') !== 'local') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : $url;
    }

    /**
     * Delete the stored documento (PDF) del servicio, if it has one.
     */
    public function deletePdf(): void
    {
        if ($this->pdf_servicio) {
            Storage::disk('public')->delete($this->pdf_servicio);
        }
    }

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
