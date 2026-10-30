import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PublicNavbar } from '@/components/public/public-navbar';
import PublicLayout from '@/layouts/public-layout';

export function PublicPageShell({
    title,
    eyebrow,
    description,
    children,
}: {
    title: string;
    eyebrow: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <PublicLayout>
            <Head title={title}>
                <meta name="description" content={description} />
            </Head>
            <PublicNavbar />
            <main
                id="konten-utama"
                tabIndex={-1}
                className="public-container py-12 md:py-20"
            >
                <header className="mb-10 max-w-2xl">
                    <p className="mb-4 text-xs font-semibold tracking-[0.18em] text-primary uppercase">
                        {eyebrow}
                    </p>
                    <h1 className="font-display text-4xl uppercase md:text-5xl">
                        {title}
                    </h1>
                    <p className="mt-5 text-base leading-7 text-muted-foreground">
                        {description}
                    </p>
                </header>
                {children}
            </main>
        </PublicLayout>
    );
}
