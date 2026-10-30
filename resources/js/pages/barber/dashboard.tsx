import { Head, Link, useForm, router } from '@inertiajs/react';
import { StaffPage } from '@/components/staff/staff-page';
import { OperationStats } from '@/components/staff/operation-stats';
import { QueueCards } from '@/components/staff/queue-cards';
import { AvailabilityControl } from '@/components/staff/availability-control';
import { Button } from '@/components/ui/button';
import { index as queues } from '@/routes/barber/queues';
import { call } from '@/routes/queue/staff';
import type { DashboardProps } from '@/types/operations';

export default function BarberDashboard({
    date,
    timezone,
    stats,
    barber,
    profile_missing,
    processing,
    next,
    can_call,
}: DashboardProps) {
    const form = useForm({});
    const errors: Record<string, string | undefined> = form.errors;
    return (
        <>
            <Head title="Dashboard Barber" />
            <StaffPage
                title="Ruang Kerja Barber"
                description={`${date} · ${timezone}. Panggil hanya pelanggan yang sudah hadir.`}
            >
                {profile_missing ? (
                    <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                        Profil barber belum ditautkan ke akun ini. Hubungi admin
                        untuk melengkapi profil dan jadwal.
                    </p>
                ) : (
                    <>
                        {barber && (
                            <AvailabilityControl
                                key={barber.availability}
                                barber={barber}
                                processing={processing.length > 0}
                            />
                        )}
                        <OperationStats stats={stats} />
                        <section className="space-y-4">
                            <h2 className="text-xl font-semibold">
                                Sedang diproses
                            </h2>
                            <QueueCards tickets={processing} />
                        </section>
                        <section className="space-y-4 rounded-2xl border bg-card p-5 sm:p-6">
                            <h2 className="text-xl font-semibold">
                                Pelanggan siap berikutnya
                            </h2>
                            {next ? (
                                <p className="text-sm leading-6">
                                    <strong className="text-2xl text-primary tabular-nums">
                                        {next.queue_number}
                                    </strong>
                                    <br />
                                    {next.customer_name} · {next.service.name}
                                </p>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada pelanggan yang sudah check-in.
                                </p>
                            )}
                            <Button
                                className="min-h-12 w-full sm:w-auto"
                                disabled={
                                    !can_call || form.processing || !barber
                                }
                                onClick={() =>
                                    barber &&
                                    form.post(call.url(barber.id), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                {form.processing
                                    ? 'Memanggil…'
                                    : 'Panggil Berikutnya'}
                            </Button>
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {errors.queue}
                            </p>
                        </section>
                    </>
                )}
                <div className="flex flex-wrap gap-3">
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href={queues()}>Lihat Semua Antrean</Link>
                    </Button>
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={() => router.reload()}
                    >
                        Perbarui Data
                    </Button>
                </div>
            </StaffPage>
        </>
    );
}
