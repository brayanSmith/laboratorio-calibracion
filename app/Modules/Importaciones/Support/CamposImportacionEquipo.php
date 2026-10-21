<?php

namespace App\Modules\Importaciones\Support;

/**
 * Campos del equipo que se pueden relacionar con las columnas de un CSV de importación
 * (ver ImportarFilaEquipo).
 */
class CamposImportacionEquipo
{
    /**
     * Campos disponibles en el orden en que se muestran en la modal de relacionar columnas.
     *
     * @return list<array{clave: string, etiqueta: string, requerido: bool, ayuda: string|null}>
     */
    public static function todos(): array
    {
        return [
            self::campo('codigo', 'Código', true),
            self::campo('tipo_equipo', 'Tipo de equipo', true, 'Si no existe, se crea.'),
            self::campo('tipo_mantenimiento', 'Tipo de mantenimiento', false, 'A o B, solo al crear un tipo de equipo nuevo. Por defecto: A.'),
            self::campo('tipo_tecnologia', 'Tipo de tecnología', true, 'ANALOGICO o DIGITAL.'),
            self::campo('modelo', 'Modelo', true),
            self::campo('fabricante', 'Fabricante', true, 'Si no existe, se crea.'),
            self::campo('numero_serie', 'Número de serie', true),
            self::campo('cliente', 'Cliente', true, 'Nombre; se usa al crear un cliente nuevo.'),
            self::campo('cliente_numero_documento', 'Documento del cliente', true, 'Identifica al cliente: si no existe uno con este número, se crea.'),
            self::campo('cliente_tipo_documento', 'Tipo de documento del cliente', false, 'NIT, CC, CE o PASAPORTE, solo al crear un cliente nuevo. Por defecto: NIT.'),
            self::campo('cliente_email', 'Email del cliente', false, 'Solo al crear un cliente nuevo.'),
            self::campo('area', 'Área', true, 'Si no existe, se crea.'),
            self::campo('bahia', 'Bahía', true, 'Si no existe en el área, se crea.'),
            self::campo('condicion_actual', 'Condición actual', true),
            self::campo('notas', 'Notas'),
            self::campo('activo', 'Activo', false, 'Sí/No. Por defecto: Sí.'),
            self::campo('patron_referencia', 'Patrón de referencia', false, 'Sí/No. Por defecto: No.'),
            self::campo('requiere_programacion', 'Requiere programación', false, 'Sí/No. Por defecto: Sí.'),
            self::campo('servicio_mantenimiento', 'Mantenimiento', false, 'Sí/No: si la primera programación incluye mantenimiento.'),
            self::campo('servicio_calibracion', 'Calibración', false, 'Sí/No: si la primera programación incluye calibración. Vacías las dos, sale del tipo de equipo (A o B).'),
            self::campo('intervalo_servicio', 'Intervalo de servicio', false, 'Número. Con él se crea la primera programación.'),
            self::campo('intervalo_unidad', 'Unidad del intervalo', false, 'DIAS, SEMANAS o MESES.'),
            self::campo('fecha_ultimo_servicio', 'Fecha del último servicio', false, 'DD/MM/AAAA (también acepta AAAA-MM-DD). Obligatoria si no viene la del próximo servicio.'),
            self::campo('fecha_proximo_servicio', 'Fecha del próximo servicio', false, 'DD/MM/AAAA (también acepta AAAA-MM-DD). Si no viene, se calcula con el último servicio y el intervalo.'),
            self::campo('dias_plazo_vencimiento', 'Días de aviso antes del vencimiento', false, 'Por defecto: 30.'),
            self::campo('pais_procedencia', 'País de procedencia'),
            self::campo('numero_activo', 'Número de activo'),
            self::campo('proveedor', 'Proveedor'),
            self::campo('costo_usd', 'Costo en USD'),
            self::campo('fecha_adquisicion', 'Fecha de adquisición', false, 'DD/MM/AAAA (también acepta AAAA-MM-DD).'),
            self::campo('tipo_magnitud', 'Tipo de magnitud', false, 'Si no existe, se crea. Con él se exige la especificación técnica.'),
            self::campo('unidad_medida', 'Unidad de medida', false, 'Nombre o símbolo. Si no existe una unidad con este símbolo o nombre, se crea.'),
            self::campo('simbolo_unidad', 'Símbolo de la unidad', false, 'Se busca primero por este símbolo; si no existe, se usa al crear la unidad.'),
            self::campo('alcance_indicacion', 'Alcance de indicación', false, 'Ej. "0 a 10"; el símbolo de la unidad se agrega solo.'),
            self::campo('simbolo_precision', 'Símbolo de la precisión', false, '± (va adelante) o % (va al final) de la precisión.'),
            self::campo('precision', 'Precisión', false, 'Solo el número, ej. 0.5; el símbolo sale de la columna anterior.'),
            self::campo('resolucion', 'Resolución', false, 'Solo el número, con coma o punto decimal y máximo 4 decimales (ej. 0,0005): el símbolo se toma de la unidad de medida.'),
        ];
    }

    /**
     * Valores de la fila de ejemplo de la plantilla descargable, por campo.
     *
     * @return array<string, string>
     */
    public static function ejemplo(): array
    {
        return [
            'codigo' => 'EQ-0001',
            'tipo_equipo' => 'Manómetro',
            'tipo_mantenimiento' => 'A',
            'tipo_tecnologia' => 'DIGITAL',
            'modelo' => 'MG-100',
            'fabricante' => 'Fluke',
            'numero_serie' => 'SN-12345',
            'cliente' => 'Cliente de ejemplo',
            'cliente_numero_documento' => '900123456',
            'cliente_tipo_documento' => 'NIT',
            'cliente_email' => 'cliente@ejemplo.com',
            'area' => 'Planta principal',
            'bahia' => 'Bahía 1',
            'condicion_actual' => 'Bueno',
            'notas' => 'Fila de ejemplo: bórrala antes de importar',
            'activo' => 'Sí',
            'patron_referencia' => 'No',
            'requiere_programacion' => 'Sí',
            'servicio_mantenimiento' => 'Sí',
            'servicio_calibracion' => 'Sí',
            'intervalo_servicio' => '12',
            'intervalo_unidad' => 'MESES',
            'fecha_ultimo_servicio' => '15/01/2026',
            'fecha_proximo_servicio' => '15/01/2027',
            'dias_plazo_vencimiento' => '30',
            'pais_procedencia' => 'Colombia',
            'numero_activo' => 'ACT-0001',
            'proveedor' => 'Proveedor de ejemplo',
            'costo_usd' => '1250.50',
            'fecha_adquisicion' => '15/06/2025',
            'tipo_magnitud' => 'Presión',
            'unidad_medida' => 'Bar',
            'simbolo_unidad' => 'bar',
            'alcance_indicacion' => '0 a 10',
            'simbolo_precision' => '±',
            'precision' => '0.5',
            'resolucion' => '0.1',
        ];
    }

    /**
     * @return array{clave: string, etiqueta: string, requerido: bool, ayuda: string|null}
     */
    private static function campo(string $clave, string $etiqueta, bool $requerido = false, ?string $ayuda = null): array
    {
        return ['clave' => $clave, 'etiqueta' => $etiqueta, 'requerido' => $requerido, 'ayuda' => $ayuda];
    }
}
