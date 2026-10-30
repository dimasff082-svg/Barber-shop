import { Head, Link } from '@inertiajs/react';
import { QueueStatusBadge } from '@/components/queue/queue-status-badge';
import { StaffPage } from '@/components/staff/staff-page';
import { TicketActions } from '@/components/staff/ticket-actions';
import { index as adminQueues } from '@/routes/admin/queues';
import { index as barberQueues } from '@/routes/barber/queues';
import type { StaffTicket, QueueAuditEvent } from '@/types/operations';

export default function QueueDetail({
    ticket,
    events,
    is_admin,
    timezone,
}: {
    ticket: StaffTicket;
    events: QueueAuditEvent[];
    is_admin: boolean;
    timezone: string;
}) {
    return (
        <>
            <Head title={`Antrean ${ticket.queue_number}`} />
            <StaffPage
                title={ticket.queue_number}
                description={`Tanggal bisnis ${ticket.queue_date} · ${ticket.source === 'online' ? 'Online' : 'QR lokasi'}`}
            >
                <Link
                    href={is_admin ? adminQueues() : barberQueues()}
                    className="inline-flex min-h-11 items-center text-sm text-primary hover:underline"
                >
                    Kembali ke daftar antrean
                </Link>
                <section className="space-y-5 rounded-2xl border bg-card p-5 sm:p-8">
                    <QueueStatusBadge status={ticket.status} />
                    <h2 className="text-xl font-semibold break-words">
                        {ticket.customer_name}
                    </h2>
                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">Barber</dt>
                            <dd className="mt-1 font-medium">
                                {ticket.barber.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Layanan</dt>
                            <dd className="mt-1 font-medium">
                                {ticket.service.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Harga / durasi saat mendaftar
                            </dt>
                            <dd className="mt-1">
                                {new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    maximumFractionDigits: 0,
                                }).format(Number(ticket.price))}{' '}
                                · {ticket.duration_minutes} menit
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Nomor HP tersamarkan
                            </dt>
                            <dd className="mt-1">{ticket.phone_masked}</dd>
                        </div>
                    </dl>
                    {ticket.cancellation_reason && (
                        <p className="rounded-lg border p-4 text-sm">
                            Alasan pembatalan: {ticket.cancellation_reason}
                        </p>
                    )}
                    <TicketActions ticket={ticket} />
                </section>
                <section className="space-y-4">
                    <h2 className="text-xl font-semibold">Jejak perubahan</h2>
                    <ol className="space-y-3">
                        {events.map((event) => (
                            <li
                                key={event.id}
                                className="rounded-xl border bg-card p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <span className="font-medium">
                                        {event.type.replaceAll('_', ' ')}
                                    </span>
                                    <QueueStatusBadge
                                        status={event.new_status}
                                    />
                                </div>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    {event.actor} ·{' '}
                                    {new Date(event.created_at).toLocaleString(
                                        'id-ID',
                                        { timeZone: timezone },
                                    )}{' '}
                                    {timezone}
                                </p>
                                {event.reason && (
                                    <p className="mt-2 text-sm break-words">
                                        {event.reason}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                </section>
            </StaffPage>
        </>
    );
}
