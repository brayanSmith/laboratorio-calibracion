import { router } from '@inertiajs/react';
import { FileText, Send } from 'lucide-react';
import { useState } from 'react';
import RecordatorioController from '@/actions/App/Modules/Recordatorios/Http/Controllers/RecordatorioController';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ClienteRecordatorio, TipoPlantillaRecordatorio } from '@/types';

const TODOS = 'todos';

/**
 * Vista previa del PDF y envío de prueba de un tipo de correo (a clientes o resumen por
 * bahía). Con la lista de clientes se puede elegir de cuál ver el PDF, tal como le
 * llegaría. La prueba no es un formulario propio porque vive dentro del de configuración.
 */
export default function AccionesPrueba({
    tipo,
    puedeEnviar,
    clientes,
}: {
    tipo: TipoPlantillaRecordatorio;
    puedeEnviar: boolean;
    clientes?: ClienteRecordatorio[];
}) {
    const [enviando, setEnviando] = useState(false);
    const [cliente, setCliente] = useState(TODOS);
    const urlVistaPrevia = RecordatorioController.vistaPrevia.url(
        tipo,
        cliente === TODOS ? undefined : { query: { cliente } },
    );

    return (
        <div className="flex flex-wrap items-center gap-2 pt-1">
            {clientes ? (
                <Select value={cliente} onValueChange={setCliente}>
                    <SelectTrigger
                        size="sm"
                        className="w-full sm:w-64"
                        aria-label="Cliente de la vista previa"
                        data-test="recordatorio-vista-previa-cliente"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={TODOS}>
                            Todos los clientes ({clientes.length})
                        </SelectItem>
                        {clientes.map((opcion) => (
                            <SelectItem
                                key={opcion.id}
                                value={String(opcion.id)}
                            >
                                {opcion.nombre} · {opcion.equipos}{' '}
                                {opcion.equipos === 1 ? 'equipo' : 'equipos'}
                                {opcion.tiene_correo ? '' : ' · sin correo'}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : null}

            <Button variant="outline" size="sm" asChild>
                <a
                    href={urlVistaPrevia}
                    target="_blank"
                    rel="noreferrer"
                    data-test={`recordatorio-vista-previa-${tipo}`}
                >
                    <FileText /> Vista previa PDF
                </a>
            </Button>

            {puedeEnviar ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    loading={enviando}
                    onClick={() =>
                        router.post(
                            RecordatorioController.enviarPrueba.url(tipo),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setEnviando(true),
                                onFinish: () => setEnviando(false),
                            },
                        )
                    }
                    data-test={`recordatorio-prueba-${tipo}`}
                >
                    <Send /> Enviarme una prueba
                </Button>
            ) : null}
        </div>
    );
}
