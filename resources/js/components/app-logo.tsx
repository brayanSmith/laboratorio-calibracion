import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, empresa } = usePage().props;

    return (
        <>
            {empresa?.logo_url ? (
                <img
                    src={empresa.logo_url}
                    alt={`Logo de ${empresa.nombre}`}
                    className="aspect-square size-8 rounded-md bg-white object-contain"
                    data-test="app-logo-image"
                />
            ) : (
                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                </div>
            )}
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {empresa?.nombre ?? name}
                </span>
            </div>
        </>
    );
}
