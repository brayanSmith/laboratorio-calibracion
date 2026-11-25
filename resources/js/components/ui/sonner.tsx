import DetalleNoEliminablesDialog from '@/components/detalle-no-eliminables-dialog';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/** Fondo de color con letra blanca por tipo, con los mismos tonos de la interfaz. */
const CLASES_POR_TIPO = {
    success: '!border-emerald-600 !bg-emerald-500 !text-white',
    error: '!border-red-600 !bg-red-500 !text-white',
    warning: '!border-amber-600 !bg-amber-500 !text-white',
    info: '!border-sky-600 !bg-sky-500 !text-white',
    description: '!text-white/90',
    actionButton:
        '!bg-white !font-medium !text-neutral-900 hover:!bg-white/90',
    cancelButton: '!bg-white/20 !text-white',
    closeButton: '!border-white/40 !bg-white/20 !text-white',
};

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <>
            <Sonner
                theme={appearance}
                className="toaster group"
                position="bottom-right"
                // Cada tipo con su color de fondo (éxito verde, error rojo, aviso ámbar, info
                // azul) para que un error no se confunda con una confirmación.
                toastOptions={{ classNames: CLASES_POR_TIPO }}
                // En móvil, por encima de la barra inferior de atajos.
                mobileOffset={{ bottom: 136 }}
                style={
                    {
                        '--normal-bg': 'var(--popover)',
                        '--normal-text': 'var(--popover-foreground)',
                        '--normal-border': 'var(--border)',
                    } as React.CSSProperties
                }
                {...props}
            />
            <DetalleNoEliminablesDialog />
        </>
    );
}

export { Toaster };
