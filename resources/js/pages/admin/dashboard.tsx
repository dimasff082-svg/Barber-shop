import { Head, Link, router } from '@inertiajs/react';
import { StaffPage } from '@/components/staff/staff-page';
import { OperationStats } from '@/components/staff/operation-stats';
import { QueueCards } from '@/components/staff/queue-cards';
import { Button } from '@/components/ui/button';
import { AvailabilityControl } from '@/components/staff/availability-control';
import { StatCard } from '@/components/staff/stat-card';
import { index as queues } from '@/routes/admin/queues';
import type { DashboardProps } from '@/types/operations';

export default function AdminDashboard({
    date,
    timezone,
    stats,
    processing,
    barbers,
    active_barbers,
}: DashboardProps) {
    return (
        <>
            <Head title="Dashboard Admin" />
            <StaffPage
                title="Dashboard Admin"
                description={`Ringkasan tanggal bisnis ${date} · ${timezone}. Angka berasal dari tiket tersimpan.`}
            >
                <div className="flex flex-wrap gap-3">
                    <Button className="min-h-11" asChild>
                        <Link href={queues()}>Kelola Antrean</Link>
                    </Button>
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={() =>
                            router.reload({
                                only: ['stats', 'processing', 'barbers'],
                            })
                        }
                    >
                        Perbarui Data
                    </Button>
                </div>
                <OperationStats stats={stats} />
                <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <StatCard label="Barber aktif" value={active_barbers} />
                    <StatCard label="Sedang dipanggil" value={stats.called} />
                    <StatCard
                        label="Sedang dilayani"
                        value={stats.in_service}
                    />
                </div>
                <section className="space-y-4">
                    <h2 className="text-xl font-semibold">Sedang diproses</h2>
                    <QueueCards tickets={processing} />
                </section>
                <section className="space-y-4">
                    <h2 className="text-xl font-semibold">Barber studio</h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {barbers.map((barber) => (
                            <article
                                key={barber.id}
                                className="rounded-xl border bg-card p-5"
                            >
                                <h3 className="font-semibold break-words">
                                    {barber.name}
                                </h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {!barber.is_active
                                        ? 'Tidak aktif'
                                        : {
                                              available: 'Tersedia',
                                              busy: 'Sedang melayani',
                                              break: 'Istirahat',
                                              off_duty: 'Tidak bertugas',
                                          }[barber.availability]}
                                </p>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {barber.accepts_queue
                                        ? 'Penerimaan antrean diizinkan'
                                        : 'Penerimaan antrean ditutup'}
                                </p>
                                <Link
                                    href={queues({
                                        query: { barber_id: barber.id },
                                    })}
                                    className="mt-3 inline-flex min-h-11 items-center text-sm text-primary hover:underline"
                                >
                                    Lihat antrean
                                </Link>
                                {barber.is_active && (
                                    <div className="mt-4">
                                        <AvailabilityControl
                                            key={`${barber.id}-${barber.availability}`}
                                            barber={barber}
                                            processing={processing.some(
                                                (ticket) =>
                                                    ticket.barber.id ===
                                                    barber.id,
                                            )}
                                        />
                                    </div>
                                )}
                            </article>
                        ))}
                    </div>
                    {!barbers.length && (
                        <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                            Belum ada barber. Tambahkan akun dan jadwal dari
                            menu Barber.
                        </p>
                    )}
                </section>
            </StaffPage>
        </>
    );
}
