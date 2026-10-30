export class BroadcastAccessError extends Error {
    constructor(public status: number) {
        super('Otorisasi pembaruan belum tersedia.');
    }
}

export async function authorizeBroadcast(
    channel: string,
    socket: string,
    signal: AbortSignal,
): Promise<{ auth: string }> {
    if (!/^private-staff\.(admin|barber\.[1-9]\d*)$/.test(channel))
        throw new BroadcastAccessError(403);
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (cookie)
        headers['X-XSRF-TOKEN'] = decodeURIComponent(
            cookie.slice('XSRF-TOKEN='.length),
        );
    const response = await fetch('/broadcasting/auth', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        referrerPolicy: 'no-referrer',
        headers,
        body: JSON.stringify({ socket_id: socket, channel_name: channel }),
        signal,
    });
    if (!response.ok) throw new BroadcastAccessError(response.status);
    const data: unknown = await response.json();
    if (
        !data ||
        typeof data !== 'object' ||
        !('auth' in data) ||
        typeof data.auth !== 'string'
    )
        throw new BroadcastAccessError(502);
    return { auth: data.auth };
}
