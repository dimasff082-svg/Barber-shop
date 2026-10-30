import { Clock3, Scissors, UserRound } from 'lucide-react';
import type { PublicBarber, PublicService } from '@/types/customer';

export const formatQueuePrice = (price: string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(price));

export function QueueSummary({
    service,
    barber,
    name,
    phone,
    onsite,
    date,
}: {
    service: PublicService;
    barber: PublicBarber;
    name: string;
    phone: string;
    onsite: boolean;
    date: string;
}) {
    return (
        <div className="overflow-hidden rounded-2xl border border-border bg-card">
            <div className="border-b border-border p-5 sm:p-6">
                <p className="text-xs font-semibold tracking-widest text-primary uppercase">
                    Ringkasan antrean
                </p>
                <h3 className="mt-3 text-xl font-semibold">{name}</h3>
                <p className="mt-1 text-sm break-all text-muted-foreground">
                    {phone}
                </p>
            </div>
            <dl className="grid gap-5 p-5 text-sm sm:p-6">
                <div>
                    <dt className="flex items-center gap-2 text-muted-foreground">
                        <Scissors className="size-4" aria-hidden="true" />
                        Layanan
                    </dt>
                    <dd className="mt-1.5 font-medium">{service.name}</dd>
                </div>
                <div>
                    <dt className="flex items-center gap-2 text-muted-foreground">
                        <UserRound className="size-4" aria-hidden="true" />
                        Barber
                    </dt>
                    <dd className="mt-1.5 font-medium">{barber.name}</dd>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <dt className="text-muted-foreground">Harga layanan</dt>
                        <dd className="mt-1.5 font-semibold text-primary">
                            {formatQueuePrice(service.price)}
                        </dd>
                    </div>
                    <div>
                        <dt className="flex items-center gap-2 text-muted-foreground">
                            <Clock3 className="size-4" aria-hidden="true" />
                            Perkiraan durasi
                        </dt>
                        <dd className="mt-1.5">
                            {service.duration_minutes} menit
                        </dd>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <dt className="text-muted-foreground">Sumber</dt>
                        <dd className="mt-1.5">
                            {onsite ? 'QR di lokasi' : 'Online · dari rumah'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Tanggal berlaku
                        </dt>
                        <dd className="mt-1.5">
                            {new Intl.DateTimeFormat('id-ID', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                                timeZone: 'UTC',
                            }).format(new Date(`${date}T00:00:00Z`))}
                        </dd>
                    </div>
                </div>
                <div className="border-t border-border pt-4">
                    <dt className="text-muted-foreground">
                        Antrean menunggu saat ini
                    </dt>
                    <dd className="mt-1.5">
                        {barber.waiting_count} antrean pada barber ini
                    </dd>
                    <dd className="mt-2 text-xs leading-5 text-muted-foreground">
                        Jumlah dapat berubah sebelum tiket diterbitkan. Posisi
                        dan estimasi resmi ditampilkan pada tiket, bukan
                        reservasi jam layanan.
                    </dd>
                </div>
            </dl>
        </div>
    );
}
