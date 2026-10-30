import { useCallback, useEffect, useState } from 'react';
import { QueueRequestError, queueRequest } from '@/lib/queue-request';
import type { CustomerTicket } from '@/types/customer';

export function useTicketPolling(url: string, enabled = true) {
    const [ticket, setTicket] = useState<CustomerTicket | null>(null);
    const [loading, setLoading] = useState(true);
    const [accessRequired, setAccessRequired] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [lastUpdated, setLastUpdated] = useState<Date | null>(null);
    const [online, setOnline] = useState(
        () => typeof navigator === 'undefined' || navigator.onLine,
    );
    const [revision, setRevision] = useState(0);
    const refresh = useCallback(() => setRevision((value) => value + 1), []);
    const update = useCallback((next: CustomerTicket) => {
        setTicket(next);
        setLastUpdated(new Date());
        setError(null);
        setAccessRequired(false);
    }, []);

    useEffect(() => {
        if (!enabled) return;
        let disposed = false;
        let busy = false;
        let terminal = false;
        let blocked = false;
        let failures = 0;
        let timer: ReturnType<typeof setTimeout> | undefined;
        let controller: AbortController | undefined;
        const clearTimer = () => {
            if (timer) clearTimeout(timer);
            timer = undefined;
        };
        const schedule = (delay: number) => {
            clearTimer();
            if (
                !disposed &&
                !terminal &&
                !blocked &&
                navigator.onLine &&
                !document.hidden
            )
                timer = setTimeout(() => void run(), delay);
        };
        const run = async () => {
            if (
                disposed ||
                busy ||
                blocked ||
                document.hidden ||
                !navigator.onLine
            ) {
                if (!navigator.onLine && !disposed) setLoading(false);
                return;
            }
            busy = true;
            controller = new AbortController();
            let delay = 12_000;
            try {
                const response = await queueRequest<{ ticket: CustomerTicket }>(
                    url,
                    { signal: controller.signal },
                );
                if (disposed) return;
                update(response.ticket);
                failures = 0;
                terminal = ['completed', 'cancelled', 'missed'].includes(
                    response.ticket.status,
                );
            } catch (failure) {
                if (
                    disposed ||
                    (failure instanceof DOMException &&
                        failure.name === 'AbortError')
                )
                    return;
                failures++;
                if (
                    failure instanceof QueueRequestError &&
                    [401, 403, 404].includes(failure.status)
                ) {
                    blocked = true;
                    setTicket(null);
                    setLastUpdated(null);
                    setAccessRequired(true);
                    setError(null);
                } else {
                    setError(
                        failure instanceof QueueRequestError
                            ? failure.message
                            : 'Koneksi terputus. Data terakhir tetap ditampilkan; pembaruan akan dicoba kembali.',
                    );
                    delay =
                        failure instanceof QueueRequestError &&
                        failure.status === 429
                            ? failure.retryAfter * 1000
                            : Math.min(12_000 * 2 ** failures, 120_000);
                }
            } finally {
                busy = false;
                if (!disposed) {
                    setLoading(false);
                    schedule(delay);
                }
            }
        };
        const handleConnection = () => {
            setOnline(navigator.onLine);
            clearTimer();
            if (navigator.onLine && !document.hidden && !terminal) void run();
        };
        const handleVisibility = () => {
            clearTimer();
            if (!document.hidden && navigator.onLine && !terminal) void run();
        };
        window.addEventListener('online', handleConnection);
        window.addEventListener('offline', handleConnection);
        document.addEventListener('visibilitychange', handleVisibility);
        void run();
        return () => {
            disposed = true;
            clearTimer();
            controller?.abort();
            window.removeEventListener('online', handleConnection);
            window.removeEventListener('offline', handleConnection);
            document.removeEventListener('visibilitychange', handleVisibility);
        };
    }, [url, enabled, revision, update]);

    return {
        ticket,
        loading,
        accessRequired,
        error,
        lastUpdated,
        online,
        refresh,
        update,
    };
}
