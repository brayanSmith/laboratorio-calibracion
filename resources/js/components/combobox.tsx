import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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

export type ComboboxOption = {
    id: number | string;
    label: string;
};

type Props = {
    id?: string;
    name?: string;
    value?: string;
    onValueChange?: (value: string) => void;
    options: ComboboxOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    disabled?: boolean;
    className?: string;
};

// A partir de este número de opciones se muestra el buscador; con pocas
// opciones basta con desplazarse por la lista.
const UMBRAL_BUSCADOR = 10;

/**
 * Select con buscador (Popover + Command) para listas largas. Se comporta
 * como un <Select> normal: pásale `name` para que su valor viaje con el
 * formulario.
 */
export default function Combobox({
    id,
    name,
    value,
    onValueChange,
    options,
    placeholder = 'Selecciona una opción',
    searchPlaceholder = 'Buscar...',
    emptyMessage = 'Sin resultados.',
    disabled,
    className,
}: Props) {
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const [triggerWidth, setTriggerWidth] = useState<number>();

    useEffect(() => {
        if (open && triggerRef.current) {
            setTriggerWidth(triggerRef.current.offsetWidth);
        }
    }, [open]);

    const seleccionado = options.find(
        (option) => option.id.toString() === value,
    );

    return (
        <>
            {name ? (
                <input type="hidden" name={name} value={value ?? ''} />
            ) : null}
            <Popover open={open} onOpenChange={setOpen}>
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
                            !seleccionado && 'text-muted-foreground',
                            className,
                        )}
                    >
                        {seleccionado?.label ?? placeholder}
                        <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="p-0"
                    style={{ width: triggerWidth }}
                    align="start"
                >
                    <Command>
                        {options.length > UMBRAL_BUSCADOR ? (
                            <CommandInput placeholder={searchPlaceholder} />
                        ) : null}
                        <CommandList>
                            <CommandEmpty>{emptyMessage}</CommandEmpty>
                            <CommandGroup>
                                {options.map((option) => (
                                    <CommandItem
                                        key={option.id}
                                        value={option.label}
                                        onSelect={() => {
                                            onValueChange?.(
                                                option.id.toString(),
                                            );
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'mr-2 size-4',
                                                option.id.toString() === value
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {option.label}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </>
    );
}
