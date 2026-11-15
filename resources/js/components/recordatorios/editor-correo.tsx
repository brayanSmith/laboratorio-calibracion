import { useHttp } from '@inertiajs/react';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import type { Editor } from '@tiptap/react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Heading2,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    Redo2,
    Strikethrough,
    Underline,
    Undo2,
} from 'lucide-react';
import type { ReactNode, Ref } from 'react';
import { useImperativeHandle, useRef, useState } from 'react';
import RecordatorioController from '@/actions/App/Modules/Recordatorios/Http/Controllers/RecordatorioController';
import InputError from '@/components/input-error';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';

export type EditorCorreoHandle = {
    /** Inserta el texto donde está el cursor. */
    insertar: (texto: string) => void;
    /** Reemplaza todo el contenido. */
    establecer: (html: string) => void;
};

type Props = {
    id: string;
    /** Nombre del campo del formulario donde viaja el HTML. */
    name: string;
    inicial: string;
    editable: boolean;
    resaltado?: boolean;
    onCambiar?: () => void;
    onEnfocar?: () => void;
    ref?: Ref<EditorCorreoHandle>;
};

function BotonHerramienta({
    etiqueta,
    activo = false,
    deshabilitado = false,
    onClick,
    children,
}: {
    etiqueta: string;
    activo?: boolean;
    deshabilitado?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Toggle
            size="sm"
            pressed={activo}
            onPressedChange={onClick}
            disabled={deshabilitado}
            aria-label={etiqueta}
            title={etiqueta}
        >
            {children}
        </Toggle>
    );
}

function Separador() {
    return <span className="mx-1 h-5 w-px bg-border" aria-hidden />;
}

/**
 * Editor de texto enriquecido para el mensaje del correo: formato, listas, alineación,
 * enlaces e imágenes (se suben al servidor y el correo las enlaza). El HTML viaja en un
 * input oculto con el nombre dado, así que se guarda con el resto del formulario.
 */
export default function EditorCorreo({
    id,
    name,
    inicial,
    editable,
    resaltado = false,
    onCambiar,
    onEnfocar,
    ref,
}: Props) {
    const [html, setHtml] = useState(inicial);
    const selectorImagen = useRef<HTMLInputElement>(null);
    const subida = useHttp<{ imagen: File | null }, { url: string }>({
        imagen: null,
    });

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                code: false,
                codeBlock: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                },
            }),
            Image.configure({ allowBase64: false }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
        ],
        content: inicial,
        editable,
        immediatelyRender: false,
        shouldRerenderOnTransaction: true,
        editorProps: {
            attributes: {
                id,
                class: 'min-h-56 px-3 py-2 text-sm outline-none [&_a]:text-primary [&_a]:underline [&_h2]:mt-3 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mt-2 [&_h3]:font-semibold [&_img]:my-2 [&_img]:max-w-full [&_img]:rounded [&_img.ProseMirror-selectednode]:ring-2 [&_img.ProseMirror-selectednode]:ring-primary [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-6',
            },
            // Imágenes arrastradas o pegadas desde el equipo: se suben como las del botón.
            handleDrop: (_vista, evento) =>
                subirArchivos((evento as DragEvent).dataTransfer?.files),
            handlePaste: (_vista, evento) =>
                subirArchivos(evento.clipboardData?.files),
        },
        onUpdate: ({ editor: actual }) => {
            setHtml(actual.getHTML());
            onCambiar?.();
        },
        onFocus: () => onEnfocar?.(),
    });

    useImperativeHandle(
        ref,
        () => ({
            insertar: (texto) =>
                editor?.chain().focus().insertContent(texto).run(),
            establecer: (nuevo) => {
                editor?.commands.setContent(nuevo, { emitUpdate: false });
                setHtml(nuevo);
            },
        }),
        [editor],
    );

    /** Sube las imágenes de la lista; devuelve true si había alguna (para que el editor no haga nada más). */
    function subirArchivos(archivos: FileList | null | undefined): boolean {
        const imagenes = Array.from(archivos ?? []).filter((archivo) =>
            archivo.type.startsWith('image/'),
        );

        imagenes.forEach((imagen) => void subirImagen(imagen));

        return imagenes.length > 0;
    }

    async function subirImagen(imagen: File) {
        subida.transform(() => ({ imagen }));

        try {
            const respuesta = await subida.post(
                RecordatorioController.subirImagen.url(),
            );

            editor?.chain().focus().setImage({ src: respuesta.url }).run();
        } catch {
            // Los errores de validación quedan en subida.errors.
        }
    }

    function editarEnlace(actual: Editor) {
        const anterior = actual.getAttributes('link').href as
            | string
            | undefined;
        const url = window.prompt(
            'Dirección del enlace',
            anterior ?? 'https://',
        );

        if (url === null) {
            return;
        }

        if (url.trim() === '') {
            actual.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        actual
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url.trim() })
            .run();
    }

    return (
        <div className="grid gap-1">
            <div
                className={cn(
                    'overflow-hidden rounded-md border shadow-xs transition-shadow focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    resaltado &&
                        'border-dashed border-primary ring-2 ring-primary/20',
                    !editable && 'opacity-60',
                )}
            >
                {editor && editable ? (
                    <div className="flex flex-wrap items-center gap-0.5 border-b bg-muted/40 p-1">
                        <BotonHerramienta
                            etiqueta="Negrita"
                            activo={editor.isActive('bold')}
                            onClick={() =>
                                editor.chain().focus().toggleBold().run()
                            }
                        >
                            <Bold />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta="Cursiva"
                            activo={editor.isActive('italic')}
                            onClick={() =>
                                editor.chain().focus().toggleItalic().run()
                            }
                        >
                            <Italic />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta="Subrayado"
                            activo={editor.isActive('underline')}
                            onClick={() =>
                                editor.chain().focus().toggleUnderline().run()
                            }
                        >
                            <Underline />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta="Tachado"
                            activo={editor.isActive('strike')}
                            onClick={() =>
                                editor.chain().focus().toggleStrike().run()
                            }
                        >
                            <Strikethrough />
                        </BotonHerramienta>

                        <Separador />

                        <BotonHerramienta
                            etiqueta="Título"
                            activo={editor.isActive('heading', { level: 2 })}
                            onClick={() =>
                                editor
                                    .chain()
                                    .focus()
                                    .toggleHeading({ level: 2 })
                                    .run()
                            }
                        >
                            <Heading2 />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta="Lista con viñetas"
                            activo={editor.isActive('bulletList')}
                            onClick={() =>
                                editor.chain().focus().toggleBulletList().run()
                            }
                        >
                            <List />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta="Lista numerada"
                            activo={editor.isActive('orderedList')}
                            onClick={() =>
                                editor.chain().focus().toggleOrderedList().run()
                            }
                        >
                            <ListOrdered />
                        </BotonHerramienta>

                        <Separador />

                        <BotonHerramienta
                            etiqueta="Alinear a la izquierda"
                            activo={editor.isActive({ textAlign: 'left' })}
                            onClick={() =>
                                editor
                                    .chain()
                                    .focus()
                                    .setTextAlign('left')
                                    .run()
                            }
                        >
                            <AlignLeft />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta="Centrar"
                            activo={editor.isActive({ textAlign: 'center' })}
                            onClick={() =>
                                editor
                                    .chain()
                                    .focus()
                                    .setTextAlign('center')
                                    .run()
                            }
                        >
                            <AlignCenter />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta="Alinear a la derecha"
                            activo={editor.isActive({ textAlign: 'right' })}
                            onClick={() =>
                                editor
                                    .chain()
                                    .focus()
                                    .setTextAlign('right')
                                    .run()
                            }
                        >
                            <AlignRight />
                        </BotonHerramienta>

                        <Separador />

                        <BotonHerramienta
                            etiqueta="Enlace"
                            activo={editor.isActive('link')}
                            onClick={() => editarEnlace(editor)}
                        >
                            <Link2 />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta={
                                subida.processing
                                    ? 'Subiendo imagen...'
                                    : 'Insertar imagen'
                            }
                            deshabilitado={subida.processing}
                            onClick={() => selectorImagen.current?.click()}
                        >
                            <ImagePlus
                                className={cn(
                                    subida.processing && 'animate-pulse',
                                )}
                            />
                        </BotonHerramienta>

                        <Separador />

                        <BotonHerramienta
                            etiqueta="Deshacer"
                            deshabilitado={!editor.can().undo()}
                            onClick={() => editor.chain().focus().undo().run()}
                        >
                            <Undo2 />
                        </BotonHerramienta>
                        <BotonHerramienta
                            etiqueta="Rehacer"
                            deshabilitado={!editor.can().redo()}
                            onClick={() => editor.chain().focus().redo().run()}
                        >
                            <Redo2 />
                        </BotonHerramienta>

                        <input
                            ref={selectorImagen}
                            type="file"
                            accept="image/png,image/jpeg,image/gif,image/webp"
                            className="hidden"
                            onChange={(evento) => {
                                const imagen = evento.target.files?.[0];

                                if (imagen) {
                                    void subirImagen(imagen);
                                }

                                evento.target.value = '';
                            }}
                        />
                    </div>
                ) : null}

                <EditorContent editor={editor} />
            </div>

            <input type="hidden" name={name} value={html} />
            <InputError message={subida.errors.imagen} />
        </div>
    );
}
