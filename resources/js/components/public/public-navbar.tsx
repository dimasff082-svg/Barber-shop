import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import { Brand } from '@/components/public/brand';
import { PrimaryCTA } from '@/components/public/primary-cta';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { home } from '@/routes';
import { services, barbers, gallery, contact } from '@/routes/public';

export const publicNavigation = [
    { label: 'Beranda', href: home.url() },
    { label: 'Layanan', href: services.url() },
    { label: 'Barber', href: barbers.url() },
    { label: 'Galeri', href: gallery.url() },
    { label: 'Kontak', href: contact.url() },
];

export function PublicNavbar() {
    const [menuOpen, setMenuOpen] = useState(false);
    const { url } = usePage();
    const currentPath = url.split('?')[0];
    return (
        <header className="relative z-20 border-b border-white/10 bg-background/80">
            <div className="public-container flex min-h-20 items-center justify-between gap-3 lg:min-h-24">
                <Brand />
                <nav
                    aria-label="Navigasi utama"
                    className="hidden items-center gap-7 lg:flex"
                >
                    {publicNavigation.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={
                                currentPath === item.href ? 'page' : undefined
                            }
                            className="inline-flex min-h-11 items-center text-sm text-muted-foreground transition-colors hover:text-primary aria-[current=page]:text-primary"
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
                <div className="hidden lg:block">
                    <PrimaryCTA />
                </div>
                <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                    <SheetTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-11 lg:hidden"
                            aria-label="Buka menu navigasi"
                        >
                            <Menu aria-hidden="true" className="size-6" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        lang="id"
                        closeLabel="Tutup menu"
                        className="public-theme w-[min(90vw,24rem)] overflow-y-auto p-6 [&>button]:top-4 [&>button]:right-4 [&>button]:flex [&>button]:size-11 [&>button]:items-center [&>button]:justify-center"
                    >
                        <SheetHeader className="px-0 pt-12 text-left">
                            <SheetTitle className="text-xl font-semibold">
                                BARBER<span className="text-primary">FLOW</span>
                            </SheetTitle>
                            <SheetDescription>
                                Gaya terbaik dimulai dari sini.
                            </SheetDescription>
                        </SheetHeader>
                        <nav
                            aria-label="Navigasi mobile"
                            className="mt-4 flex flex-col gap-2"
                        >
                            {publicNavigation.map((item) => (
                                <SheetClose asChild key={item.href}>
                                    <Link
                                        href={item.href}
                                        aria-current={
                                            currentPath === item.href
                                                ? 'page'
                                                : undefined
                                        }
                                        className="flex min-h-12 items-center rounded-lg px-3 text-base hover:bg-accent hover:text-primary aria-[current=page]:text-primary"
                                    >
                                        {item.label}
                                    </Link>
                                </SheetClose>
                            ))}
                        </nav>
                        <div className="mt-6">
                            <PrimaryCTA
                                className="w-full"
                                onClick={() => setMenuOpen(false)}
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </header>
    );
}
