import { Breadcrumbs } from '@/components/breadcrumbs';
import { usePage } from '@inertiajs/react';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage();
    const segments = page.url.split('?')[0].split('/').filter(Boolean);
    const labels: Record<string, string> = {
        dashboard: 'Dashboard',
        barbers: 'Barber & akun',
        layanan: 'Layanan',
        galeri: 'Galeri',
        jadwal: 'Jadwal & jam buka',
        qr: 'QR lokasi',
        laporan: 'Laporan',
        pengaturan: 'Pengaturan usaha',
        audit: 'Audit administrasi',
        antrean: 'Antrean',
        riwayat: 'Riwayat layanan',
        profil: 'Profil barber',
        profile: 'Profil akun',
        security: 'Keamanan akun',
        appearance: 'Tampilan',
    };
    const fallback = [
        {
            title: page.props.auth.user.role === 'admin' ? 'Admin' : 'Barber',
            href: dashboard(),
        },
        {
            title:
                (segments.length > 2 ? 'Detail ' : '') +
                (labels[segments[1]] ?? 'Dashboard'),
            href: page.url,
        },
    ];
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger
                    aria-label="Buka atau tutup menu"
                    className="-ml-1 min-h-11 min-w-11"
                />
                <Breadcrumbs
                    breadcrumbs={breadcrumbs.length ? breadcrumbs : fallback}
                />
            </div>
        </header>
    );
}
