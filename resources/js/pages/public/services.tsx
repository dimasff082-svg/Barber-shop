import { CatalogEmptyState } from '@/components/public/catalog-empty-state';
import { PublicPageShell } from '@/components/public/public-page-shell';
import { ServiceCard } from '@/components/public/service-card';
import type { PublicCatalog } from '@/types/customer';

export default function Services({ services, registration }: PublicCatalog) {
    return (
        <PublicPageShell
            title="Layanan Kami"
            eyebrow="Perawatan dengan karakter"
            description="Temukan perawatan yang sesuai dengan gaya Anda. Harga dan perkiraan durasi tercantum pada setiap layanan."
        >
            <p className="mb-8 rounded-xl border border-border bg-card px-5 py-4 text-sm leading-6 text-muted-foreground">
                {registration.message} Durasi merupakan perkiraan, bukan
                reservasi jam layanan.
            </p>
            {services.length ? (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {services.map((service) => (
                        <ServiceCard key={service.id} service={service} />
                    ))}
                </div>
            ) : (
                <CatalogEmptyState
                    title="Layanan sedang disiapkan"
                    description="Daftar layanan, harga dan durasi akan tampil setelah informasi resmi tersedia. Silakan kunjungi kembali."
                />
            )}
        </PublicPageShell>
    );
}
