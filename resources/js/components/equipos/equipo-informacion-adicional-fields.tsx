import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Equipo } from '@/types';

type Props = {
    equipo?: Equipo;
    errors: Partial<Record<string, string>>;
};

/**
 * Notas y patrón de referencia del equipo. The caller is responsible for its
 * own heading; move this component around freely to reposition the section.
 */
export default function EquipoInformacionAdicionalFields({
    equipo,
    errors,
}: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="notas">Notas</Label>
                <Textarea
                    id="notas"
                    name="notas"
                    defaultValue={equipo?.notas ?? ''}
                    rows={3}
                />
                <InputError message={errors.notas} />
            </div>

            <div className="flex items-center gap-3">
                <Checkbox
                    id="patron_referencia"
                    name="patron_referencia"
                    defaultChecked={equipo?.patron_referencia ?? false}
                />
                <Label htmlFor="patron_referencia">Patrón de referencia</Label>
            </div>
        </div>
    );
}
