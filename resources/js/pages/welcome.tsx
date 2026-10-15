import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ClipboardCheck,
    Gauge,
    ShieldCheck,
    Wrench,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import BrandingHead from '@/components/branding-head';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const features = [
    {
        icon: Gauge,
        title: 'Calibraciones',
        description:
            'Registra y da seguimiento a cada calibración con sus alcances de medición y resultados.',
    },
    {
        icon: ClipboardCheck,
        title: 'Control de equipos',
        description:
            'Gestiona ingresos, despachos y el historial completo de cada equipo de tus clientes.',
    },
    {
        icon: CalendarDays,
        title: 'Agenda',
        description:
            'Planifica actividades y mantén al equipo coordinado con una agenda centralizada.',
    },
    {
        icon: Wrench,
        title: 'Mantenimientos',
        description:
            'Programa y documenta los mantenimientos para conservar la trazabilidad.',
    },
];

export default function Welcome() {
    const { auth, plataforma } = usePage().props;
    const nombre = plataforma.nombre;

    return (
        <>
            <Head title="Bienvenido" />
            <BrandingHead />
            <div className="relative flex min-h-screen flex-col overflow-hidden bg-background text-foreground">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,color-mix(in_oklab,var(--primary)_14%,transparent),transparent)]"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] bg-[size:48px_48px] opacity-40 [mask-image:radial-gradient(ellipse_at_top,black,transparent_70%)]"
                />

                <header className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                    <div className="flex items-center gap-2">
                        {plataforma.logo_url ? (
                            <img
                                src={plataforma.logo_url}
                                alt={`Logo de ${nombre}`}
                                className="size-8 rounded-md bg-white object-contain"
                                data-test="welcome-logo"
                            />
                        ) : (
                            <div className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5 fill-current" />
                            </div>
                        )}
                        <span className="font-semibold">{nombre}</span>
                    </div>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Ir al panel</Link>
                            </Button>
                        ) : (
                            <Button asChild variant="outline">
                                <Link href={login()}>Iniciar sesión</Link>
                            </Button>
                        )}
                    </nav>
                </header>

                <main className="relative z-10 mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-6 py-12 lg:py-20">
                    <section className="mx-auto flex max-w-3xl flex-col items-center text-center opacity-100 transition-all duration-700 starting:translate-y-4 starting:opacity-0">
                        <span className="mb-6 inline-flex items-center gap-2 rounded-full border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground backdrop-blur">
                            <ShieldCheck className="size-3.5" />
                            Trazabilidad y control de calidad
                        </span>
                        <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                            Gestión de calibraciones
                            <span className="block bg-gradient-to-r from-primary to-muted-foreground bg-clip-text text-transparent">
                                simple, precisa y trazable
                            </span>
                        </h1>
                        <p className="mt-6 max-w-2xl text-base text-pretty text-muted-foreground sm:text-lg">
                            Administra los equipos, calibraciones, ingresos y
                            mantenimientos de {nombre} desde un solo lugar.
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <Button asChild size="lg">
                                <Link href={auth.user ? dashboard() : login()}>
                                    {auth.user
                                        ? 'Ir al panel'
                                        : 'Iniciar sesión'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>
                    </section>

                    <section className="mt-16 grid gap-4 sm:grid-cols-2 lg:mt-24 lg:grid-cols-4">
                        {features.map(({ icon: Icon, title, description }) => (
                            <div
                                key={title}
                                className="group rounded-xl border bg-card/80 p-5 shadow-xs backdrop-blur transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <Icon className="size-5" />
                                </div>
                                <h2 className="mb-1.5 font-medium">{title}</h2>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="relative z-10 py-6 text-center text-xs text-muted-foreground">
                    © {new Date().getFullYear()} {nombre}. Todos los derechos
                    reservados.
                </footer>
            </div>
        </>
    );
}
