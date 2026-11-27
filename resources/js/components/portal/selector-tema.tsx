import type { LucideIcon } from 'lucide-react';
import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const TEMAS: { valor: Appearance; etiqueta: string; icono: LucideIcon }[] = [
    { valor: 'light', etiqueta: 'Claro', icono: Sun },
    { valor: 'dark', etiqueta: 'Oscuro', icono: Moon },
    { valor: 'system', etiqueta: 'Según el dispositivo', icono: Monitor },
];

/** Menú del portal para elegir tema claro, oscuro o el del dispositivo (se recuerda en el navegador). */
export default function SelectorTema() {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const IconoActual = resolvedAppearance === 'dark' ? Moon : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label="Cambiar tema"
                    title="Cambiar tema"
                    data-test="portal-tema"
                >
                    <IconoActual className="size-4" />
                    <span className="sr-only sm:not-sr-only">Tema</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {TEMAS.map(({ valor, etiqueta, icono: Icono }) => (
                    <DropdownMenuItem
                        key={valor}
                        onSelect={() => updateAppearance(valor)}
                    >
                        <Icono className="size-4" />
                        {etiqueta}
                        {appearance === valor ? (
                            <Check className="ml-auto size-4" />
                        ) : null}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
