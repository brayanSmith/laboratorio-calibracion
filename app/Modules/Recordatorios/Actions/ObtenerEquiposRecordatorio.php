<?php

namespace App\Modules\Recordatorios\Actions;

use App\Modules\Equipos\Models\EquipoProgramacion;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ObtenerEquiposRecordatorio
{
    /**
     * Las programaciones del tenant que el recordatorio debe avisar, de equipos activos:
     * las vencidas, las que vencen en los próximos $diasAnticipacion días y las ya agendadas
     * para ese rango. Quedan fuera las ya recibidas (el servicio ya se gestionó), las
     * re-agendadas (las reemplaza la programación nueva) y las que no tienen fecha (correctivos).
     * Una programación agendada se informa como AGENDADO aunque esté vencida: ya está en camino.
     * Con $diasHastaInicio, lo que vence antes de ese día (y no está vencido) queda fuera:
     * ya lo avisó un envío anterior.
     *
     * @return Collection<int, array{programacion_id: int, codigo: string, tipo_equipo: string, modelo: string, numero_serie: string, cliente_id: int, cliente: string, bahia_id: int, bahia: string, area: string, tipo_servicio: string, fecha_proximo_servicio: string, dias: int, estado: 'VENCIDO'|'PROXIMO_A_VENCER'|'AGENDADO'}>
     */
    public function handle(int $tenantId, int $diasAnticipacion, int $diasHastaInicio = 0, ?CarbonInterface $hoy = null): Collection
    {
        $hoy = ($hoy ?? now())->utc()->startOfDay();
        $limite = $hoy->addDays($diasAnticipacion);
        $inicio = $hoy->addDays($diasHastaInicio);

        return EquipoProgramacion::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('estado_programacion', ['RECIBIDO', 'RE_AGENDADO'])
            ->whereNotNull('fecha_proximo_servicio')
            ->whereDate('fecha_proximo_servicio', '<=', $limite->toDateString())
            ->when($diasHastaInicio > 0, fn ($query) => $query->where(fn ($rango) => $rango
                ->whereDate('fecha_proximo_servicio', '<', $hoy->toDateString())
                ->orWhereDate('fecha_proximo_servicio', '>=', $inicio->toDateString())))
            ->whereHas('equipo', fn ($query) => $query->where('activo', true))
            ->with(['equipo.tipoEquipo', 'equipo.cliente', 'equipo.bahia', 'equipo.area'])
            ->orderBy('fecha_proximo_servicio')
            ->get()
            ->map(function (EquipoProgramacion $programacion) use ($hoy): array {
                $equipo = $programacion->equipo;
                $dias = (int) $hoy->diffInDays($programacion->fecha_proximo_servicio, false);

                return [
                    'programacion_id' => $programacion->id,
                    'codigo' => $equipo->codigo,
                    'tipo_equipo' => $equipo->tipoEquipo->nombre,
                    'modelo' => $equipo->modelo,
                    'numero_serie' => $equipo->numero_serie,
                    'cliente_id' => $equipo->cliente_id,
                    'cliente' => $equipo->cliente->nombre,
                    'bahia_id' => $equipo->bahia_id,
                    'bahia' => $equipo->bahia->nombre,
                    'area' => $equipo->area->nombre,
                    'tipo_servicio' => $this->tipoServicio($programacion->tipo_servicio),
                    'fecha_proximo_servicio' => $programacion->fecha_proximo_servicio->toDateString(),
                    'dias' => $dias,
                    'estado' => match (true) {
                        $programacion->estado_programacion === 'AGENDADO' => 'AGENDADO',
                        $dias < 0 => 'VENCIDO',
                        default => 'PROXIMO_A_VENCER',
                    },
                ];
            })
            ->values();
    }

    /**
     * "MANTENIMIENTO,CALIBRACION" como "Mantenimiento, Calibración".
     */
    private function tipoServicio(string $tipoServicio): string
    {
        return collect(explode(',', $tipoServicio))
            ->map(fn (string $tipo) => match (trim($tipo)) {
                'MANTENIMIENTO' => 'Mantenimiento',
                'CALIBRACION' => 'Calibración',
                default => ucfirst(strtolower(trim($tipo))),
            })
            ->implode(', ');
    }
}
