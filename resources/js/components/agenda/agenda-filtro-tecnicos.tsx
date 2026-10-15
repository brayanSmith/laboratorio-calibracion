import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type TecnicoAgenda = { id: number; nombre: string };

type Props = {
    tecnicos: TecnicoAgenda[];
    seleccionados: number[];
    onChange: (seleccionados: number[]) => void;
};

/** Selector múltiple de técnicos: sin ninguno elegido se muestran las tareas de todos. */
export default function AgendaFiltroTecnicos({
    tecnicos,
    seleccionados,
    onChange,
}: Props) {
    const [open, setOpen] = useState(false);

    const alternar = (id: number) =>
        onChange(
            seleccionados.includes(id)
                ? seleccionados.filter((valor) => valor !== id)
                : [...seleccionados, id],
        );

    const etiqueta =
        seleccionados.length === 0
            ? 'Todos los técnicos'
            : seleccionados.length === 1
              ? (tecnicos.find((tecnico) => tecnico.id === seleccionados[0])
                    ?.nombre ?? '1 técnico')
              : `${seleccionados.length} técnicos`;

    return (
        <div className="flex items-center gap-1">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        role="combobox"
                        aria-expanded={open}
                        className="w-56 justify-between font-normal"
                        data-test="agenda-filtro-tecnicos"
                    >
                        <span className="truncate">{etiqueta}</span>
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-64 p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Buscar técnico..." />
                        <CommandList>
                            <CommandEmpty>Sin resultados.</CommandEmpty>
                            <CommandGroup>
                                {tecnicos.map((tecnico) => (
                                    <CommandItem
                                        key={tecnico.id}
                                        value={tecnico.nombre}
                                        onSelect={() => alternar(tecnico.id)}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                seleccionados.includes(
                                                    tecnico.id,
                                                )
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {tecnico.nombre}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {seleccionados.length > 0 ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    aria-label="Quitar filtro de técnicos"
                    onClick={() => onChange([])}
                    data-test="agenda-filtro-tecnicos-limpiar"
                >
                    <X className="size-4" />
                </Button>
            ) : null}
        </div>
    );
}
