import { CatalogEmptyState } from '@/components/public/catalog-empty-state';
import { GalleryGrid } from '@/components/public/gallery-grid';
import { PublicPageShell } from '@/components/public/public-page-shell';
import type { PublicCatalog } from '@/types/customer';

export default function Gallery({
    images,
    illustrative,
}: PublicCatalog & {
    images: { title: string; src: string; alt: string }[];
    illustrative: boolean;
}) {
    return (
        <PublicPageShell
            title="Inspirasi Gaya"
            eyebrow="Detail yang berbicara"
            description="Jelajahi inspirasi potongan dan sentuhan akhir. Pilih foto untuk melihat detailnya."
        >
            {illustrative && (
                <p className="mb-8 rounded-xl border border-border bg-card px-5 py-4 text-sm leading-6 text-muted-foreground">
                    Foto berikut adalah ilustrasi inspirasi gaya, bukan
                    dokumentasi hasil layanan pelanggan studio.
                </p>
            )}
            {images.length ? (
                <GalleryGrid images={images} illustrative={illustrative} />
            ) : (
                <CatalogEmptyState
                    title="Galeri segera tersedia"
                    description="Foto karya dan suasana studio akan ditambahkan setelah tersedia."
                />
            )}
        </PublicPageShell>
    );
}
