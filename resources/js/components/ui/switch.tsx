import * as React from 'react';

import { cn } from '@/lib/utils';

type SwitchProps = Omit<
    React.ButtonHTMLAttributes<HTMLButtonElement>,
    'onChange' | 'value'
> & {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
};

/**
 * A dependency-free switch (no @radix-ui/react-switch installed): a styled
 * button with role="switch", matching the shadcn Switch API (checked/onCheckedChange).
 */
function Switch({
    checked,
    onCheckedChange,
    disabled,
    className,
    ...props
}: SwitchProps) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            data-slot="switch"
            data-state={checked ? 'checked' : 'unchecked'}
            disabled={disabled}
            onClick={() => onCheckedChange(!checked)}
            className={cn(
                'focus-visible:ring-ring/50 inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border border-transparent shadow-xs transition-colors outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                checked ? 'bg-primary' : 'bg-input',
                className,
            )}
            {...props}
        >
            <span
                data-slot="switch-thumb"
                data-state={checked ? 'checked' : 'unchecked'}
                className={cn(
                    'pointer-events-none block size-4 rounded-full bg-background shadow-lg ring-0 transition-transform',
                    checked ? 'translate-x-4' : 'translate-x-0',
                )}
            />
        </button>
    );
}

export { Switch };
