import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};
export function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;
    return (
        <nav aria-label="Halaman data" className="flex flex-wrap gap-2">
            {links.map((link, index) => {
                const label =
                    index === 0
                        ? 'Sebelumnya'
                        : index === links.length - 1
                          ? 'Berikutnya'
                          : link.label.replace(/<[^>]+>/g, '');
                return link.url ? (
                    <Button
                        key={index}
                        variant={link.active ? 'default' : 'outline'}
                        asChild
                        className="min-h-11"
                    >
                        <Link
                            href={link.url}
                            preserveScroll
                            aria-current={link.active ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    </Button>
                ) : (
                    <Button
                        key={index}
                        variant="outline"
                        className="min-h-11"
                        disabled
                    >
                        {label}
                    </Button>
                );
            })}
        </nav>
    );
}
