import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    codigoCompra: string;
    codigoDespacho: string;
    onCodigoCompraChange?: (valor: string) => void;
    onCodigoDespachoChange?: (valor: string) => void;
    errors: Partial<Record<string, string>>;
};

/** Código de compra y código de despacho (ambos opcionales) de un servicio de tercero de
 * mantenimiento. Se envían como `codigo_compra` y `codigo_despacho`. Sin los callbacks
 * parten del valor dado y se editan libremente. */
export default function CodigosServicioFields({
    codigoCompra,
    codigoDespacho,
    onCodigoCompraChange,
    onCodigoDespachoChange,
    errors,
}: Props) {
    const controlado = onCodigoCompraChange !== undefined;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="codigo_compra">Código de compra</Label>
                <Input
                    id="codigo_compra"
                    name="codigo_compra"
                    maxLength={50}
                    {...(controlado
                        ? {
                              value: codigoCompra,
                              onChange: (event) =>
                                  onCodigoCompraChange(event.target.value),
                          }
                        : { defaultValue: codigoCompra })}
                    data-test="servicio-codigo-compra-input"
                />
                <InputError message={errors.codigo_compra} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="codigo_despacho">Código de despacho</Label>
                <Input
                    id="codigo_despacho"
                    name="codigo_despacho"
                    maxLength={50}
                    {...(controlado
                        ? {
                              value: codigoDespacho,
                              onChange: (event) =>
                                  onCodigoDespachoChange?.(event.target.value),
                          }
                        : { defaultValue: codigoDespacho })}
                    data-test="servicio-codigo-despacho-input"
                />
                <InputError message={errors.codigo_despacho} />
            </div>
        </div>
    );
}
