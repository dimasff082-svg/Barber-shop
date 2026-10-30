import { BarberCard } from '@/components/public/barber-card';
import { CatalogEmptyState } from '@/components/public/catalog-empty-state';
import { PublicPageShell } from '@/components/public/public-page-shell';
import type { PublicCatalog } from '@/types/customer';
import { useLiveUpdates } from '@/hooks/use-live-updates';

export default function Barbers({ barbers, registration }: PublicCatalog) {
    useLiveUpdates('public');
    return (
        <PublicPageShell
            title="Kenali Barber Anda"
            eyebrow="Di balik setiap potongan"
            description="Pilih barber berdasarkan keahlian, jadwal dan ketersediaannya. Setiap barber memiliki antrean sendiri."
        >
            <p className="mb-8 rounded-xl border border-border bg-card px-5 py-4 text-sm leading-6 text-muted-foreground">
                {registration.message} Status dan ringkasan antrean diperbarui
                otomatis selama halaman aktif. Perubahan terakhir tetap
                diperiksa ketika Anda mengambil tiket.
            </p>
            {barbers.length ? (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {barbers.map((barber) => (
                        <BarberCard key={barber.id} barber={barber} />
                    ))}
                </div>
            ) : (
                <CatalogEmptyState
                    title="Profil barber belum tersedia"
                    description="Profil tim, spesialisasi dan jadwal resmi sedang disiapkan. Kami akan menampilkan informasi setelah tersedia."
                />
            )}
        </PublicPageShell>
    );
}
