import { router, useHttp } from '@inertiajs/react';
import { FileText, LayoutGrid, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { buscar as buscarDocumentos } from '@/actions/App/Modules/Equipos/Http/Controllers/DocumentoEquipoController';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { usePermissions } from '@/hooks/use-permissions';
import {
    esAtajoBuscador,
    filtrarModulos,
    MINIMO_BUSQUEDA_DOCUMENTOS,
} from '@/lib/buscador-universal';
import { cn, toUrl } from '@/lib/utils';
import type { NavGroup } from '@/types';

type DocumentoEncontrado = {
    id: number;
    nombre: string;
    url: string;
    equipo_id: number;
    equipo_codigo: string | null;
    equipo_modelo: string | null;
    tipo_equipo: string | null;
};

/** Espera tras la última tecla antes de buscar documentos en el servidor. */
const ESPERA_BUSQUEDA_MS = 250;

/** "⌘ K" en Mac y "Ctrl K" en los demás sistemas. */
const atajo =
    typeof navigator !== 'undefined' && /Mac/i.test(navigator.platform)
        ? '⌘ K'
        : 'Ctrl K';

/**
 * Buscador universal: se abre con Ctrl + K (⌘ + K en Mac) desde cualquier pantalla o con el
 * botón del menú, y busca entre los módulos del menú y la documentación de los equipos.
 */
export default function BuscadorUniversal({ groups }: { groups: NavGroup[] }) {
    const { can } = usePermissions();
    const puedeVerDocumentos = can('equipos.ver');
    const [abierto, setAbierto] = useState(false);
    const [query, setQuery] = useState('');
    const [documentos, setDocumentos] = useState<DocumentoEncontrado[]>([]);
    const { get, processing } = useHttp<
        Record<string, string>,
        DocumentoEncontrado[]
    >();
    // Solo vale la respuesta de la última búsqueda (las anteriores pueden llegar tarde).
    const ultimaBusqueda = useRef('');

    useEffect(() => {
        const alPresionar = (evento: KeyboardEvent) => {
            if (esAtajoBuscador(evento)) {
                evento.preventDefault();
                setAbierto((actual) => !actual);
            }
        };

        window.addEventListener('keydown', alPresionar);

        return () => window.removeEventListener('keydown', alPresionar);
    }, []);

    const buscaDocumentos =
        puedeVerDocumentos && query.trim().length >= MINIMO_BUSQUEDA_DOCUMENTOS;

    useEffect(() => {
        const texto = query.trim();
        ultimaBusqueda.current = texto;

        if (!buscaDocumentos) {
            setDocumentos([]);

            return;
        }

        const espera = window.setTimeout(() => {
            void get(buscarDocumentos({ query: { q: texto } }).url)
                .then((encontrados) => {
                    if (ultimaBusqueda.current === texto) {
                        setDocumentos(encontrados);
                    }
                })
                .catch(() => setDocumentos([]));
        }, ESPERA_BUSQUEDA_MS);

        return () => window.clearTimeout(espera);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query, buscaDocumentos]);

    const cambiarAbierto = (valor: boolean) => {
        setAbierto(valor);

        if (!valor) {
            setQuery('');
        }
    };

    // Sin texto es solo la barra: los resultados aparecen al escribir.
    const hayTexto = query.trim() !== '';
    const modulos = hayTexto ? filtrarModulos(groups, query) : [];

    const irAModulo = (href: NavGroup['items'][number]['href']) => {
        cambiarAbierto(false);
        router.visit(toUrl(href));
    };

    const abrirDocumento = (documento: DocumentoEncontrado) => {
        cambiarAbierto(false);
        window.open(documento.url, '_blank', 'noopener');
    };

    return (
        <>
            <SidebarMenuButton
                type="button"
                onClick={() => setAbierto(true)}
                tooltip={{ children: `Buscar (${atajo})` }}
                className="border border-sidebar-border bg-sidebar text-sidebar-foreground/70 shadow-none"
                data-test="buscador-universal-abrir"
            >
                <Search />
                <span>Buscar...</span>
                <kbd className="ml-auto rounded border bg-muted px-1.5 font-mono text-[10px] text-muted-foreground group-data-[collapsible=icon]:hidden">
                    {atajo}
                </kbd>
            </SidebarMenuButton>

            <Dialog open={abierto} onOpenChange={cambiarAbierto}>
                {/* Una barra cerca de la parte superior, sin la "X" del diálogo (se cierra con Esc). */}
                <DialogContent
                    className="gap-0 overflow-hidden p-0 sm:top-[15%] sm:max-w-xl sm:translate-y-0 [&>button:last-child]:hidden"
                    data-test="buscador-universal"
                >
                    <DialogHeader className="sr-only">
                        <DialogTitle>Buscador</DialogTitle>
                        <DialogDescription>
                            Busca un módulo del menú o un documento de los
                            equipos.
                        </DialogDescription>
                    </DialogHeader>

                    {/* El filtro lo hacemos nosotros: los módulos sin tildes y los documentos en el servidor. */}
                    <Command
                        shouldFilter={false}
                        className={cn(
                            'relative',
                            !hayTexto &&
                                '[&_[data-slot=command-input-wrapper]]:border-b-0',
                        )}
                    >
                        <CommandInput
                            value={query}
                            onValueChange={setQuery}
                            placeholder={
                                puedeVerDocumentos
                                    ? 'Busca un módulo o un documento de equipo...'
                                    : 'Busca un módulo...'
                            }
                            className="h-12 pr-12"
                            data-test="buscador-universal-input"
                        />
                        <kbd className="pointer-events-none absolute top-3.5 right-3 rounded border bg-muted px-1.5 font-mono text-[10px] text-muted-foreground">
                            Esc
                        </kbd>
                        {hayTexto ? (
                            <>
                                <CommandList className="max-h-[60vh]">
                                    {processing ? null : (
                                        <CommandEmpty>
                                            No se encontraron resultados.
                                        </CommandEmpty>
                                    )}

                                    {modulos.length > 0 ? (
                                        <CommandGroup heading="Módulos">
                                            {modulos.map((modulo) => (
                                                <CommandItem
                                                    key={`${modulo.grupo}-${modulo.title}`}
                                                    value={`modulo-${modulo.grupo}-${modulo.title}`}
                                                    onSelect={() =>
                                                        irAModulo(modulo.href)
                                                    }
                                                    data-test="buscador-modulo"
                                                >
                                                    {modulo.icon ? (
                                                        <modulo.icon />
                                                    ) : (
                                                        <LayoutGrid />
                                                    )}
                                                    <span>{modulo.title}</span>
                                                    {modulo.grupo ? (
                                                        <CommandShortcut className="tracking-normal">
                                                            {modulo.grupo}
                                                        </CommandShortcut>
                                                    ) : null}
                                                </CommandItem>
                                            ))}
                                        </CommandGroup>
                                    ) : null}

                                    {buscaDocumentos && processing ? (
                                        <div
                                            role="status"
                                            className="flex items-center gap-2 px-4 py-2 text-xs text-muted-foreground"
                                        >
                                            <Spinner aria-hidden="true" />
                                            Buscando en la documentación de los
                                            equipos...
                                        </div>
                                    ) : null}

                                    {buscaDocumentos &&
                                    documentos.length > 0 ? (
                                        <>
                                            {modulos.length > 0 ? (
                                                <CommandSeparator />
                                            ) : null}
                                            <CommandGroup heading="Documentación de equipos">
                                                {documentos.map((documento) => (
                                                    <CommandItem
                                                        key={documento.id}
                                                        value={`documento-${documento.id}`}
                                                        onSelect={() =>
                                                            abrirDocumento(
                                                                documento,
                                                            )
                                                        }
                                                        data-test="buscador-documento"
                                                    >
                                                        <FileText />
                                                        <div className="min-w-0">
                                                            <p className="truncate">
                                                                {
                                                                    documento.nombre
                                                                }
                                                            </p>
                                                            <p className="truncate text-xs text-muted-foreground">
                                                                {[
                                                                    documento.equipo_codigo,
                                                                    documento.tipo_equipo,
                                                                    documento.equipo_modelo,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' · ',
                                                                    )}
                                                            </p>
                                                        </div>
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </>
                                    ) : null}
                                </CommandList>
                                <div className="border-t px-3 py-2 text-xs text-muted-foreground">
                                    ↑ ↓ para moverte · Enter para abrir
                                </div>
                            </>
                        ) : null}
                    </Command>
                </DialogContent>
            </Dialog>
        </>
    );
}
