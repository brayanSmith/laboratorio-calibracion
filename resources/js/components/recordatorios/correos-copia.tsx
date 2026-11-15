import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const CORREO_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Lista de correos "con copia": se escriben y se agregan con Enter, coma o el botón.
 * Cada correo viaja al servidor como correos_copia[].
 */
export default function CorreosCopia({
    iniciales,
    errores,
}: {
    iniciales: string[];
    errores: Record<string, string>;
}) {
    const [correos, setCorreos] = useState<string[]>(iniciales);
    const [nuevo, setNuevo] = useState('');
    const [error, setError] = useState<string | null>(null);

    const erroresServidor = Object.entries(errores)
        .filter(([campo]) => campo.startsWith('correos_copia'))
        .map(([, mensaje]) => mensaje);

    function agregar() {
        const correo = nuevo.trim().toLowerCase();

        if (correo === '') {
            return;
        }

        if (!CORREO_VALIDO.test(correo)) {
            setError('Escribe un correo válido.');

            return;
        }

        if (correos.includes(correo)) {
            setError('Ese correo ya está en la lista.');

            return;
        }

        setCorreos([...correos, correo]);
        setNuevo('');
        setError(null);
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor="nuevo_correo_copia">Con copia a</Label>
            <p className="text-xs text-muted-foreground">
                Correos que no están asignados a una bahía, como un coordinador
                o gerente. Cada uno recibe el resumen de todos los equipos,
                agrupados por bahía.
            </p>

            <div className="flex max-w-md gap-2">
                <Input
                    id="nuevo_correo_copia"
                    // También se envía, por si se guarda sin pulsar "Agregar".
                    name="correos_copia[]"
                    type="email"
                    placeholder="correo@empresa.com"
                    value={nuevo}
                    onChange={(evento) => {
                        setNuevo(evento.target.value);
                        setError(null);
                    }}
                    onKeyDown={(evento) => {
                        if (evento.key === 'Enter' || evento.key === ',') {
                            evento.preventDefault();
                            agregar();
                        }
                    }}
                    data-test="recordatorio-nuevo-correo-copia"
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={agregar}
                    data-test="recordatorio-agregar-correo-copia"
                >
                    <Plus /> Agregar
                </Button>
            </div>
            <InputError message={error ?? erroresServidor[0]} />

            {correos.length > 0 ? (
                <ul className="flex flex-wrap gap-2">
                    {correos.map((correo) => (
                        <li
                            key={correo}
                            className="flex items-center gap-1 rounded-full border bg-muted py-0.5 pr-1 pl-3 text-sm"
                        >
                            {correo}
                            <input
                                type="hidden"
                                name="correos_copia[]"
                                value={correo}
                            />
                            <button
                                type="button"
                                className="rounded-full p-0.5 text-muted-foreground hover:bg-background hover:text-foreground"
                                onClick={() =>
                                    setCorreos(
                                        correos.filter(
                                            (actual) => actual !== correo,
                                        ),
                                    )
                                }
                                aria-label={`Quitar ${correo}`}
                            >
                                <X className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
