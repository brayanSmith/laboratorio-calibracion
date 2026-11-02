<?php

namespace App\Modules\ServiciosTerceros\Models;

use App\Models\Tenant;
use App\Modules\OrdenesTrabajo\Models\OrdenTrabajo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $orden_trabajo_id
 * @property Carbon|null $fecha_programada Día para el que se agendó; lo elige quien agenda
 * @property Carbon|null $fecha_realizacion Día en que realmente se terminó; se fija sola al finalizar
 * @property string $tipo_servicio
 * @property int $empresa_tercero_id
 * @property string|null $pdf_servicio
 * @property string|null $codigo_compra Solo en servicios de mantenimiento; opcional
 * @property string|null $codigo_despacho Solo en servicios de mantenimiento; opcional
 * @property string $estado_servicio PENDIENTE al agendarse, EN_PROCESO al iniciarse y FINALIZADO al finalizarse
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
#[Fillable(['orden_trabajo_id', 'fecha_programada', 'fecha_realizacion', 'tipo_servicio', 'empresa_tercero_id', 'pdf_servicio', 'codigo_compra', 'codigo_despacho', 'estado_servicio', 'estado_final_equipo', 're_agendar', 'tenant_id'])]
class ServicioTercero extends Model
{
    use SoftDeletes;

    /**
     * Same defaults as the table, so a just-created model already carries them.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'estado_servicio' => 'PENDIENTE',
    ];

    /**
     * Registra un seguimiento (fase MANTENIMIENTO o CALIBRACION, según tipo_servicio) al
     * crearse el servicio o cambiar su estado, siempre que la orden de trabajo esté lista
     * para esa fase.
     */
    /**
     * Si el servicio ya se terminó (ver fecha_realizacion).
     */
    public function terminado(): bool
    {
        return $this->estado_servicio === 'FINALIZADO';
    }

    protected static function booted(): void
    {
        // La fecha real del servicio se fija la primera vez que termina y no vuelve a cambiar.
        static::saving(function (self $servicio): void {
            if ($servicio->fecha_realizacion === null && $servicio->terminado()) {
                $servicio->fecha_realizacion = now()->toDateString();
            }
        });

        static::saved(function (ServicioTercero $servicio): void {
            // Rechazado sin volver a agendarlo: el equipo no tiene arreglo, queda inactivo.
            $rechazadoSinReAgendar = $servicio->estado_servicio === 'FINALIZADO'
                && $servicio->estado_final_equipo === 'RECHAZADO'
                && ! $servicio->re_agendar
                && ($servicio->wasRecentlyCreated || $servicio->wasChanged(['estado_servicio', 'estado_final_equipo', 're_agendar']));

            if ($rechazadoSinReAgendar) {
                $modulo = $servicio->tipo_servicio === 'CALIBRACION' ? 'Calibración' : 'Mantenimiento';

                $servicio->ordenTrabajo?->equipoProgramacion?->equipo?->inactivar("{$modulo} de tercero: Fuera de servicio");
            }

            if (! $servicio->wasRecentlyCreated && ! $servicio->wasChanged('estado_servicio')) {
                return;
            }

            $ordenTrabajo = $servicio->ordenTrabajo;

            $listo = $servicio->tipo_servicio === 'CALIBRACION'
                ? $ordenTrabajo?->listo_para_calibracion
                : $ordenTrabajo?->listo_para_mantenimiento;

            if ($listo) {
                $ordenTrabajo->equipoProgramacion->registrarSeguimiento($servicio->tipo_servicio, $servicio->estado_servicio);
            }
        });
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
