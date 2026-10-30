import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { LiveUpdateStatus } from '@/components/staff/live-update-status';

export function StaffPage({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    const { flash } = usePage().props;
    return (
        <div
            lang="id"
            className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8"
        >
            <Head title={title}>
                <meta name="robots" content="noindex, nofollow, noarchive" />
            </Head>
            <header>
                <p className="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">
                    BARBERFLOW · Area kerja
                </p>
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                )}
                <LiveUpdateStatus />
            </header>
            {flash?.success && (
                <p
                    role="status"
                    className="rounded-xl border border-success/30 bg-success/10 p-4 text-sm text-success"
                >
                    {flash.success}
                </p>
            )}
            {flash?.error && (
                <p
                    role="alert"
                    className="rounded-xl border border-danger/30 bg-danger/10 p-4 text-sm text-danger"
                >
                    {flash.error}
                </p>
            )}
            {children}
        </div>
    );
}
