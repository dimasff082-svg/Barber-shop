import { useForm } from '@inertiajs/react';
import { Download, ExternalLink, Plus, Printer, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { Pagination } from '@/components/staff/pagination';
import { StaffPage } from '@/components/staff/staff-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/admin/qr';

type Qr = {
    id: number;
    label: string | null;
    is_active: boolean;
    portal_url: string;
    image_url: string;
    print_url: string;
    download_url: string;
};
type Page<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

function QrCard({ qr }: { qr: Qr }) {
    const form = useForm({ label: qr.label ?? '', is_active: qr.is_active });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url(qr.id), { preserveScroll: true });
    };
    return (
        <article className="min-w-0 rounded-2xl border bg-card p-5 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="min-w-0 text-lg font-semibold break-words">
                    {qr.label || 'QR lokasi'}
                </h2>
                <span
                    className={`rounded-full border px-3 py-1 text-xs font-medium ${qr.is_active ? 'border-success/30 text-success' : 'text-muted-foreground'}`}
                >
                    {qr.is_active ? 'Aktif' : 'Nonaktif'}
                </span>
            </div>
            <div className="mx-auto mt-5 max-w-60 rounded-xl bg-white p-4">
                <img
                    src={qr.image_url}
                    alt={`QR lokasi ${qr.label || qr.id}`}
                    width={400}
                    height={400}
                    className="aspect-square w-full"
                />
            </div>
            <p className="mt-4 text-xs leading-5 break-all text-muted-foreground">
                {qr.portal_url}
            </p>
            <form onSubmit={submit} className="mt-5 space-y-4">
                <div>
                    <Label htmlFor={`qr-label-${qr.id}`}>Label lokasi</Label>
                    <Input
                        id={`qr-label-${qr.id}`}
                        className="mt-2 min-h-11"
                        value={form.data.label}
                        onChange={(event) =>
                            form.setData('label', event.target.value)
                        }
                        maxLength={100}
                        required
                    />
                    {form.errors.label && (
                        <p
                            role="alert"
                            className="mt-2 text-sm text-destructive"
                        >
                            {form.errors.label}
                        </p>
                    )}
                </div>
                <label className="flex min-h-11 items-center gap-3 text-sm">
                    <input
                        type="checkbox"
                        className="size-5 accent-primary"
                        checked={form.data.is_active}
                        onChange={(event) =>
                            form.setData('is_active', event.target.checked)
                        }
                    />
                    QR aktif untuk pendaftaran/check-in
                </label>
                <Button
                    className="min-h-11 w-full"
                    variant="outline"
                    disabled={form.processing}
                >
                    <Save className="size-4" aria-hidden="true" />
                    {form.processing ? 'Menyimpan…' : 'Simpan perubahan'}
                </Button>
            </form>
            <div className="mt-4 grid grid-cols-2 gap-3">
                <Button asChild className="min-h-11" variant="outline">
                    <a
                        href={qr.print_url}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Printer className="size-4" aria-hidden="true" />
                        Cetak
                    </a>
                </Button>
                <Button asChild className="min-h-11" variant="outline">
                    <a href={qr.download_url} download>
                        <Download className="size-4" aria-hidden="true" />
                        Unduh SVG
                    </a>
                </Button>
            </div>
            <a
                href={qr.portal_url}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-3 flex min-h-11 items-center justify-center gap-2 text-sm text-primary"
            >
                Buka portal
                <ExternalLink className="size-4" aria-hidden="true" />
            </a>
        </article>
    );
}

export default function QrCodes({ codes }: { codes: Page<Qr> }) {
    const form = useForm({ label: '', is_active: false });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };
    return (
        <StaffPage
            title="QR Lokasi"
            description="Buat QR untuk portal antrean dan check-in di studio. QR statis dapat dibagikan dan belum membuktikan kehadiran fisik."
        >
            <form
                onSubmit={submit}
                className="mb-6 max-w-2xl rounded-2xl border bg-card p-5 sm:p-6"
                aria-busy={form.processing}
            >
                <h2 className="text-lg font-semibold">Buat QR baru</h2>
                <Label htmlFor="new-qr-label" className="mt-5">
                    Label lokasi
                </Label>
                <Input
                    id="new-qr-label"
                    className="mt-2 min-h-11"
                    placeholder="Contoh: Meja penerima tamu"
                    value={form.data.label}
                    onChange={(event) =>
                        form.setData('label', event.target.value)
                    }
                    maxLength={100}
                    required
                />
                {form.errors.label && (
                    <p role="alert" className="mt-2 text-sm text-destructive">
                        {form.errors.label}
                    </p>
                )}
                <label className="my-4 flex min-h-11 items-center gap-3 text-sm">
                    <input
                        type="checkbox"
                        className="size-5 accent-primary"
                        checked={form.data.is_active}
                        onChange={(event) =>
                            form.setData('is_active', event.target.checked)
                        }
                    />
                    Aktifkan QR setelah dibuat
                </label>
                <Button
                    className="min-h-11 w-full sm:w-auto"
                    disabled={form.processing}
                >
                    <Plus className="size-4" aria-hidden="true" />
                    {form.processing ? 'Membuat…' : 'Buat QR lokasi'}
                </Button>
            </form>
            <div className="grid items-start gap-6 md:grid-cols-2 xl:grid-cols-3">
                {codes.data.map((qr) => (
                    <QrCard key={qr.id} qr={qr} />
                ))}
            </div>
            {!codes.data.length && (
                <p className="rounded-2xl border p-8 text-center text-sm text-muted-foreground">
                    Belum ada QR lokasi. Buat QR, aktifkan, lalu cetak untuk
                    dipasang di studio.
                </p>
            )}
            <div className="mt-6">
                <Pagination links={codes.links} />
            </div>
        </StaffPage>
    );
}
