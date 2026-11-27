import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    FileCheck2,
    History,
    UsersRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import BrandingHead from '@/components/branding-head';
import { home } from '@/routes';

/** Muestra de cómo se ven los equipos en el portal (solo decorativa). */
const MUESTRA = [
    {
        codigo: 'EQ-0142',
        estado: 'Al día',
        icono: CheckCircle2,
        clase: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-950/50',
    },
    {
        codigo: 'EQ-0087',
        estado: 'Próximo a vencer',
        icono: Clock,
        clase: 'text-amber-600 bg-amber-100 dark:bg-amber-950/50',
    },
    {
        codigo: 'EQ-0031',
        estado: 'Vencido',
        icono: AlertTriangle,
        clase: 'text-red-600 bg-red-100 dark:bg-red-950/50',
    },
];

/**
 * Marco de las pantallas de acceso del portal de clientes (entrar, crear cuenta, recuperar
 * contraseña). Va a propósito distinto del login del laboratorio: panel claro a la derecha,
 * con lo que el cliente verá adentro, y la etiqueta "Portal de clientes" bien visible.
 */
export default function PortalAuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
}) {
    const { plataforma } = usePage().props;

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[1fr_1.1fr]">
            <BrandingHead />

            <main className="flex items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col gap-5">
                            <Link
                                href={home()}
                                className="flex items-center gap-2"
                            >
                                {plataforma.logo_url ? (
                                    <img
                                        src={plataforma.logo_url}
                                        alt={`Logo de ${plataforma.nombre}`}
                                        className="size-10 rounded-lg bg-white object-contain"
                                    />
                                ) : (
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                        <AppLogoIcon className="size-5 fill-current" />
                                    </div>
                                )}
                                <span className="font-semibold">
                                    {plataforma.nombre}
                                </span>
                            </Link>

                            <span
                                className="inline-flex w-fit items-center gap-1.5 rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold tracking-wide text-sky-800 uppercase dark:bg-sky-950/60 dark:text-sky-300"
                                data-test="portal-auth-badge"
                            >
                                <UsersRound className="size-3.5" />
                                Portal de clientes
                            </span>

                            <div className="space-y-2">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    {title}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        </div>
                        {children}
                    </div>
                </div>
            </main>

            <aside className="relative hidden flex-col justify-center gap-10 overflow-hidden border-l bg-gradient-to-br from-sky-50 via-background to-emerald-50 p-12 lg:flex dark:from-sky-950/30 dark:to-emerald-950/20">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-sky-300/20 blur-3xl"
                />

                <div className="relative max-w-md space-y-4">
                    <h2 className="text-3xl leading-tight font-semibold text-balance">
                        Tus equipos al día, en un solo lugar.
                    </h2>
                    <ul className="space-y-3 text-sm text-muted-foreground">
                        <li className="flex items-center gap-3">
                            <Clock className="size-4 shrink-0 text-sky-600" />
                            Estado y vencimientos de cada equipo
                        </li>
                        <li className="flex items-center gap-3">
                            <FileCheck2 className="size-4 shrink-0 text-sky-600" />
                            Certificados e informes para descargar
                        </li>
                        <li className="flex items-center gap-3">
                            <History className="size-4 shrink-0 text-sky-600" />
                            El historial de todos sus servicios
                        </li>
                    </ul>
                </div>

                <div
                    aria-hidden
                    className="relative max-w-md space-y-2 rounded-xl border bg-background/80 p-4 shadow-sm backdrop-blur"
                >
                    <p className="mb-3 text-xs font-medium text-muted-foreground">
                        Mis equipos
                    </p>
                    {MUESTRA.map(({ codigo, estado, icono: Icono, clase }) => (
                        <div
                            key={codigo}
                            className="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                        >
                            <span className="font-medium">{codigo}</span>
                            <span
                                className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ${clase}`}
                            >
                                <Icono className="size-3.5" />
                                {estado}
                            </span>
                        </div>
                    ))}
                </div>
            </aside>
        </div>
    );
}
