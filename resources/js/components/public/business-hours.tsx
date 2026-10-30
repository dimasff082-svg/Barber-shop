import type { BusinessInfo } from '@/types/customer';

export const weekdays = [
    'Minggu',
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

export function BusinessHours({ hours }: { hours: BusinessInfo['hours'] }) {
    if (!hours.length)
        return (
            <p className="text-sm leading-6 text-muted-foreground">
                Jam operasional belum ditambahkan.
            </p>
        );
    return (
        <dl className="space-y-3 text-sm">
            {weekdays.map((day, weekday) => {
                const hour = hours.find((row) => row.weekday === weekday);
                return (
                    <div
                        key={day}
                        className="flex flex-wrap items-baseline justify-between gap-3 border-b border-border pb-3 last:border-0"
                    >
                        <dt className="text-muted-foreground">{day}</dt>
                        <dd className="tabular-nums">
                            {!hour
                                ? 'Belum diatur'
                                : hour.is_closed
                                  ? 'Tutup'
                                  : `${hour.starts_at?.slice(0, 5) ?? '—'}–${hour.ends_at?.slice(0, 5) ?? '—'}`}
                        </dd>
                    </div>
                );
            })}
        </dl>
    );
}
