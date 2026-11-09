<?php

namespace App\Enums;

/**
 * Módulos del dashboard que tienen objetivos (anuales, o por mes y tipo de equipo).
 */
enum ModuloObjetivo: string
{
    case TiemposAtencion = 'TIEMPOS_ATENCION';
    case DesempenoTecnicos = 'DESEMPENO_TECNICOS';
    case Cumplimiento = 'CUMPLIMIENTO';

    /**
     * Get the label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::TiemposAtencion => 'Tiempos de atención',
            self::DesempenoTecnicos => 'Desempeño por técnico',
            self::Cumplimiento => 'Cumplimiento',
        };
    }

    /**
     * Si sus objetivos se definen por mes y tipo de equipo (desempeño por técnico) en vez de
     * uno anual para todo el laboratorio.
     */
    public function porMesYTipoEquipo(): bool
    {
        return $this === self::DesempenoTecnicos;
    }

    /**
     * Conceptos que se definen como objetivo en este módulo, en el orden del formulario.
     *
     * @return list<ConceptoObjetivo>
     */
    public function conceptos(): array
    {
        return array_values(array_filter(
            ConceptoObjetivo::cases(),
            fn (ConceptoObjetivo $concepto): bool => $concepto->modulo() === $this,
        ));
    }
}
