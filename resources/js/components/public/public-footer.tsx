import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { Brand } from '@/components/public/brand';
import { publicNavigation } from '@/components/public/public-navbar';
import { dashboard, login } from '@/routes';
import type { BusinessInfo } from '@/types/customer';

export function PublicFooter() {
    const { auth, business } = usePage<{ business?: BusinessInfo }>().props;
    return (
        <footer className="border-t border-border bg-background py-12">
            <div className="public-container">
                <div className="grid gap-10 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <Brand />
                        <p className="mt-4 max-w-xs text-sm leading-6 text-muted-foreground">
                            Ruang untuk gaya, detail, dan waktu yang lebih
                            berharga.
                        </p>
                    </div>
                    <div>
                        <p className="mb-3 text-sm font-semibold">Jelajahi</p>
                        <nav
                            aria-label="Navigasi footer"
                            className="flex flex-wrap gap-x-6 gap-y-1"
                        >
                            {publicNavigation.slice(1).map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="inline-flex min-h-11 items-center text-sm text-muted-foreground hover:text-primary"
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                    </div>
                    <div>
                        <p className="mb-3 text-sm font-semibold">
                            Kunjungi studio
                        </p>
                        <p className="text-sm leading-6 text-muted-foreground">
                            {business?.address ||
                                'Alamat studio belum ditambahkan.'}
                        </p>
                        <Link
                            href={publicNavigation[4].href}
                            className="mt-2 inline-flex min-h-11 items-center gap-2 text-sm text-primary"
                        >
                            Jam buka & kontak
                            <ArrowUpRight
                                aria-hidden="true"
                                className="size-4"
                            />
                        </Link>
                    </div>
                </div>
                <div className="mt-10 flex flex-col gap-3 border-t border-border pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        © {new Date().getFullYear()} BARBERFLOW. Semua hak
                        dilindungi.
                    </p>
                    {auth.user ? (
                        <Link
                            href={dashboard()}
                            className="inline-flex min-h-11 items-center gap-2 text-sm hover:text-primary"
                        >
                            Dashboard staff
                            <ArrowUpRight aria-hidden="true" className="size-4" />
                        </Link>
                    ) : (
                        <a
                            href="/login"
                            className="inline-flex min-h-11 items-center gap-2 text-sm hover:text-primary"
                        >
                            Masuk staff
                            <ArrowUpRight aria-hidden="true" className="size-4" />
                        </a>
                    )}
                </div>
            </div>
        </footer>
    );
}
