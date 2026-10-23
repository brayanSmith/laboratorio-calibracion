export type Empresa = {
    id: number;
    nit: string;
    nombre: string;
    direccion: string;
    telefono: string;
    /** Laboratorio propio: el lugar de las calibraciones locales (sale en el certificado). */
    laboratorio_nombre: string | null;
    laboratorio_direccion: string | null;
    laboratorio_email: string | null;
    laboratorio_telefono: string | null;
    logo_url: string | null;
    /** Color hexadecimal del tema (botones y acentos), o null para el predeterminado. */
    color_tema: string | null;
    /** Color hexadecimal de la barra de navegación, o null para el predeterminado. */
    color_nav: string | null;
};
