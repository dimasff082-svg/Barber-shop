import { Link } from '@inertiajs/react';
import { Scissors } from 'lucide-react';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

export function Brand({ className }: { className?: string }) {
    return (
        <Link
            href={home()}
            aria-label="BARBERFLOW — kembali ke beranda"
            className={cn(
                'inline-flex min-h-11 shrink-0 items-center gap-2.5',
                className,
            )}
        >
            <Scissors aria-hidden="true" className="size-5 text-primary" />
            <span className="text-lg font-semibold tracking-wider sm:text-xl">
                BARBER<span className="text-primary">FLOW</span>
            </span>
        </Link>
    );
}
