type Props = {
    value: number;
    label: string;
    className: string;
};

/** Contador de pendientes junto al título de una tab; no se muestra si vale 0. */
export default function TabCountBadge({ value, label, className }: Props) {
    if (value <= 0) {
        return null;
    }

    return (
        <span
            title={label}
            aria-label={`${label}: ${value}`}
            className={`ml-1.5 flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-medium tabular-nums ${className}`}
            data-test="tab-badge"
        >
            {value}
        </span>
    );
}
