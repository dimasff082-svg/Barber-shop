import { Ticket } from 'lucide-react';
import type { ReactNode } from 'react';

export function QueueEmptyState({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children?: ReactNode;
}) {
    return (
        <section
            className="rounded-2xl border border-border bg-card p-6 sm:p-8"
            aria-label={title}
        >
            <div className="mb-5 flex size-12 items-center justify-center rounded-xl bg-surface-raised">
                <Ticket className="size-6 text-primary" aria-hidden="true" />
            </div>
            <h2 className="text-xl font-semibold">{title}</h2>
            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                {description}
            </p>
            {children && <div className="mt-6">{children}</div>}
        </section>
    );
}
