import { Head, Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowRight,
    Check,
    Clock3,
    Home,
    MapPin,
    MessageCircle,
    QrCode,
    Scissors,
    Sparkles,
    Ticket,
} from 'lucide-react';
import { BarberCard } from '@/components/public/barber-card';
import { BusinessHours } from '@/components/public/business-hours';
import { CatalogEmptyState } from '@/components/public/catalog-empty-state';
import { ContentImage } from '@/components/public/content-image';
import { GalleryGrid } from '@/components/public/gallery-grid';
import { PrimaryCTA } from '@/components/public/primary-cta';
import { PublicNavbar } from '@/components/public/public-navbar';
import { SectionHeading } from '@/components/public/section-heading';
import { ServiceCard } from '@/components/public/service-card';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import {
    barbers as barbersPage,
    contact,
    gallery,
    services as servicesPage,
} from '@/routes/public';
import type { PublicCatalog } from '@/types/customer';
import { useLiveUpdates } from '@/hooks/use-live-updates';

const queueSteps = [
    {
        number: '01',
        title: 'Pilih gaya & barber',
        description: 'Pilih satu layanan dan barber yang menerima antrean.',
    },
    {
        number: '02',
        title: 'Simpan tiket Anda',
        description:
            'Isi data singkat dan simpan akses tiket digital dengan aman.',
    },
    {
        number: '03',
        title: 'Datang & check-in',
        description:
            'Pindai QR di studio untuk check-in dengan nomor yang sama.',
    },
];

type WelcomeProps = PublicCatalog & {
    images: { title: string; src: string; alt: string }[];
    illustrative: boolean;
};

export default function Welcome({
    services,
    barbers,
    business,
    registration,
    images,
    illustrative,
}: WelcomeProps) {
    useLiveUpdates('public');
    return (
        <PublicLayout>
            <Head title="BARBERFLOW — Tampil Rapi. Tanpa Antre Lama.">
                <meta
                    name="description"
                    content="Kenali layanan, barber dan inspirasi gaya BARBERFLOW. Ambil antrean online dan check-in melalui QR di studio."
                />
            </Head>
            <div className="relative isolate">
                <div className="absolute inset-x-0 top-0 -z-10 h-[840px] overflow-hidden lg:h-[800px]">
                    <ContentImage
                        src="/images/hero-barbershop.webp"
                        alt="Ilustrasi barber memotong rambut di studio dengan pencahayaan hangat"
                        width={1920}
                        height={1080}
                        loading="eager"
                        fetchPriority="high"
                        className="h-full w-full object-cover object-[67%_center] lg:object-center"
                    />
                    <div className="hero-shade absolute inset-0" />
                </div>
                <PublicNavbar />
                <main id="konten-utama" tabIndex={-1}>
                    <section
                        aria-labelledby="hero-heading"
                        className="public-container relative flex min-h-[760px] items-end pt-72 pb-14 lg:min-h-[704px] lg:items-center lg:py-20"
                    >
                        <div className="w-full max-w-xl">
                            <p className="mb-5 flex items-center gap-2 text-xs font-semibold tracking-[0.18em] text-primary uppercase">
                                <Sparkles
                                    aria-hidden="true"
                                    className="size-4 shrink-0"
                                />
                                Seni tampil percaya diri
                            </p>
                            <h1
                                id="hero-heading"
                                className="font-display text-[44px] leading-[1.08] uppercase sm:text-6xl lg:text-7xl"
                            >
                                Tampil rapi.
                                <br />
                                <span className="text-primary">
                                    Tanpa antre
                                </span>
                                <br />
                                lama.
                            </h1>
                            <p className="mt-6 max-w-md text-base leading-7 text-muted-foreground">
                                Gaya terbaik dimulai dari sini. Pilih layanan,
                                kenali barber Anda, dan nikmati waktu yang lebih
                                berharga.
                            </p>
                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                <PrimaryCTA />
                                <Button
                                    asChild
                                    variant="outline"
                                    size="lg"
                                    className="min-h-12 border-white/30 bg-background/30 px-6"
                                >
                                    <Link href={servicesPage()}>
                                        Lihat Layanan
                                        <ArrowRight
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                    </Link>
                                </Button>
                            </div>
                            <div className="mt-10 flex flex-wrap gap-x-6 gap-y-3 border-t border-white/15 pt-6 text-xs text-muted-foreground">
                                <span className="flex items-center gap-2">
                                    <Scissors
                                        aria-hidden="true"
                                        className="size-4 text-primary"
                                    />
                                    Gaya personal
                                </span>
                                <span className="flex items-center gap-2">
                                    <Ticket
                                        aria-hidden="true"
                                        className="size-4 text-primary"
                                    />
                                    Antrean online & di lokasi
                                </span>
                                <span className="flex items-center gap-2">
                                    <QrCode
                                        aria-hidden="true"
                                        className="size-4 text-primary"
                                    />
                                    Check-in via QR
                                </span>
                            </div>
                            <p className="mt-5 text-xs leading-5 text-muted-foreground">
                                Foto ilustrasi · {registration.message}
                            </p>
                        </div>
                        <a
                            href="#tentang"
                            className="absolute right-12 bottom-12 hidden min-h-11 items-center gap-3 text-xs tracking-widest text-muted-foreground uppercase lg:flex"
                        >
                            Kenali kami
                            <ArrowDown aria-hidden="true" className="size-4" />
                        </a>
                    </section>
                    <section
                        id="tentang"
                        aria-labelledby="about-heading"
                        className="public-section bg-background"
                    >
                        <div className="public-container grid items-center gap-10 lg:grid-cols-2 lg:gap-20">
                            <div className="relative">
                                <div className="aspect-[4/3] overflow-hidden rounded-2xl">
                                    <ContentImage
                                        src="/images/shop-interior.webp"
                                        alt="Ilustrasi interior studio dengan kursi kulit, cermin dan pencahayaan hangat"
                                        width={1440}
                                        height={960}
                                    />
                                </div>
                                <div className="absolute right-4 bottom-4 left-4 flex items-center gap-3 rounded-xl border border-white/10 bg-background/90 p-4 backdrop-blur-sm sm:right-6 sm:bottom-6 sm:left-auto sm:max-w-xs">
                                    <Scissors
                                        aria-hidden="true"
                                        className="size-6 shrink-0 text-primary"
                                    />
                                    <p className="text-sm">
                                        Detail kecil.
                                        <br />
                                        <span className="text-muted-foreground">
                                            Perbedaan yang terasa.
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <SectionHeading
                                    id="about-heading"
                                    eyebrow="Tentang Barberflow"
                                    title={
                                        <>
                                            Lebih dari sekadar
                                            <br />
                                            <span className="text-primary">
                                                potong rambut.
                                            </span>
                                        </>
                                    }
                                    description="Kami percaya penampilan yang baik dimulai dari perhatian pada detail. BARBERFLOW menghadirkan ruang untuk menemukan gaya yang paling terasa seperti diri Anda."
                                />
                                <ul className="mt-7 space-y-4 text-sm">
                                    {[
                                        'Pendekatan personal untuk setiap gaya',
                                        'Ruang nyaman untuk mengambil jeda',
                                        'Alur antrean yang sederhana dan jelas',
                                    ].map((text) => (
                                        <li
                                            key={text}
                                            className="flex items-start gap-3"
                                        >
                                            <Check
                                                aria-hidden="true"
                                                className="mt-0.5 size-4 shrink-0 text-primary"
                                            />
                                            <span>{text}</span>
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-6 text-xs text-muted-foreground">
                                    Foto interior sebagai ilustrasi konsep
                                    studio.
                                </p>
                            </div>
                        </div>
                    </section>
                    <section
                        aria-labelledby="services-heading"
                        className="public-section border-y border-border bg-card/40"
                    >
                        <div className="public-container">
                            <div className="mb-10 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                                <SectionHeading
                                    id="services-heading"
                                    eyebrow="Layanan pilihan"
                                    title={
                                        <>
                                            Gaya Anda.
                                            <br />
                                            <span className="text-primary">
                                                Sentuhan terbaik kami.
                                            </span>
                                        </>
                                    }
                                />
                                <Link
                                    href={servicesPage()}
                                    className="inline-flex min-h-11 items-center gap-2 text-sm text-primary"
                                >
                                    Semua layanan
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </Link>
                            </div>
                            {services.length ? (
                                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                    {services.slice(0, 3).map((service) => (
                                        <ServiceCard
                                            key={service.id}
                                            service={service}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <CatalogEmptyState
                                    title="Layanan sedang disiapkan"
                                    description="Daftar layanan, harga dan durasi resmi sedang disiapkan. Silakan kunjungi kembali."
                                />
                            )}
                        </div>
                    </section>
                    <section
                        aria-labelledby="barbers-heading"
                        className="public-section bg-background"
                    >
                        <div className="public-container">
                            <div className="mb-10 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                                <SectionHeading
                                    id="barbers-heading"
                                    eyebrow="Di balik setiap potongan"
                                    title={
                                        <>
                                            Temukan barber.
                                            <br />
                                            <span className="text-primary">
                                                Temukan gaya Anda.
                                            </span>
                                        </>
                                    }
                                    description="Kenali keahlian dan karakter barber sebelum memilih. Profil dan jadwal menggunakan informasi resmi studio."
                                />
                                <Link
                                    href={barbersPage()}
                                    className="inline-flex min-h-11 items-center gap-2 text-sm text-primary"
                                >
                                    Kenali tim kami
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </Link>
                            </div>
                            {barbers.length ? (
                                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {barbers.slice(0, 3).map((barber) => (
                                        <BarberCard
                                            key={barber.id}
                                            barber={barber}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <CatalogEmptyState
                                    title="Profil barber segera tersedia"
                                    description="Profil tim dan jadwal sedang disiapkan. Kami menampilkan nama dan foto setelah informasi barber tersedia."
                                />
                            )}
                        </div>
                    </section>
                    <section
                        aria-labelledby="gallery-heading"
                        className="public-section border-y border-border bg-card/40"
                    >
                        <div className="public-container">
                            <div className="mb-10 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                                <SectionHeading
                                    id="gallery-heading"
                                    eyebrow="Inspirasi gaya"
                                    title={
                                        <>
                                            Karakter Anda.
                                            <br />
                                            <span className="text-primary">
                                                Detail yang berbeda.
                                            </span>
                                        </>
                                    }
                                    description={
                                        illustrative
                                            ? 'Foto ilustrasi untuk inspirasi gaya; bukan dokumentasi hasil layanan pelanggan studio.'
                                            : 'Jelajahi detail potongan dan suasana studio.'
                                    }
                                />
                                <Link
                                    href={gallery()}
                                    className="inline-flex min-h-11 items-center gap-2 text-sm text-primary"
                                >
                                    Lihat galeri
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </Link>
                            </div>
                            {images.length ? (
                                <GalleryGrid
                                    images={images.slice(0, 3)}
                                    illustrative={illustrative}
                                />
                            ) : (
                                <CatalogEmptyState
                                    title="Galeri segera tersedia"
                                    description="Foto dan inspirasi gaya akan ditampilkan setelah tersedia."
                                />
                            )}
                        </div>
                    </section>
                    <section
                        id="cara-antre"
                        aria-labelledby="how-heading"
                        className="public-section bg-background"
                    >
                        <div className="public-container">
                            <SectionHeading
                                id="how-heading"
                                eyebrow="Satu antrean, dua cara"
                                title={
                                    <>
                                        Lebih praktis.
                                        <br />
                                        <span className="text-primary">
                                            Tetap dalam urutan.
                                        </span>
                                    </>
                                }
                            />
                            <div className="mt-10 grid gap-6 md:grid-cols-2">
                                {[
                                    {
                                        Icon: Home,
                                        eyebrow: '01 / Dari rumah',
                                        title: 'Ambil antrean online',
                                        text: 'Pilih layanan dan barber, lalu simpan tiket. Setiba di studio, check-in melalui QR tanpa mengambil nomor baru.',
                                    },
                                    {
                                        Icon: QrCode,
                                        eyebrow: '02 / Di studio',
                                        title: 'Pindai QR di lokasi',
                                        text: 'Belum punya tiket? Pilih Ambil Antrean Baru. Sudah mendaftar online? Pilih Check-in Antrean Saya dengan akses tiket yang Anda miliki.',
                                    },
                                ].map(({ Icon, eyebrow, title, text }) => (
                                    <article
                                        key={title}
                                        className="rounded-2xl border border-border bg-card p-6 sm:p-8"
                                    >
                                        <Icon
                                            aria-hidden="true"
                                            className="mb-6 size-7 text-primary"
                                        />
                                        <p className="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">
                                            {eyebrow}
                                        </p>
                                        <h3 className="font-display text-3xl uppercase">
                                            {title}
                                        </h3>
                                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                            {text}
                                        </p>
                                    </article>
                                ))}
                            </div>
                            <ol className="mt-10 grid gap-8 sm:grid-cols-3">
                                {queueSteps.map((step) => (
                                    <li
                                        key={step.number}
                                        className="border-t border-border pt-6"
                                    >
                                        <span className="font-display text-3xl text-primary">
                                            {step.number}
                                        </span>
                                        <h3 className="mt-4 text-base font-semibold">
                                            {step.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                            {step.description}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                            <p className="mt-8 flex items-start gap-3 text-sm leading-6 text-muted-foreground">
                                <Clock3
                                    aria-hidden="true"
                                    className="mt-0.5 size-5 shrink-0 text-primary"
                                />
                                Waktu tunggu dapat berubah. Antrean tidak
                                menggunakan reservasi slot jam.
                            </p>
                        </div>
                    </section>
                    <section
                        aria-labelledby="queue-heading"
                        className="public-section border-y border-border bg-card/40"
                    >
                        <div className="public-container grid items-center gap-10 lg:grid-cols-2 lg:gap-20">
                            <SectionHeading
                                id="queue-heading"
                                eyebrow="Ringkasan antrean"
                                title={
                                    <>
                                        Waktu Anda
                                        <br />
                                        <span className="text-primary">
                                            berharga.
                                        </span>
                                    </>
                                }
                                description="Lihat ringkasan antrean per barber tanpa data pribadi pelanggan. Informasi diperbarui otomatis selama halaman aktif."
                            />
                            <div className="rounded-2xl border border-border bg-card p-6 sm:p-8">
                                <Ticket
                                    aria-hidden="true"
                                    className="mb-5 size-8 text-primary"
                                />
                                <h3 className="text-xl font-semibold">
                                    {registration.open
                                        ? 'Pendaftaran antrean dibuka'
                                        : 'Pendaftaran belum dibuka'}
                                </h3>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                    {registration.message}
                                </p>
                                {barbers.length > 0 && (
                                    <dl className="mt-6 space-y-3">
                                        {barbers.map((barber) => (
                                            <div
                                                key={barber.id}
                                                className="flex flex-wrap justify-between gap-2 border-t border-border pt-3 text-sm"
                                            >
                                                <dt>{barber.name}</dt>
                                                <dd className="text-muted-foreground">
                                                    {barber.waiting_count}{' '}
                                                    menunggu
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                                <PrimaryCTA className="mt-6 w-full" />
                            </div>
                        </div>
                    </section>
                    <section
                        aria-labelledby="contact-heading"
                        className="public-section bg-background"
                    >
                        <div className="public-container">
                            <SectionHeading
                                id="contact-heading"
                                eyebrow="Sampai jumpa di studio"
                                title={
                                    <>
                                        Gaya baru.
                                        <br />
                                        <span className="text-primary">
                                            Suasana yang tepat.
                                        </span>
                                    </>
                                }
                                description="Periksa informasi kunjungan dan jam operasional sebelum datang."
                            />
                            <div className="mt-10 grid gap-8 md:grid-cols-3">
                                <section className="border-t border-border pt-6">
                                    <MapPin
                                        aria-hidden="true"
                                        className="mb-4 size-6 text-primary"
                                    />
                                    <h3 className="mb-3 font-semibold">
                                        Lokasi studio
                                    </h3>
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        {business.address ||
                                            'Alamat resmi studio belum ditambahkan.'}
                                    </p>
                                    {business.map_url && (
                                        <a
                                            href={business.map_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            referrerPolicy="no-referrer"
                                            className="mt-3 inline-flex min-h-11 items-center gap-2 text-sm text-primary"
                                        >
                                            Buka peta
                                            <ArrowRight
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                        </a>
                                    )}
                                </section>
                                <section className="border-t border-border pt-6">
                                    <Clock3
                                        aria-hidden="true"
                                        className="mb-4 size-6 text-primary"
                                    />
                                    <h3 className="mb-4 font-semibold">
                                        Jam operasional
                                    </h3>
                                    <BusinessHours hours={business.hours} />
                                </section>
                                <section className="border-t border-border pt-6">
                                    <MessageCircle
                                        aria-hidden="true"
                                        className="mb-4 size-6 text-primary"
                                    />
                                    <h3 className="mb-3 font-semibold">
                                        Hubungi kami
                                    </h3>
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        {business.whatsapp_url
                                            ? 'Hubungi WhatsApp resmi studio untuk informasi layanan dan kunjungan.'
                                            : 'Nomor WhatsApp resmi belum ditambahkan.'}
                                    </p>
                                    <Link
                                        href={contact()}
                                        className="mt-3 inline-flex min-h-11 items-center gap-2 text-sm text-primary"
                                    >
                                        Informasi kunjungan
                                        <ArrowRight
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                    </Link>
                                </section>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </PublicLayout>
    );
}
