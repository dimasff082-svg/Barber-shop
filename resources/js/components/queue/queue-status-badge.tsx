import {
    Ban,
    CheckCircle2,
    CircleCheck,
    Clock3,
    Megaphone,
    Scissors,
    TimerOff,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { QueueStatus } from '@/types/customer';

export const queueStatusPresentation = {
    waiting_checkin: {
        label: 'Menunggu Check-in',
        description:
            'Datang ke studio dan pindai QR lokasi untuk menyatakan kehadiran.',
        icon: Clock3,
        className: 'border-warning/30 bg-warning/10 text-warning',
    },
    checked_in: {
        label: 'Sudah Hadir',
        description:
            'Anda siap dipanggil. Tetap berada di sekitar studio dan pantau nomor antrean.',
        icon: CircleCheck,
        className: 'border-success/30 bg-success/10 text-success',
    },
    called: {
        label: 'Dipanggil',
        description: 'Giliran Anda dipanggil. Silakan temui barber di studio.',
        icon: Megaphone,
        className: 'border-info/30 bg-info/10 text-info',
    },
    in_service: {
        label: 'Sedang Dilayani',
        description:
            'Layanan Anda sedang berlangsung. Selamat menikmati waktu di studio.',
        icon: Scissors,
        className: 'border-info/30 bg-info/10 text-info',
    },
    completed: {
        label: 'Selesai',
        description:
            'Layanan telah selesai. Terima kasih sudah berkunjung ke BARBERFLOW.',
        icon: CheckCircle2,
        className: 'border-success/30 bg-success/10 text-success',
    },
    missed: {
        label: 'Terlewat',
        description:
            'Masa toleransi atau tanggal berlaku telah berakhir. Ambil antrean baru bila masih tersedia.',
        icon: TimerOff,
        className: 'border-danger/30 bg-danger/10 text-danger',
    },
    cancelled: {
        label: 'Dibatalkan',
        description:
            'Tiket ini telah dibatalkan dan tidak dapat digunakan untuk menerima layanan.',
        icon: Ban,
        className: 'border-border bg-surface-raised text-muted-foreground',
    },
} satisfies Record<
    QueueStatus,
    {
        label: string;
        description: string;
        icon: typeof Clock3;
        className: string;
    }
>;

export function QueueStatusBadge({
    status,
    className,
}: {
    status: QueueStatus;
    className?: string;
}) {
    const presentation = queueStatusPresentation[status];
    const Icon = presentation.icon;
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium',
                presentation.className,
                className,
            )}
        >
            <Icon className="size-4 shrink-0" aria-hidden="true" />
            {presentation.label}
        </span>
    );
}
