type QueueRequestOptions = {
    method?: 'GET' | 'POST' | 'PATCH';
    data?: Record<string, unknown>;
    body?: Record<string, unknown>;
    token?: string;
    signal?: AbortSignal;
};

export class QueueRequestError extends Error {
    constructor(
        public readonly status: number,
        public readonly errors: Record<string, string[]> = {},
        public readonly retryAfter = 30,
    ) {
        super(
            status === 419
                ? 'Sesi formulir berakhir. Muat ulang halaman lalu coba kembali.'
                : status === 429
                  ? 'Terlalu banyak permintaan. Tunggu sebentar lalu coba kembali.'
                  : status === 404 || status === 401 || status === 403
                    ? 'Akses tiket belum dapat diverifikasi.'
                    : 'Permintaan belum berhasil. Periksa data dan coba kembali.',
        );
        this.name = 'QueueRequestError';
    }
}

export async function queueRequest<T>(
    url: string,
    options: QueueRequestOptions = {},
): Promise<T> {
    const target = new URL(url, window.location.origin);
    if (target.origin !== window.location.origin) {
        throw new QueueRequestError(400);
    }
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));
    if (cookie)
        headers['X-XSRF-TOKEN'] = decodeURIComponent(
            cookie.slice('XSRF-TOKEN='.length),
        );
    if (options.token) headers.Authorization = `Bearer ${options.token}`;
    const data = options.data ?? options.body;
    if (data !== undefined) headers['Content-Type'] = 'application/json';
    const response = await fetch(target, {
        method: options.method ?? 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        referrerPolicy: 'no-referrer',
        headers,
        body: data === undefined ? undefined : JSON.stringify(data),
        signal: options.signal,
    });
    if (!response.ok) {
        const errors: Record<string, string[]> = {};
        if (response.status === 422) {
            const payload: unknown = await response.json().catch(() => null);
            if (
                payload &&
                typeof payload === 'object' &&
                'errors' in payload &&
                payload.errors &&
                typeof payload.errors === 'object'
            ) {
                for (const [key, messages] of Object.entries(payload.errors)) {
                    if (
                        Array.isArray(messages) &&
                        messages.every((message) => typeof message === 'string')
                    )
                        errors[key] = messages;
                }
            }
        }
        const retry = Number(response.headers.get('Retry-After'));
        throw new QueueRequestError(
            response.status,
            errors,
            Number.isFinite(retry) && retry > 0 ? Math.min(retry, 300) : 30,
        );
    }
    return response.json() as Promise<T>;
}

export function parseTicketRecovery(
    value: string,
): { id: string; token: string | null } | null {
    const text = value.trim();
    if (/^[0-9a-hjkmnp-tv-z]{26}$/i.test(text))
        return { id: text.toLowerCase(), token: null };
    try {
        const url = new URL(text, window.location.origin);
        const match = /^\/antrean\/saya\/([0-9a-hjkmnp-tv-z]{26})\/?$/i.exec(
            url.pathname,
        );
        if (url.origin !== window.location.origin || url.search || !match)
            return null;
        const token = new URLSearchParams(url.hash.slice(1)).get('access');
        if (!token || !/^[a-f0-9]{64}$/i.test(token)) return null;
        return { id: match[1].toLowerCase(), token };
    } catch {
        return null;
    }
}
