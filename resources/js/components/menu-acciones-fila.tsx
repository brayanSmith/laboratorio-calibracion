import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { ModoBotonAccionProvider } from '@/components/boton-accion';
import { posicionMenuFila } from '@/lib/menu-acciones-fila';
import { cn } from '@/lib/utils';

type Props = {
    /** Punto de la pantalla donde se hizo clic en la fila. */
    clic: { x: number; y: number };
    visible: boolean;
    onCerrar: () => void;
    /** Las acciones de la fila (lo mismo que su columna de acciones). */
    children: ReactNode;
};

/**
 * Barra horizontal con las acciones de una fila, junto al punto donde se hizo clic, para no
 * tener que desplazarse hasta la columna de acciones. Sus botones son iconos que se
 * expanden con su nombre al pasar el cursor.
 *
 * Al cerrarse solo se oculta: algunas acciones abren una modal que vive dentro del propio
 * botón, y si la barra se desmontara esa modal se cerraría al instante.
 */
export default function MenuAccionesFila({
    clic,
    visible,
    onCerrar,
    children,
}: Props) {
    const menuRef = useRef<HTMLDivElement>(null);
    const [posicion, setPosicion] = useState<{ left: number; top: number }>();
    const [tieneAcciones, setTieneAcciones] = useState(true);

    useLayoutEffect(() => {
        const menu = menuRef.current;

        if (!visible || !menu) {
            return;
        }

        // Sin permisos para ninguna acción la fila no tiene botones: no hay menú que mostrar.
        setTieneAcciones(menu.querySelector('button, a') !== null);
        setPosicion(
            posicionMenuFila(
                clic,
                { ancho: menu.offsetWidth, alto: menu.offsetHeight },
                { ancho: window.innerWidth, alto: window.innerHeight },
            ),
        );
    }, [clic, visible]);

    useEffect(() => {
        if (!visible) {
            return;
        }

        const alPresionarFuera = (evento: PointerEvent) => {
            if (!menuRef.current?.contains(evento.target as Node)) {
                onCerrar();
            }
        };
        const alPresionarTecla = (evento: KeyboardEvent) => {
            if (evento.key === 'Escape') {
                onCerrar();
            }
        };

        document.addEventListener('pointerdown', alPresionarFuera, true);
        document.addEventListener('keydown', alPresionarTecla);
        window.addEventListener('scroll', onCerrar, true);
        window.addEventListener('resize', onCerrar);

        return () => {
            document.removeEventListener('pointerdown', alPresionarFuera, true);
            document.removeEventListener('keydown', alPresionarTecla);
            window.removeEventListener('scroll', onCerrar, true);
            window.removeEventListener('resize', onCerrar);
        };
    }, [visible, onCerrar]);

    const mostrar = visible && tieneAcciones && posicion !== undefined;

    return createPortal(
        <div
            ref={menuRef}
            role="toolbar"
            aria-label="Acciones de la fila"
            aria-hidden={!mostrar}
            className={cn(
                // Del color de la aplicación (el de la empresa, con su texto legible encima).
                'fixed z-40 flex items-center gap-1 rounded-lg border border-primary bg-primary p-1 text-primary-foreground shadow-lg',
                // Los botones sin color propio (los de color, como "Iniciar", lo conservan)
                // se ven en el color del texto, con un resaltado suave al pasar el cursor.
                '[&_[data-slot=button]]:border-primary-foreground/30 [&_[data-slot=button]]:bg-transparent [&_[data-slot=button]]:text-primary-foreground [&_[data-slot=button]]:shadow-none [&_[data-slot=button]:hover]:bg-primary-foreground/15 [&_[data-slot=button]:hover]:text-primary-foreground',
                mostrar
                    ? 'animate-in fade-in-0 zoom-in-95'
                    : 'pointer-events-none invisible',
            )}
            style={
                posicion
                    ? { left: posicion.left, top: posicion.top }
                    : { left: -9999, top: -9999 }
            }
            data-test="menu-acciones-fila"
            // Al elegir una acción la barra se oculta (después de que el botón haga lo suyo).
            onClick={(evento) => {
                if ((evento.target as HTMLElement).closest('button, a')) {
                    window.setTimeout(onCerrar);
                }
            }}
        >
            <ModoBotonAccionProvider value="expandir">
                {children}
            </ModoBotonAccionProvider>
        </div>,
        document.body,
    );
}
