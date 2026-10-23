import type { TipoImportacion } from '@/types';

/**
 * Lo que se puede importar desde un CSV (ver TipoImportacion en el servidor), en el orden
 * del selector. Cada tipo se muestra solo a quien tiene su permiso de importación (ver
 * permisoImportacion()); `ruta` es el listado que se recarga al terminar la importación si
 * el usuario lo está viendo.
 */
export const tiposImportacion: {
    value: TipoImportacion;
    label: string;
    ruta: string;
}[] = [
    { value: 'equipos', label: 'Equipos', ruta: '/equipos' },
    { value: 'areas', label: 'Áreas', ruta: '/areas' },
    { value: 'bahias', label: 'Bahías', ruta: '/bahias' },
    {
        value: 'checklist-mantenimiento',
        label: 'Checklist de mantenimiento',
        ruta: '/tipos-equipo',
    },
    { value: 'clientes', label: 'Clientes', ruta: '/clientes' },
    {
        value: 'empresas-terceras',
        label: 'Laboratorios terceros',
        ruta: '/empresas-terceras',
    },
    { value: 'fabricantes', label: 'Fabricantes', ruta: '/fabricantes' },
    { value: 'items', label: 'Ítems', ruta: '/items' },
    { value: 'novedades', label: 'Novedades', ruta: '/novedades' },
    {
        value: 'procedimientos-calibracion',
        label: 'Procedimientos de calibración',
        ruta: '/procedimientos-calibracion',
    },
    { value: 'tipos-equipo', label: 'Tipos de equipo', ruta: '/tipos-equipo' },
];

/** Permiso del grupo "Importaciones" que habilita importar el tipo (ver TenantPermission). */
export function permisoImportacion(tipo: TipoImportacion): string {
    return `importaciones.${tipo}`;
}
