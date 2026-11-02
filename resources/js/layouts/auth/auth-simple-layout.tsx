import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const displayTitle = title || 'Masuk ke akun staff';
    const displayDescription =
        description ||
        'Gunakan email dan kata sandi akun admin atau barber Anda.';

    return (
        <div className="staff-theme relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-[#0a0908] p-4 text-foreground sm:p-6 md:p-10">
            {/* Ambient Background with Soft Gold Glow */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -top-36 left-1/2 -translate-x-1/2 h-[380px] w-[640px] rounded-full bg-gradient-to-b from-[#e5c07b]/25 via-[#d4af37]/15 to-transparent blur-3xl"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-32 -left-20 h-[320px] w-[420px] rounded-full bg-[#d4af37]/10 blur-[100px]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-32 -right-20 h-[320px] w-[420px] rounded-full bg-[#e5c07b]/10 blur-[100px]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(#e5c07b12_1px,transparent_1px)] [background-size:28px_28px]"
            />

            {/* Login Card Container - Visible Soft Gold Box */}
            <div className="relative z-10 w-full max-w-[440px]">
                {/* Soft Gold Outer Halo Glow */}
                <div
                    aria-hidden="true"
                    className="absolute -inset-1 rounded-[26px] bg-gradient-to-b from-[#e5c07b]/35 via-[#d4af37]/15 to-transparent blur-md opacity-80"
                />

                {/* Card Surface with Soft Gold Border */}
                <div className="relative rounded-2xl border-2 border-[#e5c07b]/40 bg-[#161410]/95 p-6 shadow-[0_15px_45px_-10px_rgba(0,0,0,0.85),0_0_35px_rgba(229,192,123,0.15)] backdrop-blur-2xl sm:p-8">
                    {/* Top Soft Gold Line Accent */}
                    <div
                        aria-hidden="true"
                        className="absolute inset-x-10 top-0 h-[2px] bg-gradient-to-r from-transparent via-[#f3e5ab] to-transparent opacity-90"
                    />

                    {/* Brand Header */}
                    <div className="flex flex-col items-center gap-2.5">
                        <Link
                            href={home()}
                            className="group flex flex-col items-center gap-2 transition-transform hover:scale-105"
                        >
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl border-2 border-[#e5c07b]/40 bg-gradient-to-br from-[#e5c07b]/25 via-[#d4af37]/15 to-[#1c1812] text-[#f3e5ab] shadow-inner shadow-[#e5c07b]/30 transition-all group-hover:border-[#f3e5ab] group-hover:shadow-[#e5c07b]/50">
                                <AppLogoIcon className="size-6 text-[#f3e5ab]" />
                            </div>
                            <span className="text-[11px] font-bold tracking-[0.28em] text-[#e5c07b] uppercase">
                                BARBERFLOW
                            </span>
                        </Link>

                        <div className="mt-1 space-y-1 text-center">
                            <h1 className="text-xl font-bold tracking-tight text-white sm:text-2xl">
                                {displayTitle}
                            </h1>
                            <p className="mx-auto max-w-xs text-xs text-[#c2baa6] sm:text-sm">
                                {displayDescription}
                            </p>
                        </div>
                    </div>

                    {/* Form Content */}
                    <div className="mt-5">{children}</div>

                    {/* Back to Home Link */}
                    <div className="mt-6 border-t border-[#e5c07b]/20 pt-4 text-center">
                        <Link
                            href={home()}
                            className="inline-flex items-center gap-1.5 text-xs text-[#c2baa6] transition-colors hover:text-[#f3e5ab]"
                        >
                            <ArrowLeft className="size-3.5" />
                            <span>Kembali ke Halaman Utama</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
