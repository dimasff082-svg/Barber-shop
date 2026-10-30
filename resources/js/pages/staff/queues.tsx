import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    QueueStatusBadge,
    queueStatusPresentation,
} from '@/components/queue/queue-status-badge';
import { StaffPage } from '@/components/staff/staff-page';
import { TicketActions } from '@/components/staff/ticket-actions';
import { Pagination } from '@/components/staff/pagination';
import { OperationStats } from '@/components/staff/operation-stats';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as adminQueues } from '@/routes/admin/queues';
import { index as barberQueues } from '@/routes/barber/queues';
import { history as barberHistory } from '@/routes/barber';
import type { StaffQueueProps } from '@/types/operations';

const controlClass =
    'min-h-11 w-full min-w-0 rounded-lg border bg-background px-3 text-sm';
export default function StaffQueues({
    tickets,
    filters,
    is_admin,
    history,
    barbers,
    stats,
}: StaffQueueProps) {
    const form = useForm(filters);
    const target = is_admin
        ? adminQueues.url()
        : history
          ? barberHistory.url()
          : barberQueues.url();
    return (
        <>
            <Head title={history ? 'Riwayat Layanan' : 'Daftar Antrean'} />
            <StaffPage
                title={history ? 'Riwayat Layanan' : 'Daftar Antrean'}
                description={
                    is_admin
                        ? 'Pantau antrean seluruh barber. Perubahan status tetap mengikuti aturan layanan.'
                        : 'Hanya antrean milik barber Anda yang ditampilkan.'
                }
            >
                <OperationStats stats={stats} />
                <form
                    data-realtime-pause={
                        JSON.stringify(form.data) !== JSON.stringify(filters) ||
                        form.processing
                            ? 'true'
                            : undefined
                    }
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(target, {
                            preserveState: true,
                            preserveScroll: true,
                        });
                    }}
                    className="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 xl:grid-cols-5"
                >
                    <div>
                        <Label htmlFor="queue-date">Tanggal bisnis</Label>
                        <Input
                            id="queue-date"
                            type="date"
                            value={form.data.date}
                            onChange={(event) =>
                                form.setData('date', event.target.value)
                            }
                            className="mt-2 min-h-11"
                        />
                    </div>
                    {is_admin && (
                        <div>
                            <Label htmlFor="queue-barber">Barber</Label>
                            <select
                                id="queue-barber"
                                className={`${controlClass} mt-2`}
                                value={form.data.barber_id}
                                onChange={(event) =>
                                    form.setData(
                                        'barber_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Semua barber</option>
                                {barbers.map((barber) => (
                                    <option key={barber.id} value={barber.id}>
                                        {barber.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <div>
                        <Label htmlFor="queue-status">Status</Label>
                        <select
                            id="queue-status"
                            className={`${controlClass} mt-2`}
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                        >
                            <option value="">Semua status</option>
                            {Object.entries(queueStatusPresentation)
                                .filter(
                                    ([key]) =>
                                        !history ||
                                        [
                                            'completed',
                                            'cancelled',
                                            'missed',
                                        ].includes(key),
                                )
                                .map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label.label}
                                    </option>
                                ))}
                        </select>
                    </div>
                    <div>
                        <Label htmlFor="queue-source">Sumber</Label>
                        <select
                            id="queue-source"
                            className={`${controlClass} mt-2`}
                            value={form.data.source}
                            onChange={(event) =>
                                form.setData('source', event.target.value)
                            }
                        >
                            <option value="">Semua sumber</option>
                            <option value="online">Online</option>
                            <option value="onsite">QR lokasi</option>
                        </select>
                    </div>
                    <div className="flex items-end gap-2">
                        <Button className="min-h-11" disabled={form.processing}>
                            Terapkan
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={() => router.reload()}
                        >
                            Perbarui
                        </Button>
                    </div>
                    {Object.keys(form.errors).length > 0 && (
                        <p
                            role="alert"
                            className="text-sm text-destructive sm:col-span-2"
                        >
                            {Object.values(form.errors).join(' ')}
                        </p>
                    )}
                </form>
                <p className="text-sm text-muted-foreground">
                    {tickets.total} tiket sesuai filter. Perubahan diperiksa
                    secara otomatis selama halaman aktif.
                </p>
                {tickets.data.length ? (
                    <>
                        <div className="grid gap-4 md:hidden">
                            {tickets.data.map((ticket) => (
                                <article
                                    key={ticket.id}
                                    className="min-w-0 rounded-xl border bg-card p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <Link
                                            href={ticket.detail_url}
                                            className="inline-flex min-h-11 items-center text-xl font-semibold text-primary"
                                        >
                                            {ticket.queue_number}
                                        </Link>
                                        <QueueStatusBadge
                                            status={ticket.status}
                                        />
                                    </div>
                                    <p className="mt-2 font-medium break-words">
                                        {ticket.customer_name}
                                    </p>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {ticket.barber.name} ·{' '}
                                        {ticket.service.name}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {ticket.source === 'online'
                                            ? 'Online'
                                            : 'QR lokasi'}{' '}
                                        · {ticket.phone_masked}
                                    </p>
                                    <div className="mt-4">
                                        <TicketActions ticket={ticket} />
                                    </div>
                                </article>
                            ))}
                        </div>
                        <div className="hidden min-w-0 overflow-x-auto rounded-xl border md:block">
                            <table className="w-full min-w-200 text-left text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        {[
                                            'Nomor',
                                            'Pelanggan',
                                            'Barber / layanan',
                                            'Status',
                                            'Aksi',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                className="p-4 font-medium"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {tickets.data.map((ticket) => (
                                        <tr
                                            key={ticket.id}
                                            className="border-t align-top"
                                        >
                                            <td className="p-4">
                                                <Link
                                                    href={ticket.detail_url}
                                                    className="inline-flex min-h-11 items-center font-semibold text-primary hover:underline"
                                                >
                                                    {ticket.queue_number}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {ticket.source === 'online'
                                                        ? 'Online'
                                                        : 'QR lokasi'}
                                                </p>
                                            </td>
                                            <td className="p-4">
                                                <p className="font-medium">
                                                    {ticket.customer_name}
                                                </p>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {ticket.phone_masked}
                                                </p>
                                            </td>
                                            <td className="p-4">
                                                <p>{ticket.barber.name}</p>
                                                <p className="mt-2 text-muted-foreground">
                                                    {ticket.service.name}
                                                </p>
                                            </td>
                                            <td className="p-4">
                                                <QueueStatusBadge
                                                    status={ticket.status}
                                                />
                                            </td>
                                            <td className="p-4">
                                                <TicketActions
                                                    ticket={ticket}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={tickets.links} />
                    </>
                ) : (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Belum ada antrean sesuai filter ini.
                    </p>
                )}
            </StaffPage>
        </>
    );
}
