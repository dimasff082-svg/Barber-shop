import { Link } from '@inertiajs/react';
import { QueueStatusBadge } from '@/components/queue/queue-status-badge';
import { TicketActions } from '@/components/staff/ticket-actions';
import type { StaffTicket } from '@/types/operations';

export function QueueCards({ tickets }: { tickets: StaffTicket[] }) {
    if (!tickets.length)
        return (
            <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                Belum ada pelanggan dalam antrean ini.
            </p>
        );
    return (
        <div className="grid gap-4">
            {tickets.map((ticket) => (
                <article
                    key={ticket.id}
                    className="min-w-0 rounded-2xl border bg-card p-5 sm:p-6"
                >
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <Link
                                href={ticket.detail_url}
                                className="inline-flex min-h-11 items-center text-3xl font-semibold text-primary tabular-nums hover:underline"
                            >
                                {ticket.queue_number}
                            </Link>
                            <p className="mt-1 font-medium break-words">
                                {ticket.customer_name}
                            </p>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {ticket.service.name} · {ticket.barber.name}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {ticket.source === 'online'
                                    ? 'Online'
                                    : 'QR lokasi'}{' '}
                                · {ticket.phone_masked}
                            </p>
                        </div>
                        <QueueStatusBadge status={ticket.status} />
                    </div>
                    <div className="mt-5">
                        <TicketActions ticket={ticket} />
                    </div>
                </article>
            ))}
        </div>
    );
}
