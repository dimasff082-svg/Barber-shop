import { router, usePage } from '@inertiajs/react';
import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';
import { useEffect, useState } from 'react';
import { authorizeBroadcast, BroadcastAccessError } from '@/lib/broadcast-auth';
import { createLiveRefresh } from '@/lib/live-refresh';
import type { LiveUpdateState } from '@/types/realtime';

const events = [
    '.QueueCreated',
    '.QueueCheckedIn',
    '.QueueCalled',
    '.QueueStarted',
    '.QueueCompleted',
    '.QueueCancelled',
    '.QueueMissed',
    '.BarberAvailabilityChanged',
    '.QueueSummaryChanged',
];
const staffProps: Record<string, string[]> = {
    'admin/dashboard': [
        'date',
        'stats',
        'processing',
        'barbers',
        'active_barbers',
    ],
    'barber/dashboard': [
        'date',
        'stats',
        'processing',
        'barber',
        'next',
        'can_call',
        'profile_missing',
    ],
    'staff/queues': ['tickets', 'stats', 'barbers'],
    'staff/queue-detail': ['ticket', 'events'],
    'admin/reports': [
        'totals',
        'days',
        'barbers',
        'services',
        'wait_definition',
    ],
};
const publicPages = [
    'welcome',
    'public/barbers',
    'queue/index',
    'queue/location',
];

export function useLiveUpdates(mode: 'staff' | 'public') {
    const page = usePage();
    const config = page.props.realtime;
    const userId = page.props.auth?.user?.id;
    const properties =
        mode === 'staff'
            ? staffProps[page.component]
            : publicPages.includes(page.component)
              ? ['barbers', 'registration']
              : undefined;
    const propertyKey = properties?.join(',') ?? '';
    const enabled = Boolean(propertyKey);
    const [state, setState] = useState<LiveUpdateState>({
        connected: false,
        online: typeof navigator === 'undefined' || navigator.onLine,
        paused: false,
        failed: false,
        expired: false,
        lastUpdated: null,
    });

    useEffect(() => {
        if (!propertyKey) return;
        let disposed = false;
        let foreground = 0;
        let cancelRefresh: (() => void) | undefined;
        let echo: Echo<'reverb'> | undefined;
        let pusher: Pusher | undefined;
        const authRequests = new Set<AbortController>();
        const update = (next: Partial<LiveUpdateState>) => {
            if (!disposed) setState((previous) => ({ ...previous, ...next }));
        };
        const active = () => navigator.onLine && !document.hidden;
        const blocked = () =>
            foreground > 0 ||
            Boolean(
                document.querySelector(
                    '[data-realtime-pause="true"], [role="dialog"][data-state="open"]',
                ),
            ) ||
            Boolean(
                document.activeElement?.matches(
                    'input, select, textarea, [contenteditable="true"]',
                ),
            );
        update({
            connected: false,
            online: navigator.onLine,
            paused: false,
            failed: false,
            expired: false,
            lastUpdated: null,
        });
        const refresh = () =>
            new Promise<'updated' | 'retry' | 'cancelled' | 'stopped'>(
                (resolve) => {
                    let result: 'updated' | 'retry' | 'cancelled' | 'stopped' =
                        'retry';
                    router.reload({
                        only: propertyKey.split(','),
                        async: true,
                        showProgress: false,
                        preserveErrors: true,
                        headers: { 'X-Barberflow-Refresh': '1' },
                        onCancelToken: (token) => {
                            cancelRefresh = () => token.cancel();
                        },
                        onSuccess: () => {
                            result = 'updated';
                            update({ lastUpdated: new Date(), failed: false });
                        },
                        onCancel: () => {
                            result = 'cancelled';
                        },
                        onHttpException: (response) => {
                            if (
                                [401, 403, 404, 419].includes(response.status)
                            ) {
                                result = 'stopped';
                                update({ expired: true, connected: false });
                                echo?.disconnect();
                            } else update({ failed: true });
                            // Background updates must not display raw error/debug HTML.
                            return false;
                        },
                        onNetworkError: () => {
                            update({ failed: true });
                            return false;
                        },
                        onError: () => {
                            update({ failed: true });
                        },
                        onFinish: () => {
                            cancelRefresh = undefined;
                            resolve(result);
                        },
                    });
                },
            );
        const coordinator = createLiveRefresh({
            available: active,
            blocked,
            refresh,
            paused: (paused) => update({ paused }),
            schedule: (callback, delay) => setTimeout(callback, delay),
            cancel: (timer) => clearTimeout(timer),
        });
        const connectivity = () => {
            update({ online: navigator.onLine });
            cancelRefresh?.();
            if (active()) coordinator.resume();
            else coordinator.suspend();
        };
        const start = router.on('start', (event) => {
            if (event.detail.visit.headers['X-Barberflow-Refresh'] === '1')
                return;
            foreground++;
            cancelRefresh?.();
        });
        const finish = router.on('finish', (event) => {
            if (event.detail.visit.headers['X-Barberflow-Refresh'] === '1')
                return;
            foreground = Math.max(0, foreground - 1);
        });
        // Stop an in-flight response when editing or a confirmation begins,
        // including the gap between sending HTTP and receiving its response.
        const protectEditing = () => {
            if (blocked()) cancelRefresh?.();
        };
        document.addEventListener('focusin', protectEditing);
        document.addEventListener('input', protectEditing);
        document.addEventListener('change', protectEditing);
        const formObserver = new MutationObserver(protectEditing);
        formObserver.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['data-realtime-pause', 'data-state'],
        });
        window.addEventListener('online', connectivity);
        window.addEventListener('offline', connectivity);
        document.addEventListener('visibilitychange', connectivity);
        coordinator.start();

        async function connect() {
            if (!config?.enabled || (mode === 'staff' && !config.channel))
                return;
            try {
                const [{ default: EchoClient }, { default: PusherClient }] =
                    await Promise.all([
                        import('laravel-echo'),
                        import('pusher-js'),
                    ]);
                if (disposed) return;
                pusher = new PusherClient(config.key, {
                    cluster: 'mt1',
                    wsHost: config.host,
                    wsPort: config.port,
                    wssPort: config.port,
                    forceTLS: config.scheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                    disableStats: true,
                    channelAuthorization: {
                        customHandler: (parameters, callback) => {
                            const controller = new AbortController();
                            authRequests.add(controller);
                            void authorizeBroadcast(
                                parameters.channelName,
                                parameters.socketId,
                                controller.signal,
                            )
                                .then((data) => {
                                    if (!disposed) callback(null, data);
                                })
                                .catch((error: unknown) => {
                                    if (disposed) return;
                                    callback(
                                        new Error(
                                            'Pembaruan memakai koneksi berkala.',
                                        ),
                                        null,
                                    );
                                    const denied =
                                        error instanceof BroadcastAccessError &&
                                        [401, 403, 419].includes(error.status);
                                    update({ connected: false });
                                    coordinator.transport(false);
                                    if (denied) {
                                        echo?.disconnect();
                                        coordinator.resume();
                                    }
                                })
                                .finally(() => authRequests.delete(controller));
                        },
                    },
                });
                echo = new EchoClient({
                    broadcaster: 'reverb',
                    client: pusher,
                    withoutInterceptors: true,
                });
                const channels = [echo.channel('queue.summary')];
                if (mode === 'staff' && config.channel)
                    channels.push(echo.private(config.channel));
                const subscribed = new Set<string>();
                const disconnected = () => {
                    subscribed.clear();
                    update({ connected: false });
                    coordinator.transport(false);
                };
                pusher.connection.bind(
                    'state_change',
                    ({ current }: { current: string }) => {
                        if (current !== 'connected') disconnected();
                    },
                );
                channels.forEach((channel) => {
                    channel
                        .subscribed(() => {
                            subscribed.add(channel.name);
                            if (subscribed.size === channels.length) {
                                update({ connected: true });
                                coordinator.transport(true);
                            }
                        })
                        .error(disconnected);
                    events.forEach((event) =>
                        channel.listen(event, () => coordinator.invalidate()),
                    );
                });
            } catch {
                update({ connected: false });
                coordinator.transport(false);
                pusher?.disconnect();
            }
        }
        void connect();
        return () => {
            disposed = true;
            coordinator.dispose();
            cancelRefresh?.();
            authRequests.forEach((controller) => controller.abort());
            start();
            finish();
            formObserver.disconnect();
            document.removeEventListener('focusin', protectEditing);
            document.removeEventListener('input', protectEditing);
            document.removeEventListener('change', protectEditing);
            window.removeEventListener('online', connectivity);
            window.removeEventListener('offline', connectivity);
            document.removeEventListener('visibilitychange', connectivity);
            pusher?.connection.unbind_all();
            echo?.leaveAllChannels();
            echo?.disconnect();
        };
    }, [
        config?.enabled,
        config?.key,
        config?.host,
        config?.port,
        config?.scheme,
        config?.channel,
        mode,
        page.url,
        propertyKey,
        userId,
    ]);

    return { ...state, enabled };
}
