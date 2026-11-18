import { Camera, ImagePlus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type { FotoRecepcion } from '@/types';

/** Fotos que puede tener cada equipo (igual que en UpdateEstadoIngresoRequest). */
const MAXIMO_FOTOS = 10;

/** Lado mayor de la foto ya reducida: suficiente como evidencia y liviana para subirla. */
const LADO_MAXIMO = 1600;

type FotoNueva = { clave: string; archivo: File; url: string };

type Props = {
    /**
     * Campo de las fotos nuevas, ej. fotos_recepcion[12]: cada una viaja en su propio input
     * con índice explícito (fotos_recepcion[12][0], [12][1]…). Con "[]" Inertia arma un
     * array disperso desde la clave numérica (el id) y, al pasarlo a FormData con archivos,
     * envía vacías las posiciones anteriores, que el servidor rechaza.
     */
    nombre: string;
    fotos: FotoRecepcion[];
    error?: string;
};

/**
 * Reduce la foto (las del celular pesan varios MB) a JPG de máximo LADO_MAXIMO px. Si el
 * navegador no puede procesarla, se sube tal cual.
 */
async function reducirFoto(archivo: File): Promise<File> {
    try {
        const imagen = await createImageBitmap(archivo);
        const escala = Math.min(
            1,
            LADO_MAXIMO / Math.max(imagen.width, imagen.height),
        );
        const lienzo = document.createElement('canvas');
        lienzo.width = Math.round(imagen.width * escala);
        lienzo.height = Math.round(imagen.height * escala);
        lienzo
            .getContext('2d')
            ?.drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
        imagen.close();

        const blob = await new Promise<Blob | null>((resolver) =>
            lienzo.toBlob(resolver, 'image/jpeg', 0.82),
        );

        if (!blob) {
            return archivo;
        }

        const nombre = archivo.name.replace(/\.[^.]+$/, '') || 'foto';

        return new File([blob], `${nombre}.jpg`, { type: 'image/jpeg' });
    } catch {
        return archivo;
    }
}

/**
 * Fotos de cómo llegó un equipo al recibir el ingreso. En el celular, "Tomar foto" abre
 * la cámara y "Galería" deja elegir varias. Las nuevas viajan en inputs de archivo ocultos
 * (ver `nombre`) y las guardadas que se quitan, en eliminar_fotos_recepcion[].
 */
export default function FotosRecepcionField({ nombre, fotos, error }: Props) {
    const [nuevas, setNuevas] = useState<FotoNueva[]>([]);
    const [quitadas, setQuitadas] = useState<number[]>([]);
    const [procesando, setProcesando] = useState(false);
    const camaraRef = useRef<HTMLInputElement>(null);
    const galeriaRef = useRef<HTMLInputElement>(null);

    const guardadas = fotos.filter((foto) => !quitadas.includes(foto.id));
    const disponibles = MAXIMO_FOTOS - guardadas.length - nuevas.length;

    // Libera las vistas previas al desmontar.
    const nuevasRef = useRef(nuevas);
    nuevasRef.current = nuevas;
    useEffect(
        () => () =>
            nuevasRef.current.forEach((foto) => URL.revokeObjectURL(foto.url)),
        [],
    );

    const agregar = async (evento: ChangeEvent<HTMLInputElement>) => {
        const elegidas = Array.from(evento.target.files ?? [])
            .filter((archivo) => archivo.type.startsWith('image/'))
            .slice(0, Math.max(0, disponibles));
        evento.target.value = '';

        if (elegidas.length === 0) {
            return;
        }

        setProcesando(true);
        const reducidas = await Promise.all(elegidas.map(reducirFoto));
        setProcesando(false);
        setNuevas((actuales) => [
            ...actuales,
            ...reducidas.map((archivo) => ({
                clave: crypto.randomUUID(),
                archivo,
                url: URL.createObjectURL(archivo),
            })),
        ]);
    };

    const quitarNueva = (clave: string) =>
        setNuevas((actuales) =>
            actuales.filter((foto) => {
                if (foto.clave === clave) {
                    URL.revokeObjectURL(foto.url);

                    return false;
                }

                return true;
            }),
        );

    return (
        <div className="grid gap-2" data-test="fotos-recepcion">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs font-medium">
                    Fotos de cómo llegó{' '}
                    <span className="font-normal text-muted-foreground">
                        ({guardadas.length + nuevas.length}/{MAXIMO_FOTOS})
                    </span>
                </p>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8"
                        disabled={disponibles <= 0}
                        loading={procesando}
                        onClick={() => camaraRef.current?.click()}
                        data-test="fotos-recepcion-camara"
                    >
                        {procesando ? null : <Camera />}
                        Tomar foto
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8"
                        disabled={disponibles <= 0 || procesando}
                        onClick={() => galeriaRef.current?.click()}
                    >
                        <ImagePlus /> Galería
                    </Button>
                </div>
            </div>

            {guardadas.length + nuevas.length > 0 ? (
                <ul className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                    {guardadas.map((foto) => (
                        <MiniaturaFoto
                            key={foto.id}
                            url={foto.url}
                            enlace
                            onQuitar={() =>
                                setQuitadas((actuales) => [
                                    ...actuales,
                                    foto.id,
                                ])
                            }
                        />
                    ))}
                    {nuevas.map((foto) => (
                        <MiniaturaFoto
                            key={foto.clave}
                            url={foto.url}
                            nueva
                            onQuitar={() => quitarNueva(foto.clave)}
                        />
                    ))}
                </ul>
            ) : (
                <p className="text-xs text-muted-foreground">
                    Opcional: toma fotos del equipo como evidencia de su estado
                    al recibirlo.
                </p>
            )}

            <InputError message={error} />

            <input
                ref={camaraRef}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={agregar}
            />
            <input
                ref={galeriaRef}
                type="file"
                accept="image/*"
                multiple
                className="hidden"
                onChange={agregar}
            />
            {nuevas.map((foto, indice) => (
                <ArchivoOculto
                    key={foto.clave}
                    nombre={`${nombre}[${indice}]`}
                    archivo={foto.archivo}
                />
            ))}
            {quitadas.map((id) => (
                <input
                    key={id}
                    type="hidden"
                    name="eliminar_fotos_recepcion[]"
                    value={id}
                />
            ))}
        </div>
    );
}

/** Input de archivo oculto con una sola foto, para que viaje con el formulario. */
function ArchivoOculto({ nombre, archivo }: { nombre: string; archivo: File }) {
    const ref = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!ref.current) {
            return;
        }

        const transferencia = new DataTransfer();
        transferencia.items.add(archivo);
        ref.current.files = transferencia.files;
    }, [archivo]);

    return (
        <input
            ref={ref}
            type="file"
            name={nombre}
            className="hidden"
            tabIndex={-1}
            aria-hidden
            data-test="foto-recepcion-archivo"
        />
    );
}

function MiniaturaFoto({
    url,
    enlace = false,
    nueva = false,
    onQuitar,
}: {
    url: string;
    enlace?: boolean;
    nueva?: boolean;
    onQuitar: () => void;
}) {
    const imagen = (
        <img
            src={url}
            alt="Foto de recepción del equipo"
            className="aspect-square w-full rounded-md border object-cover"
        />
    );

    return (
        <li className="relative" data-test="foto-recepcion">
            {enlace ? (
                <a href={url} target="_blank" rel="noreferrer">
                    {imagen}
                </a>
            ) : (
                imagen
            )}
            {nueva ? (
                <span className="absolute bottom-1 left-1 rounded bg-emerald-600 px-1 text-[10px] font-medium text-white">
                    Nueva
                </span>
            ) : null}
            <button
                type="button"
                onClick={onQuitar}
                className="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-destructive text-white shadow"
                aria-label="Quitar foto"
                data-test="quitar-foto-recepcion"
            >
                <X className="size-3" />
            </button>
        </li>
    );
}
