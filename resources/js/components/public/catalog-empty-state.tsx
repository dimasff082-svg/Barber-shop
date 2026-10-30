import { Scissors } from 'lucide-react';

export function CatalogEmptyState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="rounded-2xl border border-dashed border-border bg-card/40 p-8 text-center sm:p-12">
            <Scissors
                aria-hidden="true"
                className="mx-auto mb-5 size-8 text-primary"
            />
            <h2 className="text-xl font-semibold">{title}</h2>
            <p className="mx-auto mt-3 max-w-md text-sm leading-6 text-muted-foreground">
                {description}
            </p>
        </div>
    );
}
