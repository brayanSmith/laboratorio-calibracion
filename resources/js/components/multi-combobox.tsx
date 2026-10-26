import { Check, ChevronsUpDown, ListChecks, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ComboboxOption } from '@/components/combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type MultiComboboxOption = ComboboxOption & {
    /** Número que se muestra en un badge al lado de la opción (ej. equipos por bahía). */
    contador?: number;
};

type Props = {
    id?: string;
    /** Nombre del campo: cada valor elegido viaja como `name[]` con el formulario. */
    name?: string;
    value: string[];
    onValueChange: (value: string[]) => void;
    options: MultiComboboxOption[];
    /** Texto de ayuda del badge del contador (se muestra al pasar el mouse). */
    tituloContador?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    /** Cómo se llama a varias opciones elegidas en el botón, ej. "bahías". */
    plural?: string;
    disabled?: boolean;
    className?: string;
};

/** Texto sin mayúsculas ni tildes, para buscar "bahia" y encontrar "Bahía". */
const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

/**
 * Select con buscador que permite elegir varias opciones. "Seleccionar todo" marca todas
 * las opciones que coinciden con la búsqueda (o todas, si no se busca nada), para no
 * tener que marcarlas una por una.
 */
export default function MultiCombobox({
    id,
    name,
    value,
    onValueChange,
    options,
    tituloContador,
    placeholder = 'Selecciona una o varias opciones',
    searchPlaceholder = 'Buscar...',
    emptyMessage = 'Sin resultados.',
    plural = 'opciones',
    disabled,
    className,
}: Props) {
    const [open, setOpen] = useState(false);
    const [busqueda, setBusqueda] = useState('');
    const triggerRef = useRef<HTMLButtonElement>(null);
    const [triggerWidth, setTriggerWidth] = useState<number>();

    useEffect(() => {
        if (open && triggerRef.current) {
            setTriggerWidth(triggerRef.current.offsetWidth);
        }
    }, [open]);

    const filtradas = useMemo(
        () =>
            options.filter((option) =>
                normalizar(option.label).includes(normalizar(busqueda.trim())),
            ),
        [options, busqueda],
    );

    const seleccionadas = options.filter((option) =>
        value.includes(option.id.toString()),
    );
    const todasFiltradasSeleccionadas =
        filtradas.length > 0 &&
        filtradas.every((option) => value.includes(option.id.toString()));

    const alternar = (optionId: string) =>
        onValueChange(
            value.includes(optionId)
                ? value.filter((actual) => actual !== optionId)
                : [...value, optionId],
        );

    const alternarFiltradas = () => {
        const idsFiltrados = filtradas.map((option) => option.id.toString());

        onValueChange(
            todasFiltradasSeleccionadas
                ? value.filter((actual) => !idsFiltrados.includes(actual))
                : [...new Set([...value, ...idsFiltrados])],
        );
    };

    const etiqueta =
        seleccionadas.length === 0
            ? placeholder
            : seleccionadas.length <= 2
              ? seleccionadas.map((option) => option.label).join(', ')
              : `${seleccionadas.length} ${plural} seleccionadas`;

    return (
        <>
            {name
                ? value.map((valor) => (
                      <input
                          key={valor}
                          type="hidden"
                          name={`${name}[]`}
                          value={valor}
                      />
                  ))
                : null}
            <Popover
                open={open}
                onOpenChange={(abierto) => {
                    setOpen(abierto);

                    if (!abierto) {
                        setBusqueda('');
                    }
                }}
            >
                <PopoverTrigger asChild>
                    <Button
                        ref={triggerRef}
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        disabled={disabled}
                        className={cn(
                            'w-full justify-between font-normal',
                            seleccionadas.length === 0 &&
                                'text-muted-foreground',
                            className,
                        )}
                    >
                        <span className="min-w-0 flex-1 truncate text-left">
                            {etiqueta}
                        </span>
                        <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="p-0"
                    style={{ width: triggerWidth }}
                    align="start"
                >
                    <Command shouldFilter={false}>
                        <CommandInput
                            placeholder={searchPlaceholder}
                            value={busqueda}
                            onValueChange={setBusqueda}
                        />
                        <CommandList>
                            <CommandEmpty>{emptyMessage}</CommandEmpty>
                            {filtradas.length > 0 ? (
                                <CommandGroup>
                                    <CommandItem
                                        value="__seleccionar-todo__"
                                        onSelect={alternarFiltradas}
                                        data-test="multi-combobox-seleccionar-todo"
                                    >
                                        <ListChecks className="mr-2 size-4" />
                                        {todasFiltradasSeleccionadas
                                            ? 'Quitar'
                                            : 'Seleccionar'}{' '}
                                        {busqueda.trim()
                                            ? `las ${filtradas.length} filtradas`
                                            : 'todas'}
                                    </CommandItem>
                                    {value.length > 0 ? (
                                        <CommandItem
                                            value="__limpiar__"
                                            onSelect={() => onValueChange([])}
                                        >
                                            <X className="mr-2 size-4" />
                                            Limpiar selección ({value.length})
                                        </CommandItem>
                                    ) : null}
                                </CommandGroup>
                            ) : null}
                            <CommandSeparator />
                            <CommandGroup>
                                {filtradas.map((option) => {
                                    const optionId = option.id.toString();
                                    const marcada = value.includes(optionId);

                                    return (
                                        <CommandItem
                                            key={option.id}
                                            value={optionId}
                                            onSelect={() => alternar(optionId)}
                                        >
                                            <Check
                                                className={cn(
                                                    'mr-2 size-4',
                                                    marcada
                                                        ? 'opacity-100'
                                                        : 'opacity-0',
                                                )}
                                            />
                                            <span className="min-w-0 flex-1 truncate">
                                                {option.label}
                                            </span>
                                            {option.contador !== undefined ? (
                                                <Badge
                                                    variant={
                                                        option.contador > 0
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                    className="ml-2"
                                                    title={tituloContador}
                                                    data-test="multi-combobox-contador"
                                                >
                                                    {option.contador}
                                                </Badge>
                                            ) : null}
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </>
    );
}
