export type TipoDocumentoCliente = 'NIT' | 'CC' | 'CE' | 'PASAPORTE';

export type TipoDocumentoClienteOption = {
    value: TipoDocumentoCliente;
    label: string;
};

export type Cliente = {
    id: number;
    nombre: string;
    tipo_documento: TipoDocumentoCliente | null;
    numero_documento: string | null;
    email: string;
    telefono: string | null;
    direccion: string | null;
    equipos_count: number;
};
