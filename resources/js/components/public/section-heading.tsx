import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SectionHeadingProps = {
    id: string;
    eyebrow: string;
    title: ReactNode;
    description?: string;
    className?: string;
};

export function SectionHeading({
    id,
    eyebrow,
    title,
    description,
    className,
}: SectionHeadingProps) {
    return (
        <div className={cn('max-w-2xl', className)}>
            <p className="mb-4 text-xs font-semibold tracking-[0.2em] text-primary uppercase">
                {eyebrow}
            </p>
            <h2
                id={id}
                className="font-display text-4xl leading-[1.12] uppercase sm:text-5xl"
            >
                {title}
            </h2>
            {description && (
                <p className="mt-5 max-w-xl text-base leading-7 text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}
