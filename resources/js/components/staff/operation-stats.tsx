import { StatCard } from '@/components/staff/stat-card';
import type { StaffStats } from '@/types/operations';

export function OperationStats({ stats }: { stats: StaffStats }) {
    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
            <StatCard label="Tiket terdaftar" value={stats.total} />
            <StatCard
                label="Menunggu"
                value={stats.waiting}
                description={`${stats.checked_in} sudah hadir · ${stats.waiting_checkin} belum check-in`}
            />
            <StatCard label="Selesai" value={stats.completed} />
            <StatCard
                label="Batal / terlewat"
                value={`${stats.cancelled} / ${stats.missed}`}
            />
        </div>
    );
}
