import { Link } from '@inertiajs/react';
import { ArrowUpRight, Clock3, UserRound } from 'lucide-react';
import { weekdays } from '@/components/public/business-hours';
import { ContentImage } from '@/components/public/content-image';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/queue';
import type { PublicBarber } from '@/types/customer';

const availability = {
    available: 'Tersedia',
    busy: 'Sedang Melayani',
    break: 'Istirahat',
    off_duty: 'Tidak Bertugas',
};
const availabilityColor = {
    available: 'text-success',
    busy: 'text-info',
    break: 'text-warning',
    off_duty: 'text-muted-foreground',
};

export function BarberCard({ barber }: { barber: PublicBarber }) {
    return (
        <article className="flex min-w-0 flex-col overflow-hidden rounded-xl border border-border bg-card">
            <div className="relative aspect-[4/5] overflow-hidden">
                {barber.photo ? (
                    <ContentImage
                        src={barber.photo}
                        alt={`Potret ${barber.name}`}
                        width={600}
                        height={750}
                    />
                ) : (
                    <div className="flex h-full flex-col items-center justify-center gap-3 bg-surface-raised text-muted-foreground">
                        <UserRound aria-hidden="true" className="size-14" />
                        <span className="text-sm">
                            Foto barber belum tersedia
                        </span>
                    </div>
                )}
            </div>
            <div className="flex flex-1 flex-col p-6">
                <p
                    className={`mb-3 text-sm font-medium ${availabilityColor[barber.availability]}`}
                >
                    {availability[barber.availability]}
                </p>
                <h2 className="font-display text-2xl uppercase">
                    {barber.name}
                </h2>
                {barber.specialization && (
                    <p className="mt-2 text-sm text-primary">
                        {barber.specialization}
                    </p>
                )}
                {barber.bio && (
                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                        {barber.bio}
                    </p>
                )}
                <div className="mt-5 border-t border-border pt-4">
                    <p className="flex items-center gap-2 text-sm">
                        <Clock3
                            aria-hidden="true"
                            className="size-4 text-primary"
                        />
                        {barber.waiting_count} antrean menunggu hari ini
                    </p>
                    <p className="mt-3 text-xs font-semibold tracking-wider uppercase">
                        Jadwal rutin
                    </p>
                    {barber.schedules.length ? (
                        <ul className="mt-2 space-y-1.5 text-sm text-muted-foreground">
                            {barber.schedules.map((schedule) => (
                                <li
                                    key={schedule.weekday}
                                    className="flex flex-wrap justify-between gap-2"
                                >
                                    <span>{weekdays[schedule.weekday]}</span>
                                    <span className="tabular-nums">
                                        {schedule.starts_at.slice(0, 5)}–
                                        {schedule.ends_at.slice(0, 5)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Jadwal belum tersedia.
                        </p>
                    )}
                </div>
                <div className="mt-auto pt-5">
                    {barber.registration.allowed ? (
                        <Button asChild className="min-h-11 w-full">
                            <Link
                                href={index({ query: { barber: barber.id } })}
                            >
                                Pilih Barber
                                <ArrowUpRight
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Link>
                        </Button>
                    ) : (
                        <>
                            <Button disabled className="min-h-11 w-full">
                                Belum menerima antrean
                            </Button>
                            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                {barber.registration.reason ||
                                    'Silakan cek kembali nanti.'}
                            </p>
                        </>
                    )}
                </div>
            </div>
        </article>
    );
}
