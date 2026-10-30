import { CirclePause, Radio, RefreshCw, WifiOff } from 'lucide-react';
import { useLiveUpdates } from '@/hooks/use-live-updates';

export function LiveUpdateStatus() {
    const live = useLiveUpdates('staff');
    if (!live.enabled) return null;
    const Icon = !live.online
        ? WifiOff
        : live.paused
          ? CirclePause
          : live.connected
            ? Radio
            : RefreshCw;
    const message = live.expired
        ? 'Akses perlu diperbarui. Muat ulang halaman.'
        : !live.online
          ? 'Offline · menampilkan data terakhir'
          : live.paused
            ? 'Pembaruan menunggu Anda selesai mengisi form'
            : live.failed
              ? 'Koneksi terganggu · pembaruan akan dicoba kembali'
              : live.connected
                ? 'Terhubung langsung'
                : 'Pembaruan berkala aktif';
    return (
        <p
            role="status"
            className="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs leading-5 text-muted-foreground"
        >
            <Icon aria-hidden="true" className="size-3.5 shrink-0" />
            <span>{message}</span>
            {live.lastUpdated && (
                <span>
                    · diperbarui{' '}
                    {live.lastUpdated.toLocaleTimeString('id-ID', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                    })}
                </span>
            )}
        </p>
    );
}
