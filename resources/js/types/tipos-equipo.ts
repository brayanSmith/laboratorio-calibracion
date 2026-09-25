export type TipoMantenimiento = 'A' | 'B';

export type TipoEquipoCheckListItem = {
    id: number;
    nombre: string;
};

export type TipoEquipo = {
    id: number;
    nombre: string;
    tipo_mantenimiento: TipoMantenimiento;
    equipos_count: number;
    checklist: TipoEquipoCheckListItem[];
};
