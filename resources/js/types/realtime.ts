export type RealtimeConfig = {
    enabled: boolean;
    key: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
    channel: string | null;
};

export type LiveUpdateState = {
    connected: boolean;
    online: boolean;
    paused: boolean;
    failed: boolean;
    expired: boolean;
    lastUpdated: Date | null;
};
