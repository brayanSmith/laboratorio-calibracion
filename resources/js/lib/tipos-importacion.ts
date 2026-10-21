import type { TipoImportacion } from '@/types';

/**
 * Lo que se puede importar desde un CSV (ver TipoImportacion en el servidor), en el orden
 * del selector. Cada tipo se muestra solo a quien tiene su permiso; `ruta` es el listado que
 * se recarga al terminar la importación si el usuario lo está viendo.
 */
export const tiposImportacion: {
    value: TipoImportacion;
    label: string;
    permiso: string;
    ruta: string;
}[] = [
    {
        value: 'equipos',
        label: 'Equipos',
        permiso: 'equipos.crear',
        ruta: '/equipos',
    },
    { value: 'areas', label: 'Áreas', permiso: 'areas.crear', ruta: '/areas' },
    {
        value: 'bahias',
        label: 'Bahías',
        permiso: 'bahias.crear',
        ruta: '/bahias',
    },
    {
        value: 'checklist-mantenimiento',
        label: 'Checklist de mantenimiento',
        // El checklist se gestiona al editar el tipo de equipo.
        permiso: 'tipos-equipo.editar',
        ruta: '/tipos-equipo',
    },
    {
        value: 'clientes',
        label: 'Clientes',
        permiso: 'clientes.crear',
        ruta: '/clientes',
    },
    {
        value: 'empresas-terceras',
        label: 'Empresas terceras',
        permiso: 'empresas-terceras.crear',
        ruta: '/empresas-terceras',
    },
    {
        value: 'fabricantes',
        label: 'Fabricantes',
        permiso: 'fabricantes.crear',
        ruta: '/fabricantes',
    },
    { value: 'items', label: 'Ítems', permiso: 'items.crear', ruta: '/items' },
    {
        value: 'laboratorios',
        label: 'Laboratorios',
        permiso: 'laboratorios.crear',
        ruta: '/laboratorios',
    },
    {
        value: 'novedades',
        label: 'Novedades',
        permiso: 'novedades.crear',
        ruta: '/novedades',
    },
    {
        value: 'procedimientos-calibracion',
        label: 'Procedimientos de calibración',
        permiso: 'procedimientos-calibracion.crear',
        ruta: '/procedimientos-calibracion',
    },
    {
        value: 'tipos-equipo',
        label: 'Tipos de equipo',
        permiso: 'tipos-equipo.crear',
        ruta: '/tipos-equipo',
    },
];
