import { useRef, useState } from 'react';
import SignatureCanvasImport from 'react-signature-canvas';
import { Button } from '@/components/ui/button';

// react-signature-canvas empaqueta un UMD dentro de otro: en Vite, el import por
// defecto llega como { __esModule: true, default: <clase real> } en vez de la clase
// directamente, lo que rompe el render ("Element type is invalid"). Se desenvuelve
// aquí una sola vez.
const SignatureCanvas =
    (
        SignatureCanvasImport as unknown as {
            default?: typeof SignatureCanvasImport;
        }
    ).default ?? SignatureCanvasImport;

type Props = {
    name: string;
};

/**
 * Canvas para dibujar una firma con el dedo o el mouse. El trazo se convierte a un
 * PNG recortado y se deposita en un input de archivo oculto con el mismo `name`, para
 * que viaje junto con el resto del formulario como cualquier otro archivo subido.
 */
export default function FirmaCanvas({ name }: Props) {
    const canvasRef = useRef<SignatureCanvasImport>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [vacio, setVacio] = useState(true);

    const actualizarArchivo = () => {
        const canvas = canvasRef.current;
        const fileInput = fileInputRef.current;

        if (!canvas || !fileInput) {
            return;
        }

        if (canvas.isEmpty()) {
            setVacio(true);
            fileInput.value = '';
            return;
        }

        setVacio(false);

        canvas.getTrimmedCanvas().toBlob((blob) => {
            if (!blob) {
                return;
            }

            const archivo = new File([blob], 'firma.png', {
                type: 'image/png',
            });
            const datos = new DataTransfer();
            datos.items.add(archivo);
            fileInput.files = datos.files;
        }, 'image/png');
    };

    const limpiar = () => {
        canvasRef.current?.clear();
        setVacio(true);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    return (
        <div className="space-y-2">
            <div className="flex justify-center overflow-hidden rounded-md border bg-white">
                <SignatureCanvas
                    ref={canvasRef}
                    penColor="black"
                    canvasProps={{
                        width: 440,
                        height: 160,
                        className: 'touch-none',
                    }}
                    onEnd={actualizarArchivo}
                />
            </div>
            <div className="flex items-center justify-between">
                <p className="text-xs text-muted-foreground">
                    Firme dentro del recuadro
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={limpiar}
                    disabled={vacio}
                    data-test="firma-canvas-limpiar"
                >
                    Limpiar
                </Button>
            </div>
            <input
                ref={fileInputRef}
                type="file"
                name={name}
                className="hidden"
                data-test="firma-canvas-input"
            />
        </div>
    );
}
