import { Head, useForm } from '@inertiajs/react';
import { StaffPage } from '@/components/staff/staff-page';
import { StatCard } from '@/components/staff/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/reports';

type ReportProps = {
    range: { from: string; to: string };
    totals: {
        total: number;
        completed: number;
        cancelled: number;
        missed: number;
        online: number;
        onsite: number;
        avg_wait_minutes: number | null;
        wait_samples: number;
    };
    days: {
        date: string;
        total: number;
        completed: number;
        cancelled: number;
        missed: number;
        online: number;
        onsite: number;
    }[];
    barbers: { id: number; name: string; completed: number }[];
    services: { id: number; name: string; completed: number }[];
    wait_definition: string;
};
export default function Reports({
    range,
    totals,
    days,
    barbers,
    services,
    wait_definition,
}: ReportProps) {
    const form = useForm(range);
    return (
        <>
            <Head title="Laporan Operasional" />
            <StaffPage
                title="Laporan Operasional"
                description="Ringkasan tiket dan layanan. Tidak mencatat pembayaran atau omzet."
            >
                <form
                    data-realtime-pause={
                        JSON.stringify(form.data) !== JSON.stringify(range) ||
                        form.processing
                            ? 'true'
                            : undefined
                    }
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(index.url(), {
                            preserveState: true,
                            preserveScroll: true,
                        });
                    }}
                    className="flex flex-col gap-4 rounded-xl border bg-card p-5 sm:flex-row sm:items-end"
                >
                    <div>
                        <Label htmlFor="report-from">Dari tanggal</Label>
                        <Input
                            id="report-from"
                            type="date"
                            className="mt-2 min-h-11"
                            value={form.data.from}
                            onChange={(event) =>
                                form.setData('from', event.target.value)
                            }
                            required
                        />
                    </div>
                    <div>
                        <Label htmlFor="report-to">Sampai tanggal</Label>
                        <Input
                            id="report-to"
                            type="date"
                            className="mt-2 min-h-11"
                            value={form.data.to}
                            onChange={(event) =>
                                form.setData('to', event.target.value)
                            }
                            required
                        />
                    </div>
                    <Button className="min-h-11" disabled={form.processing}>
                        Tampilkan
                    </Button>
                </form>
                {Object.keys(form.errors).length > 0 && (
                    <p role="alert" className="text-sm text-destructive">
                        {Object.values(form.errors).join(' ')}
                    </p>
                )}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard label="Tiket terbit" value={totals.total} />
                    <StatCard
                        label="Layanan selesai"
                        value={totals.completed}
                    />
                    <StatCard
                        label="Batal / terlewat"
                        value={`${totals.cancelled} / ${totals.missed}`}
                    />
                    <StatCard
                        label="Rata-rata tunggu"
                        value={
                            totals.avg_wait_minutes === null
                                ? '—'
                                : `${totals.avg_wait_minutes} menit`
                        }
                        description={`${totals.wait_samples} sampel`}
                    />
                </div>
                <p className="text-sm leading-6 text-muted-foreground">
                    {wait_definition} Online {totals.online} tiket · QR lokasi{' '}
                    {totals.onsite} tiket.
                </p>
                <section className="space-y-4">
                    <h2 className="text-xl font-semibold">Tiket per hari</h2>
                    {days.length ? (
                        <div className="min-w-0 overflow-x-auto rounded-xl border">
                            <table className="w-full min-w-150 text-left text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        {[
                                            'Tanggal',
                                            'Total',
                                            'Online',
                                            'QR',
                                            'Selesai',
                                            'Batal',
                                            'Terlewat',
                                        ].map((value) => (
                                            <th
                                                key={value}
                                                className="p-3 font-medium"
                                            >
                                                {value}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {days.map((day) => (
                                        <tr key={day.date} className="border-t">
                                            {[
                                                day.date,
                                                day.total,
                                                day.online,
                                                day.onsite,
                                                day.completed,
                                                day.cancelled,
                                                day.missed,
                                            ].map((value, i) => (
                                                <td
                                                    key={i}
                                                    className="p-3 tabular-nums"
                                                >
                                                    {value}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                            Belum ada tiket pada rentang ini.
                        </p>
                    )}
                </section>
                <div className="grid gap-6 lg:grid-cols-2">
                    {[
                        { title: 'Selesai per barber', rows: barbers },
                        { title: 'Selesai per layanan', rows: services },
                    ].map((group) => (
                        <section
                            key={group.title}
                            className="rounded-xl border bg-card p-5"
                        >
                            <h2 className="text-lg font-semibold">
                                {group.title}
                            </h2>
                            <dl className="mt-5 space-y-3">
                                {group.rows.map((row) => (
                                    <div
                                        key={row.id}
                                        className="flex min-w-0 justify-between gap-4 border-b pb-3 text-sm"
                                    >
                                        <dt className="min-w-0 break-words">
                                            {row.name}
                                        </dt>
                                        <dd className="font-semibold tabular-nums">
                                            {row.completed}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            {!group.rows.length && (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Belum ada layanan selesai.
                                </p>
                            )}
                        </section>
                    ))}
                </div>
            </StaffPage>
        </>
    );
}
