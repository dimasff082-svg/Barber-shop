import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck, Ticket } from 'lucide-react';
import { PublicNavbar } from '@/components/public/public-navbar';
import { QueueBookingForm } from '@/components/queue/queue-booking-form';
import PublicLayout from '@/layouts/public-layout';
import { home } from '@/routes';
import type { QueueFormProps } from '@/types/customer';

export default function QueueIndex(props: QueueFormProps) {
    return (
        <PublicLayout showQueueCta={false}>
            <Head title="Ambil Antrean">
                <meta
                    name="description"
                    content="Pilih layanan dan barber, lalu ambil antrean BARBERFLOW. Tanpa membuat akun, dengan tiket privat dan check-in di lokasi."
                />
            </Head>
            <PublicNavbar />
            <main
                id="konten-utama"
                tabIndex={-1}
                className="public-container py-10 outline-none sm:py-14"
            >
                <div className="mx-auto max-w-160">
                    <Link
                        href={home()}
                        className="mb-8 inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground hover:text-primary"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Kembali ke beranda
                    </Link>
                    <h1 className="sr-only">Ambil antrean BARBERFLOW</h1>
                    {props.recent_tickets.length > 0 && (
                        <section
                            className="mb-8 rounded-xl border border-border bg-card p-5"
                            aria-label="Tiket pada sesi ini"
                        >
                            <div className="flex items-center gap-2 font-medium">
                                <Ticket
                                    className="size-5 text-primary"
                                    aria-hidden="true"
                                />
                                Sudah punya tiket?
                            </div>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                Buka tiket yang sudah Anda buat untuk melihat
                                statusnya.
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {props.recent_tickets.map((ticket, index) => (
                                    <Link
                                        key={ticket.id}
                                        href={ticket.url}
                                        className="inline-flex min-h-11 items-center rounded-lg border border-border px-4 text-sm text-primary hover:bg-surface-raised"
                                    >
                                        Buka tiket {index + 1}
                                    </Link>
                                ))}
                            </div>
                        </section>
                    )}
                    <QueueBookingForm {...props} />
                    <p className="mt-8 flex items-start gap-2 text-xs leading-5 text-muted-foreground">
                        <ShieldCheck
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        Detail tiket hanya dapat diakses dari sesi Anda atau
                        melalui tautan akses rahasia milik Anda.
                    </p>
                </div>
            </main>
        </PublicLayout>
    );
}
