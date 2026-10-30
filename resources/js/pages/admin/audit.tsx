import { router, useForm } from '@inertiajs/react';
import { Pagination, type PaginationLink } from '@/components/staff/pagination';
import { StaffPage } from '@/components/staff/staff-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Entry = {
    id: number;
    actor: string;
    action: string;
    target_type: string;
    target_id: string | null;
    metadata: Record<string, unknown>;
    created_at: string;
};
export default function Audit({
    entries,
    filters,
    timezone,
}: {
    entries: { data: Entry[]; links: PaginationLink[]; total: number };
    filters: { target_type: string; action: string };
    timezone: string;
}) {
    const form = useForm(filters);
    return (
        <StaffPage
            title="Audit administrasi"
            description="Riwayat perubahan pengaturan dan data usaha. Perubahan status antrean tersedia pada detail tiket."
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/admin/audit', form.data, {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
                className="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-3"
            >
                <div>
                    <Label htmlFor="audit-type">Jenis data</Label>
                    <Input
                        id="audit-type"
                        className="mt-2 min-h-11"
                        value={form.data.target_type}
                        onChange={(e) =>
                            form.setData('target_type', e.target.value)
                        }
                        placeholder="Semua jenis"
                    />
                </div>
                <div>
                    <Label htmlFor="audit-action">Tindakan</Label>
                    <Input
                        id="audit-action"
                        className="mt-2 min-h-11"
                        value={form.data.action}
                        onChange={(e) => form.setData('action', e.target.value)}
                        placeholder="Semua tindakan"
                    />
                </div>
                <Button className="min-h-11 self-end">Terapkan filter</Button>
            </form>
            <p className="text-sm text-muted-foreground">
                {entries.total} catatan · Zona waktu {timezone}
            </p>
            {entries.data.length === 0 ? (
                <p className="rounded-xl border bg-card p-8 text-center text-muted-foreground">
                    Belum ada catatan yang sesuai.
                </p>
            ) : (
                <div className="space-y-4">
                    {entries.data.map((entry) => (
                        <article
                            key={entry.id}
                            className="min-w-0 rounded-xl border bg-card p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold break-words">
                                        {entry.action}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {entry.actor} · {entry.target_type}{' '}
                                        {entry.target_id &&
                                            `#${entry.target_id}`}
                                    </p>
                                </div>
                                <time
                                    className="text-xs text-muted-foreground"
                                    dateTime={entry.created_at}
                                >
                                    {new Intl.DateTimeFormat('id-ID', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                        timeZone: timezone,
                                    }).format(new Date(entry.created_at))}
                                </time>
                            </div>
                            <details className="mt-4">
                                <summary className="min-h-11 cursor-pointer py-3 text-sm text-primary">
                                    Detail perubahan
                                </summary>
                                <pre className="max-w-full overflow-x-auto rounded-lg bg-surface-raised p-4 text-xs leading-6">
                                    {JSON.stringify(entry.metadata, null, 2)}
                                </pre>
                            </details>
                        </article>
                    ))}
                </div>
            )}
            <Pagination links={entries.links} />
        </StaffPage>
    );
}
