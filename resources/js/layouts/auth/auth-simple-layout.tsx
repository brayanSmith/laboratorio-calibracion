import { Link, usePage } from '@inertiajs/react';
import { Gauge, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

function PlatformMark({ className }: { className?: string }) {
    const { plataforma } = usePage().props;

    return plataforma.logo_url ? (
        <img
            src={plataforma.logo_url}
            alt={`Logo de ${plataforma.nombre}`}
            className={`rounded-lg bg-white object-contain ${className ?? ''}`}
        />
    ) : (
        <div
            className={`flex items-center justify-center rounded-lg bg-primary text-primary-foreground ${className ?? ''}`}
        >
            <AppLogoIcon className="size-1/2 fill-current" />
        </div>
    );
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { plataforma } = usePage().props;

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[1.1fr_1fr]">
            <aside className="relative hidden flex-col justify-between overflow-hidden bg-primary p-12 text-primary-foreground lg:flex">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,currentColor_1px,transparent_1px),linear-gradient(to_bottom,currentColor_1px,transparent_1px)] bg-[size:48px_48px] opacity-[0.07] [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -right-24 -bottom-24 size-96 rounded-full bg-primary-foreground/10 blur-3xl"
                />

                <Link
                    href={home()}
                    className="relative flex items-center gap-3 text-lg font-semibold"
                >
                    <PlatformMark className="size-10" />
                    {plataforma.nombre}
                </Link>

                <div className="relative max-w-md space-y-6">
                    <h2 className="text-3xl leading-tight font-semibold text-balance">
                        Calibraciones precisas, resultados trazables.
                    </h2>
                    <ul className="space-y-3 text-sm text-primary-foreground/80">
                        <li className="flex items-center gap-3">
                            <Gauge className="size-4 shrink-0" />
                            Control de equipos, calibraciones y mantenimientos
                        </li>
                        <li className="flex items-center gap-3">
                            <ShieldCheck className="size-4 shrink-0" />
                            Información segura y separada por laboratorio
                        </li>
                    </ul>
                </div>

                <p className="relative text-xs text-primary-foreground/60">
                    © {new Date().getFullYear()} {plataforma.nombre}
                </p>
            </aside>

            <main className="flex items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col gap-4">
                            <Link
                                href={home()}
                                className="flex items-center gap-2 lg:hidden"
                            >
                                <PlatformMark className="size-10" />
                                <span className="font-semibold">
                                    {plataforma.nombre}
                                </span>
                            </Link>

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
        </div>
    );
}
