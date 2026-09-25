import { Form, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import type { ClipboardEvent, KeyboardEvent } from 'react';
import { useState } from 'react';
import TipoEquipoCheckListController from '@/actions/App/Http/Controllers/TipoEquipoCheckListController';
import InputError from '@/components/input-error';
import {
    mergeChecklistItems,
    parseChecklistItems,
} from '@/components/tipos-equipo/checklist-items';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { TipoEquipo, TipoEquipoCheckListItem } from '@/types';

type Props = {
    tipoEquipo: TipoEquipo;
};

type RowProps = {
    item: TipoEquipoCheckListItem;
    editing: boolean;
    onEdit: () => void;
    onCancel: () => void;
};

function ChecklistItemRow({ item, editing, onEdit, onCancel }: RowProps) {
    if (editing) {
        return (
            <li className="px-3 py-2">
                <Form
                    {...TipoEquipoCheckListController.update.form(item.id)}
                    errorBag={`checklist-${item.id}`}
                    options={{ preserveScroll: true }}
                    onSuccess={onCancel}
                    className="space-y-1"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex items-center gap-2">
                                <Input
                                    name="nombre"
                                    defaultValue={item.nombre}
                                    aria-label="Nombre del ítem"
                                    required
                                    autoFocus
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                    aria-label="Guardar ítem"
                                    data-test="checklist-item-save"
                                >
                                    <Check className="h-4 w-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={onCancel}
                                    aria-label="Cancelar edición"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                            <InputError message={errors.nombre} />
                        </>
                    )}
                </Form>
            </li>
        );
    }

    return (
        <li
            className="flex items-center justify-between gap-2 px-3 py-2"
            data-test="checklist-item"
        >
            <span className="text-sm">{item.nombre}</span>

            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onEdit}
                    aria-label={`Editar ${item.nombre}`}
                    data-test="checklist-item-edit"
                >
                    <Pencil className="h-4 w-4" />
                </Button>
                <Form
                    {...TipoEquipoCheckListController.destroy.form(item.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="ghost"
                            size="sm"
                            disabled={processing}
                            aria-label={`Eliminar ${item.nombre}`}
                            data-test="checklist-item-delete"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </Form>
            </div>
        </li>
    );
}

export default function TipoEquipoChecklistManager({ tipoEquipo }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [nuevo, setNuevo] = useState('');
    const [aviso, setAviso] = useState<string | null>(null);
    const form = useForm<{ nombres: string[] }>({ nombres: [] });

    const existentes = tipoEquipo.checklist.map((item) => item.nombre);
    const errors = form.errors as Record<string, string | undefined>;
    const borrador = form.data.nombres;

    // Lo que se enviará: el borrador más lo que esté escrito y aún no se haya añadido.
    const pendientes = mergeChecklistItems(
        borrador,
        parseChecklistItems(nuevo),
        existentes,
    ).items;

    const agregarALaLista = (candidatos: string[]) => {
        const { items, skipped } = mergeChecklistItems(
            borrador,
            candidatos,
            existentes,
        );

        form.setData('nombres', items);
        form.clearErrors();
        setAviso(
            skipped === 0
                ? null
                : skipped === 1
                  ? 'Se omitió 1 ítem repetido.'
                  : `Se omitieron ${skipped} ítems repetidos.`,
        );
    };

    const agregarEscrito = () => {
        const candidatos = parseChecklistItems(nuevo);

        if (candidatos.length > 0) {
            agregarALaLista(candidatos);
        }

        setNuevo('');
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            agregarEscrito();
        }
    };

    const handlePaste = (event: ClipboardEvent<HTMLInputElement>) => {
        const texto = event.clipboardData.getData('text');

        if (!/[\r\n]/.test(texto.trim())) {
            return;
        }

        event.preventDefault();
        agregarALaLista(parseChecklistItems(texto));
    };

    const editarBorrador = (index: number, valor: string) => {
        form.setData(
            'nombres',
            borrador.map((nombre, i) => (i === index ? valor : nombre)),
        );
        form.clearErrors();
    };

    const quitarDelBorrador = (index: number) => {
        form.setData(
            'nombres',
            borrador.filter((_, i) => i !== index),
        );
        form.clearErrors();
    };

    const enviar = () => {
        form.transform(() => ({ nombres: pendientes }));
        form.post(TipoEquipoCheckListController.store.url(tipoEquipo.id), {
            errorBag: 'checklist',
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setNuevo('');
                setAviso(null);
            },
        });
    };

    return (
        <section className="space-y-3" data-test="tipo-equipo-checklist">
            <ul className="divide-y rounded-lg border">
                {tipoEquipo.checklist.map((item) => (
                    <ChecklistItemRow
                        key={item.id}
                        item={item}
                        editing={editingId === item.id}
                        onEdit={() => setEditingId(item.id)}
                        onCancel={() => setEditingId(null)}
                    />
                ))}

                {tipoEquipo.checklist.length === 0 ? (
                    <li className="px-3 py-4 text-center text-sm text-muted-foreground">
                        Este tipo de equipo aún no tiene ítems en su checklist.
                    </li>
                ) : null}
            </ul>

            <div className="space-y-2 rounded-lg border border-dashed p-3">
                <p className="text-sm font-medium">Agregar ítems</p>

                {borrador.length > 0 ? (
                    <ul
                        className="space-y-2"
                        aria-label="Ítems por agregar"
                        data-test="checklist-draft"
                    >
                        {borrador.map((nombre, index) => (
                            <li key={index} className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Input
                                        value={nombre}
                                        onChange={(event) =>
                                            editarBorrador(
                                                index,
                                                event.target.value,
                                            )
                                        }
                                        aria-label={`Ítem ${index + 1} por agregar`}
                                        aria-invalid={
                                            errors[`nombres.${index}`]
                                                ? true
                                                : undefined
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => quitarDelBorrador(index)}
                                        aria-label={`Quitar ${nombre} de la lista`}
                                        data-test="checklist-draft-remove"
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                                <InputError
                                    message={errors[`nombres.${index}`]}
                                />
                            </li>
                        ))}
                    </ul>
                ) : null}

                <div className="flex items-center gap-2">
                    <Input
                        value={nuevo}
                        onChange={(event) => setNuevo(event.target.value)}
                        onKeyDown={handleKeyDown}
                        onPaste={handlePaste}
                        placeholder="Escribe un ítem y pulsa Enter, o pega una lista"
                        aria-label="Nuevo ítem del checklist"
                        data-test="checklist-draft-input"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={agregarEscrito}
                        disabled={nuevo.trim() === ''}
                        aria-label="Añadir a la lista"
                        data-test="checklist-draft-add"
                    >
                        <Plus className="h-4 w-4" />
                    </Button>
                </div>
                <InputError
                    message={
                        errors[`nombres.${borrador.length}`] ?? errors.nombres
                    }
                />

                {aviso ? (
                    <p className="text-sm text-muted-foreground">{aviso}</p>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Pega una lista con un ítem por línea y se armará sola.
                        Nada se guarda hasta que pulses "Agregar checklists".
                    </p>
                )}

                <div className="flex justify-end">
                    <Button
                        type="button"
                        onClick={enviar}
                        disabled={pendientes.length === 0 || form.processing}
                        data-test="checklist-items-submit"
                    >
                        Agregar checklists
                        {pendientes.length > 0 ? ` (${pendientes.length})` : ''}
                    </Button>
                </div>
            </div>
        </section>
    );
}
