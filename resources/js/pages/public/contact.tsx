import { ArrowUpRight, Clock3, MapPin, MessageCircle } from 'lucide-react';
import { BusinessHours } from '@/components/public/business-hours';
import { ContentImage } from '@/components/public/content-image';
import { PublicPageShell } from '@/components/public/public-page-shell';
import { Button } from '@/components/ui/button';
import type { PublicCatalog } from '@/types/customer';

export default function Contact({ business, registration }: PublicCatalog) {
    return (
        <PublicPageShell
            title="Kunjungi Studio"
            eyebrow="Waktunya gaya baru"
            description="Temukan informasi lokasi, jam operasional dan kontak resmi. Periksa penerimaan antrean sebelum berangkat."
        >
            <div className="grid gap-6 lg:grid-cols-2">
                <div className="space-y-6">
                    <section className="rounded-2xl border border-border bg-card p-6 sm:p-8">
                        <MapPin
                            aria-hidden="true"
                            className="mb-5 size-6 text-primary"
                        />
                        <h2 className="text-xl font-semibold">
                            Lokasi {business.name}
                        </h2>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            {business.address ||
                                'Alamat resmi studio belum ditambahkan.'}
                        </p>
                        {business.map_url && (
                            <Button
                                asChild
                                variant="outline"
                                className="mt-5 min-h-11"
                            >
                                <a
                                    href={business.map_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    referrerPolicy="no-referrer"
                                >
                                    Buka Peta
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </a>
                            </Button>
                        )}
                    </section>
                    <section className="rounded-2xl border border-border bg-card p-6 sm:p-8">
                        <MessageCircle
                            aria-hidden="true"
                            className="mb-5 size-6 text-primary"
                        />
                        <h2 className="text-xl font-semibold">Hubungi Kami</h2>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            {business.whatsapp_url
                                ? 'Tanyakan informasi layanan dan kunjungan melalui WhatsApp resmi studio.'
                                : 'Nomor WhatsApp resmi belum ditambahkan.'}
                        </p>
                        {business.whatsapp_url && (
                            <Button asChild className="mt-5 min-h-11">
                                <a
                                    href={business.whatsapp_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    referrerPolicy="no-referrer"
                                >
                                    Hubungi via WhatsApp
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </a>
                            </Button>
                        )}
                    </section>
                </div>
                <section className="rounded-2xl border border-border bg-card p-6 sm:p-8">
                    <Clock3
                        aria-hidden="true"
                        className="mb-5 size-6 text-primary"
                    />
                    <h2 className="mb-6 text-xl font-semibold">
                        Jam Operasional
                    </h2>
                    <BusinessHours hours={business.hours} />
                    <p className="mt-6 text-sm leading-6 text-muted-foreground">
                        Zona waktu: {registration.timezone}.{' '}
                        {registration.message} Jadwal dapat berubah pada hari
                        libur.
                    </p>
                </section>
            </div>
            <figure className="mt-10">
                <div className="aspect-[4/3] w-full overflow-hidden rounded-2xl sm:aspect-[16/7]">
                    <ContentImage
                        src="/images/shop-interior.webp"
                        alt="Ilustrasi interior studio dengan kursi barber dan pencahayaan hangat"
                        width={1200}
                        height={800}
                    />
                </div>
                <figcaption className="mt-3 text-xs text-muted-foreground">
                    Foto ilustrasi konsep studio.
                </figcaption>
            </figure>
        </PublicPageShell>
    );
}
