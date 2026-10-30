import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import type { MouseEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index } from '@/routes/queue';

export function PrimaryCTA({
    className,
    onClick,
}: {
    className?: string;
    onClick?: MouseEventHandler<Element>;
}) {
    return (
        <Button
            asChild
            size="lg"
            className={cn(
                'min-h-12 gap-3 px-6 font-semibold hover:bg-primary-hover',
                className,
            )}
        >
            <Link href={index()} onClick={onClick}>
                Ambil Antrean
                <ArrowUpRight aria-hidden="true" className="size-4" />
            </Link>
        </Button>
    );
}
