import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Check,
    Home,
    Settings2,
    ShieldCheck,
    Ticket,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

type DashboardFoundationProps = {
    role: 'admin' | 'barber';
};

export function DashboardFoundation({ role }: DashboardFoundationProps) {
    const { auth } = usePage().props;
    const accountRole =
        auth.user.role === 'admin'
            ? 'Admin'
            : auth.user.role === 'barber'
              ? 'Barber'
              : 'Staff';
    const isAdmin = role === 'admin';

    return (
        <div
            lang="id"
            className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 p-6 lg:p-8"
        >
            <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
                <div className="min-w-0">
                    <p className="mb-2 text-sm font-medium text-muted-foreground">
                        Area kerja BARBERFLOW
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Dashboard {isAdmin ? 'admin' : 'barber'}
                    </h1>
                    <p className="mt-3 max-w-xl text-sm leading-6 text-muted-foreground">
                        Selamat datang,{' '}
                        <span className="font-medium break-words text-foreground">
                            {auth.user.name}
                        </span>
                        .{' '}
                        {isAdmin
                            ? 'Ini adalah pusat pengelolaan studio Anda.'
                            : 'Ini adalah ruang kerja untuk layanan dan antrean Anda.'}
                    </p>
                </div>
                <Badge variant="outline" className="gap-2 px-3 py-2 text-sm">
                    <ShieldCheck aria-hidden="true" className="size-4" />
                    Akses {accountRole}
                </Badge>
            </header>

            <div className="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
                <Card>
                    <CardHeader>
                        <div className="mb-3 flex size-12 items-center justify-center rounded-xl border bg-muted">
                            <Ticket aria-hidden="true" className="size-6" />
                        </div>
                        <CardTitle>
                            <h2 className="text-xl leading-snug">
                                Sistem antrean sedang disiapkan
                            </h2>
                        </CardTitle>
                        <CardDescription className="leading-6">
                            {isAdmin
                                ? 'Ringkasan studio dan pengelolaan operasional akan muncul di sini saat layanan antrean tersedia.'
                                : 'Antrean milik Anda dan tindakan layanan akan muncul di sini saat layanan antrean tersedia.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <p className="rounded-lg border bg-muted/50 p-4 text-sm leading-6 text-muted-foreground">
                            Sementara layanan antrean disiapkan, Anda dapat
                            memperbarui profil atau mengunjungi halaman publik
                            studio melalui tautan di samping.
                        </p>
                    </CardContent>
                </Card>

                <div className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <h2 className="text-base">
                                    Akses yang tersedia
                                </h2>
                            </CardTitle>
                            <CardDescription>
                                Kelola akun Anda atau lihat halaman publik
                                studio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-12 justify-between gap-3"
                            >
                                <Link href={editProfile()}>
                                    <span className="flex items-center gap-3">
                                        <Settings2
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        Pengaturan profil
                                    </span>
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-12 justify-between gap-3"
                            >
                                <Link href={home()}>
                                    <span className="flex items-center gap-3">
                                        <Home
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        Halaman publik
                                    </span>
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                    <div className="flex items-start gap-3 rounded-xl border p-5">
                        <Check
                            aria-hidden="true"
                            className="mt-0.5 size-5 shrink-0"
                        />
                        <p className="text-sm leading-6 text-muted-foreground">
                            Anda masuk melalui akun staff. Pelanggan dapat
                            mengunjungi halaman publik tanpa membuat akun.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
