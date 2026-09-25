import { Head, usePage } from '@inertiajs/react';

/**
 * Keep the browser tab icon in sync with the logo of the empresa, falling back to the app icon.
 */
export default function BrandingHead() {
    const { empresa } = usePage().props;

    return (
        <Head>
            <link
                head-key="favicon"
                rel="icon"
                href={empresa?.logo_url ?? '/favicon.svg'}
            />
        </Head>
    );
}
