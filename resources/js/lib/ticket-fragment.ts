type TicketFragment = { token: string | null; qr: string | null };
const fragments = new Map<string, TicketFragment>();
const recoveryTokens = new Map<string, string>();

/** Called before Inertia initializes, so secrets never enter its URL/history state. */
export function captureTicketFragment(): void {
    if (typeof window === 'undefined' || !window.location.hash) return;
    const match = /^\/antrean\/saya\/([0-9a-hjkmnp-tv-z]{26})\/?$/i.exec(
        window.location.pathname,
    );
    if (!match) return;
    const fragment = new URLSearchParams(window.location.hash.slice(1));
    const access = fragment.get('access');
    const qr = fragment.get('qr');
    fragments.set(match[1].toLowerCase(), {
        token: access && /^[a-f0-9]{64}$/i.test(access) ? access : null,
        qr: qr && /^[A-Za-z0-9_-]{1,64}$/.test(qr) ? qr : null,
    });
    window.history.replaceState(
        window.history.state,
        '',
        window.location.pathname + window.location.search,
    );
}

export function getTicketFragment(id: string): TicketFragment | undefined {
    return fragments.get(id.toLowerCase());
}

export function clearTicketFragment(id: string): void {
    fragments.delete(id.toLowerCase());
}

export function rememberRecoveryToken(id: string, token: string): void {
    recoveryTokens.set(id, token);
}

export function recoveryTokenFor(id: string): string | undefined {
    return recoveryTokens.get(id);
}
