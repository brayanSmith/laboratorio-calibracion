<?php

namespace App\Enums;

/**
 * Cada objetivo que se puede definir en el dashboard. El concepto determina su módulo y su
 * unidad, para que no se puedan guardar desalineados. Las cantidades de mantenimientos y
 * calibraciones no están aquí: son los objetivos de cada usuario (ver ObjetivoUser).
 */
enum ConceptoObjetivo: string
{
    case TiempoAtencionDias = 'TIEMPO_ATENCION_DIAS';
    case PromedioHorasMantenimiento = 'PROMEDIO_HORAS_MANTENIMIENTO';
    case PromedioHorasCalibracion = 'PROMEDIO_HORAS_CALIBRACION';
    case CumplimientoPorcentaje = 'CUMPLIMIENTO_PORCENTAJE';

    /**
     * Módulo del dashboard al que pertenece el objetivo.
     */
    public function modulo(): ModuloObjetivo
    {
        return match ($this) {
            self::TiempoAtencionDias => ModuloObjetivo::TiemposAtencion,
            self::PromedioHorasMantenimiento,
            self::PromedioHorasCalibracion => ModuloObjetivo::DesempenoTecnicos,
            self::CumplimientoPorcentaje => ModuloObjetivo::Cumplimiento,
        };
    }

    /**
     * Get the label shown in the goals form.
     */
    public function label(): string
    {
        return match ($this) {
            self::TiempoAtencionDias => 'Tiempo promedio de atención',
            self::PromedioHorasMantenimiento => 'Tiempo promedio de mantenimiento',
            self::PromedioHorasCalibracion => 'Tiempo promedio de calibración',
            self::CumplimientoPorcentaje => 'Cumplimiento esperado',
        };
    }

    /**
     * Unidad del valor, para el formulario y los gráficos.
     */
    public function unidad(): string
    {
        return match ($this) {
            self::TiempoAtencionDias => 'días',
            self::PromedioHorasMantenimiento,
            self::PromedioHorasCalibracion => 'horas',
            self::CumplimientoPorcentaje => '%',
        };
    }

    /**
     * Cómo se escribe y se muestra el valor: "horas" (en horas y minutos, se guarda en
     * horas), "dias" o "porcentaje".
     */
    public function formato(): string
    {
        return match ($this) {
            self::TiempoAtencionDias => 'dias',
            self::PromedioHorasMantenimiento,
            self::PromedioHorasCalibracion => 'horas',
            self::CumplimientoPorcentaje => 'porcentaje',
        };
    }

    /**
     * Valor máximo permitido (el porcentaje no pasa de 100); null si no tiene tope.
     */
    public function maximo(): ?int
    {
        return $this === self::CumplimientoPorcentaje ? 100 : null;
    }
}
