import { Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    CalendarDays,
    ClipboardList,
    History,
    Home,
    Images,
    LayoutGrid,
    QrCode,
    Scissors,
    Settings2,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { audit } from '@/routes/admin';
import { index as adminBarbers } from '@/routes/admin/barbers';
import { index as adminGallery } from '@/routes/admin/gallery';
import { index as adminQueues } from '@/routes/admin/queues';
import { index as adminQr } from '@/routes/admin/qr';
import { index as adminReports } from '@/routes/admin/reports';
import { index as adminSchedules } from '@/routes/admin/schedules';
import { index as adminServices } from '@/routes/admin/services';
import { edit as adminSettings } from '@/routes/admin/settings';
import { history, profile } from '@/routes/barber';
import { index as barberQueues } from '@/routes/barber/queues';

export function AppSidebar() {
    const page = usePage();
    const { auth } = page.props;
    const { setOpenMobile } = useSidebar();
    useEffect(
        () => router.on('navigate', () => setOpenMobile(false)),
        [setOpenMobile],
    );
    const roleLabel = auth.user.role === 'admin' ? 'admin' : 'barber';
    const navigation =
        auth.user.role === 'admin'
            ? [
                  {
                      title: 'Antrean',
                      url: adminQueues.url(),
                      icon: ClipboardList,
                  },
                  {
                      title: 'Barber & akun',
                      url: adminBarbers.url(),
                      icon: Users,
                  },
                  {
                      title: 'Layanan',
                      url: adminServices.url(),
                      icon: Scissors,
                  },
                  { title: 'Galeri', url: adminGallery.url(), icon: Images },
                  {
                      title: 'Jadwal & jam buka',
                      url: adminSchedules.url(),
                      icon: CalendarDays,
                  },
                  { title: 'QR lokasi', url: adminQr.url(), icon: QrCode },
                  { title: 'Laporan', url: adminReports.url(), icon: History },
                  {
                      title: 'Audit administrasi',
                      url: audit.url(),
                      icon: ShieldCheck,
                  },
                  {
                      title: 'Pengaturan usaha',
                      url: adminSettings.url(),
                      icon: Settings2,
                  },
              ]
            : [
                  {
                      title: 'Antrean saya',
                      url: barberQueues.url(),
                      icon: ClipboardList,
                  },
                  {
                      title: 'Riwayat layanan',
                      url: history.url(),
                      icon: History,
                  },
                  {
                      title: 'Profil barber',
                      url: profile.url(),
                      icon: UserRound,
                  },
              ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Area staff</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={page.component.endsWith('/dashboard')}
                                tooltip={{ children: `Dashboard ${roleLabel}` }}
                                className="min-h-11"
                            >
                                <Link href={dashboard()} prefetch>
                                    <LayoutGrid aria-hidden="true" />
                                    <span>Dashboard {roleLabel}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        {navigation.map((item) => (
                            <SidebarMenuItem key={item.url}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={
                                        page.url.split('?')[0] === item.url ||
                                        page.url.startsWith(item.url + '/')
                                    }
                                    tooltip={{ children: item.title }}
                                    className="min-h-11"
                                >
                                    <Link href={item.url}>
                                        <item.icon aria-hidden="true" />
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: 'Halaman publik' }}
                            className="min-h-11"
                        >
                            <Link href={home()} prefetch>
                                <Home aria-hidden="true" />
                                <span>Halaman publik</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
