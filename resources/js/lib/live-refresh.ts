type RefreshResult = 'updated' | 'retry' | 'cancelled' | 'stopped';

type Runtime = {
    available: () => boolean;
    blocked: () => boolean;
    refresh: () => Promise<RefreshResult>;
    paused: (value: boolean) => void;
    schedule: (
        callback: () => void,
        delay: number,
    ) => ReturnType<typeof setTimeout>;
    cancel: (timer: ReturnType<typeof setTimeout>) => void;
};

// Event payloads are invalidations. Only a fresh authorized HTTP response can
// replace application data; socket messages never become ticket state.
export function createLiveRefresh(runtime: Runtime) {
    let disposed = false;
    let connected = false;
    let running = false;
    let pending = false;
    let failures = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const clear = () => {
        if (timer !== undefined) runtime.cancel(timer);
        timer = undefined;
    };
    const plan = (delay: number) => {
        clear();
        if (!disposed && runtime.available())
            timer = runtime.schedule(() => void run(), delay);
    };
    const interval = () =>
        Math.min((connected ? 60_000 : 15_000) * 2 ** failures, 120_000);
    const run = async () => {
        timer = undefined;
        if (disposed || running || !runtime.available()) return;
        if (runtime.blocked()) {
            runtime.paused(true);
            plan(1_000);
            return;
        }
        runtime.paused(false);
        running = true;
        pending = false;
        let result: RefreshResult;
        try {
            result = await runtime.refresh();
        } catch {
            result = 'retry';
        }
        running = false;
        if (disposed) return;
        if (result === 'stopped') {
            disposed = true;
            clear();
            return;
        }
        if (result === 'updated') failures = 0;
        if (result === 'retry') failures++;
        plan(
            result === 'cancelled'
                ? 1_000
                : failures > 0
                  ? interval()
                  : pending
                    ? 250
                    : interval(),
        );
    };

    return {
        start: () => plan(interval()),
        invalidate: () => {
            pending = true;
            if (!running) plan(failures > 0 ? interval() : 250);
        },
        transport: (ready: boolean) => {
            connected = ready;
            pending = true;
            if (!running) plan(ready ? 250 : interval());
        },
        resume: () => {
            pending = true;
            if (!running) plan(0);
        },
        suspend: clear,
        dispose: () => {
            disposed = true;
            clear();
        },
    };
}
