export type EntradaPendiente = {
    id: number;
    cantidad: number;
    precio_unitario: number;
    precio_total: number;
    codigo_compra: string | null;
};

export type Item = {
    id: number;
    codigo: string;
    nombre: string;
    fabricante: string | null;
    numero_parte: string | null;
    descripcion: string | null;
    ubicacion: string | null;
    precio_unitario: number | null;
    entradas: number;
    salidas: number;
    stock: number;
    entradas_pendientes: EntradaPendiente[];
};
