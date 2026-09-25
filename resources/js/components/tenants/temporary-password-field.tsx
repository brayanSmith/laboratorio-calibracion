import { Check, Copy, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { generatePassword } from '@/lib/generate-password';

type Props = {
    id: string;
    name: string;
    error?: string;
};

export default function TemporaryPasswordField({ id, name, error }: Props) {
    const [password, setPassword] = useState('');
    const [copiedText, copy] = useClipboard();

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>Contraseña temporal</Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    name={name}
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    className="font-mono"
                    autoComplete="off"
                    required
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setPassword(generatePassword())}
                    data-test="temporary-password-generate"
                >
                    <RefreshCw /> Generar
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    disabled={password === ''}
                    onClick={() => void copy(password)}
                    aria-label="Copiar contraseña"
                    data-test="temporary-password-copy"
                >
                    {copiedText === password && password !== '' ? (
                        <Check />
                    ) : (
                        <Copy />
                    )}
                </Button>
            </div>
            <p className="text-xs text-muted-foreground">
                Cópiala y entrégala al usuario: no se volverá a mostrar. Deberá
                cambiarla la primera vez que inicie sesión.
            </p>
            <InputError message={error} />
        </div>
    );
}
