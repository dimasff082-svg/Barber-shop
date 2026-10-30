import { useForm } from '@inertiajs/react';
import { ImageIcon, LoaderCircle, Pencil, Plus } from 'lucide-react';
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
import { index } from '@/routes/admin/gallery';

type GalleryRow = {
    id: number;
    title: string;
    alt: string;
    category: string | null;
    sort_order: number;
    is_active: boolean;
    image_url: string | null;
    update_url: string;
    archive_url: string;
};

function GalleryEditor({
    image,
    storeUrl,
    close,
}: {
    image: GalleryRow | null;
    storeUrl: string;
    close: () => void;
}) {
    const form = useForm({
        title: image?.title ?? '',
        alt: image?.alt ?? '',
        category: image?.category ?? '',
        sort_order: image?.sort_order?.toString() ?? '0',
        is_active: image?.is_active ?? false,
        image: null as File | null,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            ...(image ? { _method: 'put' } : {}),
        }));
        form.post(image?.update_url ?? storeUrl, {
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
                    id="gallery-title"
                    label="Judul foto"
                    error={form.errors.title}
                >
                    <Input
                        id="gallery-title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        required
                        maxLength={100}
                        className="min-h-11"
                        aria-describedby="gallery-title-error"
                    />
                </CatalogField>
                <CatalogField
                    id="gallery-alt"
                    label="Deskripsi foto untuk aksesibilitas"
                    error={form.errors.alt}
                    description="Jelaskan isi foto agar dapat dipahami tanpa melihat gambar."
                >
                    <Input
                        id="gallery-alt"
                        value={form.data.alt}
                        onChange={(event) =>
                            form.setData('alt', event.target.value)
                        }
                        required
                        maxLength={255}
                        className="min-h-11"
                        aria-describedby="gallery-alt-help gallery-alt-error"
                    />
                </CatalogField>
                <div className="grid gap-5 sm:grid-cols-2">
                    <CatalogField
                        id="gallery-category"
                        label="Kategori"
                        error={form.errors.category}
                    >
                        <Input
                            id="gallery-category"
                            value={form.data.category}
                            onChange={(event) =>
                                form.setData('category', event.target.value)
                            }
                            maxLength={100}
                            className="min-h-11"
                        />
                    </CatalogField>
                    <CatalogField
                        id="gallery-order"
                        label="Urutan tampil"
                        error={form.errors.sort_order}
                    >
                        <Input
                            id="gallery-order"
                            type="number"
                            min={0}
                            max={1000000}
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData('sort_order', event.target.value)
                            }
                            required
                            className="min-h-11"
                            aria-describedby="gallery-order-error"
                        />
                    </CatalogField>
                </div>
                <CatalogField
                    id="gallery-image"
                    label={image ? 'Ganti foto (opsional)' : 'Foto'}
                    error={form.errors.image}
                    description="JPG, PNG, WebP atau AVIF, maksimal 2 MB. Pastikan memiliki izin untuk menampilkan foto."
                >
                    <Input
                        id="gallery-image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/avif"
                        required={!image}
                        onChange={(event) =>
                            form.setData(
                                'image',
                                event.target.files?.[0] ?? null,
                            )
                        }
                        className="min-h-11"
                        aria-describedby="gallery-image-help gallery-image-error"
                    />
                </CatalogField>
                <CatalogToggle
                    id="gallery-active"
                    label="Tampilkan pada galeri publik"
                    checked={form.data.is_active}
                    onChange={(checked) => form.setData('is_active', checked)}
                    description="Foto baru disimpan sebagai draft sampai opsi ini diaktifkan."
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
                    Simpan foto
                </Button>
            </div>
        </form>
    );
}

export default function Gallery({
    images,
    filters,
    store_url,
}: {
    images: CatalogPage<GalleryRow>;
    filters: { search: string };
    store_url: string;
}) {
    const [editing, setEditing] = useState<GalleryRow | null | undefined>(
        undefined,
    );
    const [archive, setArchive] = useState<{
        name: string;
        url: string;
    } | null>(null);
    return (
        <StaffPage
            title="Galeri"
            description="Kelola foto karya, informasi aksesibilitas dan urutan publikasi."
        >
            <div className="flex flex-col justify-between gap-4 sm:flex-row">
                <CatalogSearch search={filters.search} url={index.url()} />
                <Button onClick={() => setEditing(null)} className="min-h-11">
                    <Plus className="size-4" aria-hidden="true" />
                    Tambah foto
                </Button>
            </div>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {images.data.map((image) => (
                    <article
                        key={image.id}
                        className="min-w-0 overflow-hidden rounded-xl border bg-card"
                    >
                        {image.image_url ? (
                            <img
                                src={image.image_url}
                                alt={image.alt}
                                width={600}
                                height={450}
                                loading="lazy"
                                className="aspect-[4/3] w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-40 items-center justify-center bg-muted">
                                <ImageIcon
                                    aria-hidden="true"
                                    className="size-10 text-primary"
                                />
                            </div>
                        )}
                        <div className="p-5">
                            <Badge
                                variant={
                                    image.is_active ? 'default' : 'outline'
                                }
                            >
                                {image.is_active
                                    ? 'Tayang'
                                    : 'Draft / disembunyikan'}
                            </Badge>
                            <h2 className="mt-3 text-lg font-semibold break-words">
                                {image.title}
                            </h2>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                {image.alt}
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Urutan {image.sort_order}
                                {image.category ? ` · ${image.category}` : ''}
                            </p>
                            <div className="mt-5 flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => setEditing(image)}
                                >
                                    <Pencil
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Ubah
                                </Button>
                                {image.is_active && (
                                    <Button
                                        variant="outline"
                                        className="min-h-11"
                                        onClick={() =>
                                            setArchive({
                                                name: image.title,
                                                url: image.archive_url,
                                            })
                                        }
                                    >
                                        Sembunyikan
                                    </Button>
                                )}
                            </div>
                        </div>
                    </article>
                ))}
            </div>
            {images.data.length === 0 && (
                <p className="rounded-xl border p-8 text-center text-muted-foreground">
                    Belum ada foto yang sesuai. Unggah foto usaha dengan izin
                    pemiliknya.
                </p>
            )}
            <CatalogPagination page={images} />
            <Dialog
                open={editing !== undefined}
                onOpenChange={(open) => {
                    if (!open) setEditing(undefined);
                }}
            >
                <DialogContent
                    className="staff-theme max-h-[90dvh] overflow-y-auto sm:max-w-xl"
                    closeLabel="Tutup formulir galeri"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Ubah foto galeri'
                                : 'Tambah foto galeri'}
                        </DialogTitle>
                        <DialogDescription>
                            Foto yang aktif akan ditampilkan pada halaman
                            publik.
                        </DialogDescription>
                    </DialogHeader>
                    {editing !== undefined && (
                        <GalleryEditor
                            key={editing?.id ?? 'new'}
                            image={editing}
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
