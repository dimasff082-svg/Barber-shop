import { Link } from '@inertiajs/react';
import { ArrowUpRight, Clock3, Scissors } from 'lucide-react';
import { ContentImage } from '@/components/public/content-image';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/queue';
import type { PublicService } from '@/types/customer';

export function ServiceCard({ service }: { service: PublicService }) {
    return (
        <article className="flex h-full min-w-0 flex-col overflow-hidden rounded-xl border border-border bg-card">
            <div className="aspect-[4/3] overflow-hidden">
                {service.image ? (
                    <ContentImage
                        src={service.image}
                        alt={`Layanan ${service.name}`}
                        width={800}
                        height={600}
                    />
                ) : (
                    <div className="flex h-full flex-col items-center justify-center gap-3 bg-surface-raised text-muted-foreground">
                        <Scissors
                            aria-hidden="true"
                            className="size-8 text-primary"
                        />
                        <span className="text-sm">
                            Foto layanan belum tersedia
                        </span>
                    </div>
                )}
            </div>
            <div className="flex flex-1 flex-col p-6">
                <h2 className="font-display text-2xl uppercase">
                    {service.name}
                </h2>
                <p className="mt-3 flex-1 text-sm leading-6 text-muted-foreground">
                    {service.description ||
                        'Konsultasikan gaya pilihan Anda dengan barber sebelum layanan dimulai.'}
                </p>
                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                    <span className="text-xl font-semibold text-primary">
                        {new Intl.NumberFormat('id-ID', {
                            style: 'currency',
                            currency: 'IDR',
                            maximumFractionDigits: 0,
                        }).format(Number(service.price))}
                    </span>
                    <span className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Clock3 aria-hidden="true" className="size-4" />±{' '}
                        {service.duration_minutes} menit
                    </span>
                </div>
                <Button asChild className="mt-5 min-h-11 w-full">
                    <Link href={index({ query: { service: service.id } })}>
                        Pilih Layanan
                        <ArrowUpRight aria-hidden="true" className="size-4" />
                    </Link>
                </Button>
            </div>
        </article>
    );
}
