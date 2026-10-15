import { Head, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { empresaCssVariables } from '@/lib/empresa-colors';

/**
 * Keep the browser tab icon in sync with the logo of the empresa, falling back to the app icon,
 * and apply the empresa's theme and navigation colors over the default ones.
 */
export default function BrandingHead() {
    const { empresa, plataforma } = usePage().props;
    const colorTema = empresa?.color_tema;
    const colorNav = empresa?.color_nav;

    useEffect(() => {
        const variables = empresaCssVariables(colorTema, colorNav);
        const root = document.documentElement;

        Object.entries(variables).forEach(([name, value]) => {
            root.style.setProperty(name, value);
        });

        return () => {
            Object.keys(variables).forEach((name) => {
                root.style.removeProperty(name);
            });
        };
    }, [colorTema, colorNav]);

    return (
        <Head>
            <link
                head-key="favicon"
                rel="icon"
                href={
                    empresa?.logo_url ?? plataforma.logo_url ?? '/favicon.svg'
                }
            />
        </Head>
    );
}
