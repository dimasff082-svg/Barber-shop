import type { ReactNode } from 'react';
import { PrimaryCTA } from '@/components/public/primary-cta';
import { PublicFooter } from '@/components/public/public-footer';
import { cn } from '@/lib/utils';

export default function PublicLayout({
    children,
    hideQueueCta = false,
    showQueueCta = true,
}: {
    children: ReactNode;
    hideQueueCta?: boolean;
    showQueueCta?: boolean;
}) {
    const sticky = showQueueCta && !hideQueueCta;
    return (
        <div
            lang="id"
            className={cn(
                'public-theme min-h-screen bg-background font-sans text-foreground antialiased',
                sticky && 'pb-20 lg:pb-0',
            )}
        >
            <a
                href="#konten-utama"
                className="fixed top-4 left-4 z-60 -translate-y-[200%] rounded-lg bg-primary px-5 py-3 text-primary-foreground focus:translate-y-0"
            >
                Lewati navigasi
            </a>
            {children}
            <PublicFooter />
            {sticky && (
                <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 p-3 backdrop-blur-md lg:hidden">
                    <PrimaryCTA className="w-full" />
                </div>
            )}
        </div>
    );
}
