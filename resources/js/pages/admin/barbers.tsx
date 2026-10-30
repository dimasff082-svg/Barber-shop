import { useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import {
    CatalogArchive,
    CatalogErrorSummary,
    CatalogField,
    CatalogPagination,
    CatalogSearch,
    CatalogToggle,
    type CatalogPage,
} from '@/components/admin/catalog-fields';
import { StaffPage } from '@/components/staff/staff-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/barbers';

type BarberRow = {
    id: number;
    name: string;
    email: string;
    queue_prefix: string;
    specialization: string | null;
    bio: string | null;
    is_active: boolean;
    accepts_queue: boolean;
    daily_capacity: number | null;
    availability: string;
    image_url: string | null;
    queue_locked: boolean;
    update_url: string;
    archive_url: string;
};

function BarberEditor({
    barber,
    storeUrl,
    close,
}: {
    barber: BarberRow | null;
    storeUrl: string;
    close: () => void;
}) {
    const form = useForm({
        name: barber?.name ?? '',
        email: barber?.email ?? '',
        password: '',
        password_confirmation: '',
        queue_prefix: barber?.queue_prefix ?? '',
        specialization: barber?.specialization ?? '',
        bio: barber?.bio ?? '',
        is_active: barber?.is_active ?? false,
        accepts_queue: barber?.accepts_queue ?? false,
        daily_capacity: barber?.daily_capacity?.toString() ?? '',
        image: null as File | null,
        remove_image: false,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            ...(barber ? { _method: 'put' } : {}),
        }));
        form.post(barber?.update_url ?? storeUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: close,
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };
    return (
        <form
            onSubmit={submit}
            className="space-y-5"
            aria-busy={form.processing}
        >
            <fieldset disabled={form.processing} className="grid min-w-0 gap-5">
                <CatalogField
                    id="barber-name"
                    label="Nama barber"
                    error={form.errors.name}
                >
                    <Input
                        id="barber-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        maxLength={100}
                        required
                        className="min-h-11"
                        aria-describedby="barber-name-error"
                    />
                </CatalogField>
                <CatalogField
                    id="barber-email"
                    label="Email login staff"
                    error={form.errors.email}
                >
                    <Input
                        id="barber-email"
                        type="email"
                        autoComplete="off"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        maxLength={255}
                        required
                        className="min-h-11"
                        aria-describedby="barber-email-error"
                    />
                </CatalogField>
                <div className="grid gap-5 sm:grid-cols-2">
                    <CatalogField
                        id="barber-password"
                        label={
                            barber
                                ? 'Password baru (opsional)'
                                : 'Password awal'
                        }
                        error={form.errors.password}
                        description="Minimal 12 karakter: huruf besar/kecil, angka dan simbol."
                    >
                        <Input
                            id="barber-password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            required={!barber}
                            className="min-h-11"
                            aria-describedby="barber-password-help barber-password-error"
                        />
                    </CatalogField>
                    <CatalogField
                        id="barber-password-confirm"
                        label="Konfirmasi password"
                        error={form.errors.password_confirmation}
                    >
                        <Input
                            id="barber-password-confirm"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            required={Boolean(form.data.password)}
                            className="min-h-11"
                        />
                    </CatalogField>
                </div>
                <div className="grid gap-5 sm:grid-cols-2">
                    <CatalogField
                        id="barber-prefix"
                        label="Prefix antrean"
                        error={form.errors.queue_prefix}
                        description={
                            barber?.queue_locked
                                ? 'Prefix dikunci karena nomor pernah diterbitkan.'
                                : 'Satu sampai tiga huruf A–Z, misalnya A.'
                        }
                    >
                        <Input
                            id="barber-prefix"
                            value={form.data.queue_prefix}
                            onChange={(event) =>
                                form.setData(
                                    'queue_prefix',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            readOnly={barber?.queue_locked}
                            maxLength={3}
                            required
                            className="min-h-11 uppercase"
                            aria-describedby="barber-prefix-help barber-prefix-error"
                        />
                    </CatalogField>
                    <CatalogField
                        id="barber-capacity"
                        label="Kapasitas harian (opsional)"
                        error={form.errors.daily_capacity}
                        description="Kosong mengikuti kapasitas usaha."
                    >
                        <Input
                            id="barber-capacity"
                            type="number"
                            min={1}
                            max={32767}
                            value={form.data.daily_capacity}
                            onChange={(event) =>
                                form.setData(
                                    'daily_capacity',
                                    event.target.value,
                                )
                            }
                            className="min-h-11"
                        />
                    </CatalogField>
                </div>
                <CatalogField
                    id="barber-specialization"
                    label="Spesialisasi"
                    error={form.errors.specialization}
                >
                    <Input
                        id="barber-specialization"
                        value={form.data.specialization}
                        onChange={(event) =>
                            form.setData('specialization', event.target.value)
                        }
                        maxLength={255}
                        className="min-h-11"
                    />
                </CatalogField>
                <CatalogField
                    id="barber-bio"
                    label="Profil singkat"
                    error={form.errors.bio}
                >
                    <textarea
                        id="barber-bio"
                        value={form.data.bio}
                        onChange={(event) =>
                            form.setData('bio', event.target.value)
                        }
                        maxLength={2000}
                        rows={3}
                        className="min-h-24 w-full rounded-md border bg-background p-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                    />
                </CatalogField>
                <CatalogField
                    id="barber-image"
                    label="Foto barber"
                    error={form.errors.image}
                    description="JPG, PNG, WebP atau AVIF, maksimal 2 MB. Gunakan foto dengan izin pemilik."
                >
                    <Input
                        id="barber-image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/avif"
                        onChange={(event) =>
                            form.setData(
                                'image',
                                event.target.files?.[0] ?? null,
                            )
                        }
                        className="min-h-11"
                    />
                </CatalogField>
                {barber?.image_url && (
                    <CatalogToggle
                        id="barber-remove-image"
                        label="Lepaskan foto profil saat ini"
                        checked={form.data.remove_image}
                        onChange={(checked) =>
                            form.setData('remove_image', checked)
                        }
                    />
                )}
                <CatalogToggle
                    id="barber-active"
                    label="Akun dan profil aktif"
                    checked={form.data.is_active}
                    onChange={(checked) => form.setData('is_active', checked)}
                    description="Barber dapat login saat aktif. Penonaktifan tidak membatalkan tiket yang sudah diterbitkan."
                />
                <CatalogToggle
                    id="barber-accepts"
                    label="Menerima pendaftaran antrean"
                    checked={form.data.accepts_queue}
                    onChange={(checked) =>
                        form.setData('accepts_queue', checked)
                    }
                    description="Jam buka, jadwal dan status kerja tetap diperiksa. Barber baru mulai dengan status Tidak Bertugas."
                />
            </fieldset>
            <CatalogErrorSummary errors={form.errors} />
            <div className="flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={close}
                    className="min-h-11"
                >
                    Kembali
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="min-h-11"
                >
                    {form.processing && (
                        <LoaderCircle
                            className="size-4 animate-spin"
                            aria-hidden="true"
                        />
                    )}
                    Simpan barber
                </Button>
            </div>
        </form>
    );
}

export default function Barbers({
    barbers,
    filters,
    store_url,
}: {
    barbers: CatalogPage<BarberRow>;
    filters: { search: string };
    store_url: string;
}) {
    const [editing, setEditing] = useState<BarberRow | null | undefined>(
        undefined,
    );
    const [archive, setArchive] = useState<{
        name: string;
        url: string;
    } | null>(null);
    const labels: Record<string, string> = {
        available: 'Tersedia',
        busy: 'Sedang melayani',
        break: 'Istirahat',
        off_duty: 'Tidak bertugas',
    };
    return (
        <StaffPage
            title="Barber & akun staff"
            description="Kelola profil, akses login, prefix dan penerimaan antrean barber."
        >
            <div className="flex flex-col justify-between gap-4 sm:flex-row">
                <CatalogSearch search={filters.search} url={index.url()} />
                <Button onClick={() => setEditing(null)} className="min-h-11">
                    <Plus className="size-4" aria-hidden="true" />
                    Tambah barber
                </Button>
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                {barbers.data.map((barber) => (
                    <article
                        key={barber.id}
                        className="min-w-0 rounded-xl border bg-card p-5"
                    >
                        <div className="flex items-start gap-4">
                            {barber.image_url ? (
                                <img
                                    src={barber.image_url}
                                    alt={barber.name}
                                    width={64}
                                    height={80}
                                    className="h-20 w-16 rounded-lg object-cover"
                                />
                            ) : (
                                <span className="flex size-14 shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <UserRound
                                        aria-hidden="true"
                                        className="size-6 text-primary"
                                    />
                                </span>
                            )}
                            <div className="min-w-0 flex-1">
                                <h2 className="text-lg font-semibold break-words">
                                    {barber.name}
                                </h2>
                                <p className="mt-1 text-sm break-all text-muted-foreground">
                                    {barber.email}
                                </p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Badge
                                        variant={
                                            barber.is_active
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        {barber.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </Badge>
                                    <Badge variant="outline">
                                        {labels[barber.availability]}
                                    </Badge>
                                    <Badge variant="outline">
                                        Prefix {barber.queue_prefix}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <p className="mt-4 text-sm text-muted-foreground">
                            {barber.accepts_queue
                                ? 'Penerimaan antrean diizinkan'
                                : 'Penerimaan antrean ditutup'}{' '}
                            · Kapasitas{' '}
                            {barber.daily_capacity ?? 'mengikuti usaha'}
                        </p>
                        {barber.specialization && (
                            <p className="mt-2 text-sm">
                                {barber.specialization}
                            </p>
                        )}
                        <div className="mt-5 flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                className="min-h-11"
                                onClick={() => setEditing(barber)}
                            >
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Button>
                            {barber.is_active && (
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() =>
                                        setArchive({
                                            name: barber.name,
                                            url: barber.archive_url,
                                        })
                                    }
                                >
                                    Nonaktifkan
                                </Button>
                            )}
                        </div>
                    </article>
                ))}
            </div>
            {barbers.data.length === 0 && (
                <p className="rounded-xl border p-8 text-center text-muted-foreground">
                    Belum ada barber yang sesuai. Tambahkan akun dan profil
                    untuk memulai.
                </p>
            )}
            <CatalogPagination page={barbers} />
            <Dialog
                open={editing !== undefined}
                onOpenChange={(open) => {
                    if (!open) setEditing(undefined);
                }}
            >
                <DialogContent
                    className="staff-theme max-h-[90dvh] overflow-y-auto sm:max-w-2xl"
                    closeLabel="Tutup formulir barber"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Ubah barber' : 'Tambah barber'}
                        </DialogTitle>
                        <DialogDescription>
                            Role akun selalu barber. Prefix menjadi tetap
                            setelah nomor antrean diterbitkan.
                        </DialogDescription>
                    </DialogHeader>
                    {editing !== undefined && (
                        <BarberEditor
                            key={editing?.id ?? 'new'}
                            barber={editing}
                            storeUrl={store_url}
                            close={() => setEditing(undefined)}
                        />
                    )}
                </DialogContent>
            </Dialog>
            <CatalogArchive target={archive} onClose={() => setArchive(null)} />
        </StaffPage>
    );
}
