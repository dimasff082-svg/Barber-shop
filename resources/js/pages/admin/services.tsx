import { useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, Scissors } from 'lucide-react';
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
import { index } from '@/routes/admin/services';

type ServiceRow = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    estimated_duration_minutes: number;
    is_active: boolean;
    image_url: string | null;
    update_url: string;
    archive_url: string;
};

function ServiceEditor({
    service,
    storeUrl,
    close,
}: {
    service: ServiceRow | null;
    storeUrl: string;
    close: () => void;
}) {
    const form = useForm({
        name: service?.name ?? '',
        description: service?.description ?? '',
        price: service?.price ?? '',
        estimated_duration_minutes:
            service?.estimated_duration_minutes?.toString() ?? '30',
        is_active: service?.is_active ?? false,
        image: null as File | null,
        remove_image: false,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            ...(service ? { _method: 'put' } : {}),
        }));
        form.post(service?.update_url ?? storeUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: close,
        });
    }
    return (
        <form
            onSubmit={submit}
            className="space-y-5"
            aria-busy={form.processing}
        >
            <fieldset disabled={form.processing} className="grid min-w-0 gap-5">
                <CatalogField
                    id="service-name"
                    label="Nama layanan"
                    error={form.errors.name}
                >
                    <Input
                        id="service-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        required
                        maxLength={100}
                        className="min-h-11"
                        aria-describedby="service-name-error"
                    />
                </CatalogField>
                <CatalogField
                    id="service-description"
                    label="Deskripsi"
                    error={form.errors.description}
                >
                    <textarea
                        id="service-description"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                        maxLength={2000}
                        rows={3}
                        className="min-h-24 w-full rounded-md border bg-background p-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                    />
                </CatalogField>
                <div className="grid gap-5 sm:grid-cols-2">
                    <CatalogField
                        id="service-price"
                        label="Harga (Rupiah)"
                        error={form.errors.price}
                    >
                        <Input
                            id="service-price"
                            type="number"
                            min={0}
                            max={9999999999.99}
                            step="0.01"
                            value={form.data.price}
                            onChange={(event) =>
                                form.setData('price', event.target.value)
                            }
                            required
                            className="min-h-11"
                            aria-describedby="service-price-error"
                        />
                    </CatalogField>
                    <CatalogField
                        id="service-duration"
                        label="Perkiraan durasi (menit)"
                        error={form.errors.estimated_duration_minutes}
                    >
                        <Input
                            id="service-duration"
                            type="number"
                            min={1}
                            max={32767}
                            value={form.data.estimated_duration_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'estimated_duration_minutes',
                                    event.target.value,
                                )
                            }
                            required
                            className="min-h-11"
                            aria-describedby="service-duration-error"
                        />
                    </CatalogField>
                </div>
                <CatalogField
                    id="service-image"
                    label="Foto layanan"
                    error={form.errors.image}
                    description="JPG, PNG, WebP atau AVIF, maksimal 2 MB."
                >
                    <Input
                        id="service-image"
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
                {service?.image_url && (
                    <CatalogToggle
                        id="service-remove-image"
                        label="Lepaskan foto layanan saat ini"
                        checked={form.data.remove_image}
                        onChange={(checked) =>
                            form.setData('remove_image', checked)
                        }
                    />
                )}
                <CatalogToggle
                    id="service-active"
                    label="Layanan aktif untuk pelanggan"
                    checked={form.data.is_active}
                    onChange={(checked) => form.setData('is_active', checked)}
                    description="Perubahan harga/durasi hanya berlaku untuk tiket baru. Snapshot tiket lama tidak diubah."
                />
            </fieldset>
            <CatalogErrorSummary errors={form.errors} />
            <div className="flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    className="min-h-11"
                    onClick={close}
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
                    Simpan layanan
                </Button>
            </div>
        </form>
    );
}

export default function Services({
    services,
    filters,
    store_url,
}: {
    services: CatalogPage<ServiceRow>;
    filters: { search: string };
    store_url: string;
}) {
    const [editing, setEditing] = useState<ServiceRow | null | undefined>(
        undefined,
    );
    const [archive, setArchive] = useState<{
        name: string;
        url: string;
    } | null>(null);
    const currency = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });
    return (
        <StaffPage
            title="Layanan"
            description="Kelola layanan, harga dan perkiraan durasi tanpa mengubah riwayat tiket."
        >
            <div className="flex flex-col justify-between gap-4 sm:flex-row">
                <CatalogSearch search={filters.search} url={index.url()} />
                <Button onClick={() => setEditing(null)} className="min-h-11">
                    <Plus className="size-4" aria-hidden="true" />
                    Tambah layanan
                </Button>
            </div>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {services.data.map((service) => (
                    <article
                        key={service.id}
                        className="min-w-0 overflow-hidden rounded-xl border bg-card"
                    >
                        {service.image_url ? (
                            <img
                                src={service.image_url}
                                alt={service.name}
                                width={600}
                                height={400}
                                className="aspect-[3/2] w-full object-cover"
                                loading="lazy"
                            />
                        ) : (
                            <div className="flex h-32 items-center justify-center bg-muted">
                                <Scissors
                                    aria-hidden="true"
                                    className="size-10 text-primary"
                                />
                            </div>
                        )}
                        <div className="p-5">
                            <Badge
                                variant={
                                    service.is_active ? 'default' : 'outline'
                                }
                            >
                                {service.is_active ? 'Aktif' : 'Nonaktif'}
                            </Badge>
                            <h2 className="mt-3 text-lg font-semibold break-words">
                                {service.name}
                            </h2>
                            <p className="mt-3 text-lg font-semibold text-primary">
                                {currency.format(Number(service.price))}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {service.estimated_duration_minutes} menit
                                perkiraan layanan
                            </p>
                            {service.description && (
                                <p className="mt-3 line-clamp-3 text-sm leading-6 text-muted-foreground">
                                    {service.description}
                                </p>
                            )}
                            <div className="mt-5 flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => setEditing(service)}
                                >
                                    <Pencil
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Ubah
                                </Button>
                                {service.is_active && (
                                    <Button
                                        variant="outline"
                                        className="min-h-11"
                                        onClick={() =>
                                            setArchive({
                                                name: service.name,
                                                url: service.archive_url,
                                            })
                                        }
                                    >
                                        Nonaktifkan
                                    </Button>
                                )}
                            </div>
                        </div>
                    </article>
                ))}
            </div>
            {services.data.length === 0 && (
                <p className="rounded-xl border p-8 text-center text-muted-foreground">
                    Belum ada layanan yang sesuai. Tambahkan layanan untuk
                    ditampilkan kepada pelanggan.
                </p>
            )}
            <CatalogPagination page={services} />
            <Dialog
                open={editing !== undefined}
                onOpenChange={(open) => {
                    if (!open) setEditing(undefined);
                }}
            >
                <DialogContent
                    className="staff-theme max-h-[90dvh] overflow-y-auto sm:max-w-xl"
                    closeLabel="Tutup formulir layanan"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Ubah layanan' : 'Tambah layanan'}
                        </DialogTitle>
                        <DialogDescription>
                            Gunakan harga dan durasi resmi usaha. Pelanggan
                            melihat layanan yang aktif.
                        </DialogDescription>
                    </DialogHeader>
                    {editing !== undefined && (
                        <ServiceEditor
                            key={editing?.id ?? 'new'}
                            service={editing}
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
