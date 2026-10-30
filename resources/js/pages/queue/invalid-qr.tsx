import { Head, Link } from '@inertiajs/react';
import { QrCode } from 'lucide-react';
import { PublicNavbar } from '@/components/public/public-navbar';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { home } from '@/routes';

export default function InvalidQr({ message }: { message?: string }) {
    return (
        <PublicLayout showQueueCta={false}>
            <Head title="QR Tidak Aktif">
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <PublicNavbar />
            <main
                id="konten-utama"
                tabIndex={-1}
                className="public-container py-16 outline-none"
            >
                <div className="mx-auto max-w-lg rounded-2xl border border-border bg-card p-6 text-center sm:p-8">
                    <QrCode
                        className="mx-auto size-10 text-warning"
                        aria-hidden="true"
                    />
                    <h1 className="mt-6 font-display text-3xl uppercase">
                        QR tidak valid atau tidak aktif
                    </h1>
                    <p className="mt-4 text-sm leading-6 text-muted-foreground">
                        {message ||
                            'Pindai QR aktif yang tersedia di barbershop, atau minta bantuan staff. Tiket yang sudah Anda miliki tetap dapat dibuka melalui tautan aksesnya.'}
                    </p>
                    <Button asChild className="mt-7 min-h-12 w-full">
                        <Link href={home()}>Kembali ke beranda</Link>
                    </Button>
                </div>
            </main>
        </PublicLayout>
    );
}
