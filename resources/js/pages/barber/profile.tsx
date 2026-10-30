import { Head, Link } from '@inertiajs/react';
import { StaffPage } from '@/components/staff/staff-page';
import { weekdays } from '@/components/public/business-hours';
import { edit as editAccount } from '@/routes/profile';

type BarberProfile = {
    name: string;
    bio: string | null;
    specialization: string | null;
    queue_prefix: string;
    accepts_queue: boolean;
    schedules: {
        weekday: number;
        starts_at: string;
        ends_at: string;
        is_active: boolean;
    }[];
};
export default function BarberProfilePage({
    barber,
    timezone,
}: {
    barber: BarberProfile | null;
    timezone: string;
}) {
    return (
        <>
            <Head title="Profil Barber" />
            <StaffPage
                title="Profil Barber"
                description="Profil dan jadwal kerja resmi yang dikelola admin."
            >
                {barber ? (
                    <>
                        <section className="rounded-xl border bg-card p-6">
                            <h2 className="text-2xl font-semibold break-words">
                                {barber.name}
                            </h2>
                            <p className="mt-3 text-sm text-primary">
                                {barber.specialization ||
                                    'Spesialisasi belum ditambahkan'}
                            </p>
                            <p className="mt-4 text-sm leading-6 text-muted-foreground">
                                {barber.bio || 'Profil belum dilengkapi.'}
                            </p>
                            <p className="mt-4 text-sm">
                                Prefix antrean: {barber.queue_prefix} ·{' '}
                                {barber.accepts_queue
                                    ? 'Penerimaan diizinkan'
                                    : 'Penerimaan ditutup'}
                            </p>
                        </section>
                        <section className="rounded-xl border bg-card p-6">
                            <h2 className="text-xl font-semibold">
                                Jadwal mingguan
                            </h2>
                            <dl className="mt-5 space-y-3">
                                {barber.schedules
                                    .filter((row) => row.is_active)
                                    .map((row) => (
                                        <div
                                            key={row.weekday}
                                            className="flex flex-wrap justify-between gap-3 border-b pb-3 text-sm"
                                        >
                                            <dt>{weekdays[row.weekday]}</dt>
                                            <dd className="tabular-nums">
                                                {row.starts_at}–{row.ends_at}{' '}
                                                {timezone}
                                            </dd>
                                        </div>
                                    ))}
                            </dl>
                            {!barber.schedules.some((row) => row.is_active) && (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Jadwal aktif belum ditambahkan.
                                </p>
                            )}
                        </section>
                    </>
                ) : (
                    <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                        Profil belum ditautkan. Hubungi admin.
                    </p>
                )}
                <Link
                    href={editAccount()}
                    className="inline-flex min-h-11 items-center text-sm text-primary hover:underline"
                >
                    Pengaturan akun login
                </Link>
            </StaffPage>
        </>
    );
}
