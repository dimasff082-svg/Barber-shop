import type { ReactNode } from 'react';

export function StatCard({
    label,
    value,
    description,
}: {
    label: string;
    value: ReactNode;
    description?: string;
}) {
    return (
        <div className="min-w-0 rounded-2xl border border-border bg-card p-5">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-3 text-3xl font-semibold tabular-nums">{value}</p>
            {description && (
                <p className="mt-2 text-xs leading-5 text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}
