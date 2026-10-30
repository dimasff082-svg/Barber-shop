/** Read only this application's location QR; the scanned URL is never opened. */
export function parseLocationQr(value: string, origin: string): string | null {
    if (value.length > 2048) return null;
    for (let index = 0; index < value.length; index++) {
        const code = value.charCodeAt(index);
        if (code <= 32 || code === 127 || code === 92) return null;
    }
    // Validate the literal shape before URL normalization can remove dot paths,
    // empty fragments, or an empty user-info delimiter.
    if (
        !/^(?:https?:\/\/[^/?#@]+)?\/antrean\/lokasi\/[A-Za-z0-9_-]{1,64}$/.test(
            value,
        )
    )
        return null;
    try {
        const base = new URL(origin);
        const url = new URL(value, base);
        if (
            !['http:', 'https:'].includes(base.protocol) ||
            url.origin !== base.origin ||
            url.username ||
            url.password ||
            url.search ||
            url.hash
        )
            return null;
        return (
            /^\/antrean\/lokasi\/([A-Za-z0-9_-]{1,64})$/.exec(
                url.pathname,
            )?.[1] ?? null
        );
    } catch {
        return null;
    }
}

/** Cancels resources and invalidates late camera/import/decode completions. */
export function createQrScanSession() {
    let revision = 0;
    const resources = new Set<() => void>();
    const dispose = (cleanup: () => void) => {
        try {
            cleanup();
        } catch {
            // Disposing another resource must still continue if a device is gone.
        }
    };
    return {
        reset() {
            revision++;
            const current = [...resources];
            resources.clear();
            current.forEach(dispose);
        },
        scope() {
            const expected = revision;
            return {
                current: () => revision === expected,
                retain(cleanup: () => void) {
                    if (revision !== expected) dispose(cleanup);
                    else resources.add(cleanup);
                },
            };
        },
    };
}
