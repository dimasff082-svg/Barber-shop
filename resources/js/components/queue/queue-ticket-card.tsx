import { CalendarDays, Scissors, UserRound } from 'lucide-react';
import {
    QueueStatusBadge,
    queueStatusPresentation,
} from '@/components/queue/queue-status-badge';
import type { CustomerTicket } from '@/types/customer';
import { TicketIdentifier } from '@/components/queue/ticket-identifier';

export function formatTicketDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${value}T12:00:00Z`));
}

export function QueueTicketCard({ ticket }: { ticket: CustomerTicket }) {
    const { minutes_min: minimum, minutes_max: maximum } = ticket.estimate;
    return (
        <>
            <article
                className="overflow-hidden rounded-3xl border border-primary/40 bg-card"
                aria-label="Tiket antrean Anda"
            >
                <div className="px-5 py-8 text-center sm:px-8 sm:py-10">
                    <QueueStatusBadge status={ticket.status} />
                    <p className="mt-7 text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Nomor antrean Anda
                    </p>
                    <p className="mt-2 font-display text-[clamp(3.5rem,16vw,5rem)] leading-tight font-bold tracking-tight break-all text-foreground tabular-nums">
                        {ticket.queue_number}
                    </p>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {ticket.customer_name}
                    </p>
                </div>
                <TicketIdentifier id={ticket.id} />
                <dl className="mx-5 grid grid-cols-1 gap-5 border-t border-border py-6 sm:mx-8 sm:grid-cols-2">
                    <div>
                        <dt className="flex items-center gap-2 text-xs text-muted-foreground uppercase">
                            <UserRound className="size-4" aria-hidden="true" />
                            Barber
                        </dt>
                        <dd className="mt-2 font-semibold break-words">
                            {ticket.barber.name}
                        </dd>
                    </div>
                    <div>
                        <dt className="flex items-center gap-2 text-xs text-muted-foreground uppercase">
                            <Scissors className="size-4" aria-hidden="true" />
                            Layanan
                        </dt>
                        <dd className="mt-2 font-semibold break-words">
                            {ticket.service.name}
                        </dd>
                        <dd className="mt-1 text-xs leading-5 text-muted-foreground">
                            {new Intl.NumberFormat('id-ID', {
                                style: 'currency',
                                currency: 'IDR',
                                maximumFractionDigits: 0,
                            }).format(Number(ticket.price))}
                            {' · '}
                            {ticket.duration_minutes} menit perkiraan layanan
                        </dd>
                    </div>
                    <div>
                        <dt className="flex items-center gap-2 text-xs text-muted-foreground uppercase">
                            <CalendarDays
                                className="size-4"
                                aria-hidden="true"
                            />
                            Tanggal berlaku
                        </dt>
                        <dd className="mt-2 text-sm font-medium">
                            {formatTicketDate(ticket.queue_date)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground uppercase">
                            Sumber
                        </dt>
                        <dd className="mt-2 text-sm font-medium">
                            {ticket.source === 'online'
                                ? 'Online · dari rumah'
                                : 'On-site · QR lokasi'}
                        </dd>
                    </div>
                </dl>
            </article>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:gap-4">
                <div className="rounded-2xl border border-border bg-card p-4 sm:p-6">
                    <p className="text-xs leading-5 text-muted-foreground uppercase">
                        Siap / dilayani di depan
                    </p>
                    <p className="mt-3 text-3xl font-semibold tabular-nums">
                        {ticket.estimate.ahead}
                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                            orang
                        </span>
                    </p>
                </div>
                <div className="rounded-2xl border border-border bg-card p-4 sm:p-6">
                    <p className="text-xs leading-5 text-muted-foreground uppercase">
                        Perkiraan tunggu
                    </p>
                    <p className="mt-3 text-2xl font-semibold tabular-nums">
                        {minimum === null || maximum === null
                            ? '—'
                            : minimum === maximum
                              ? minimum
                              : `${minimum}–${maximum}`}
                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                            {minimum !== null ? 'menit' : ''}
                        </span>
                    </p>
                </div>
            </div>
            <p className="mt-3 text-xs leading-5 text-muted-foreground">
                {ticket.estimate.label} Pelanggan yang belum check-in dapat ikut
                urutan berikutnya jika tiba dalam toleransi.
            </p>
            <div className="mt-6 rounded-2xl border border-border bg-card p-5">
                <h2 className="font-semibold">
                    {queueStatusPresentation[ticket.status].label}
                </h2>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    {queueStatusPresentation[ticket.status].description}
                </p>
            </div>
        </>
    );
}
