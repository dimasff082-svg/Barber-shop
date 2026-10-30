import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Copy,
    Download,
    KeyRound,
    LoaderCircle,
    LockKeyhole,
    QrCode,
    RefreshCw,
    ShieldCheck,
    WifiOff,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PublicNavbar } from '@/components/public/public-navbar';
import { ConfirmActionDialog } from '@/components/queue/confirm-action-dialog';
import { QueueTicketCard } from '@/components/queue/queue-ticket-card';
import { QrScannerDialog } from '@/components/queue/qr-scanner-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTicketPolling } from '@/hooks/use-ticket-polling';
import PublicLayout from '@/layouts/public-layout';
import {
    parseTicketRecovery,
    QueueRequestError,
    queueRequest,
} from '@/lib/queue-request';
import {
    clearTicketFragment,
    getTicketFragment,
    recoveryTokenFor,
    rememberRecoveryToken,
} from '@/lib/ticket-fragment';
import type { CustomerTicket, TicketPageProps } from '@/types/customer';

function requestMessage(failure: unknown): string {
    if (failure instanceof QueueRequestError)
        return Object.values(failure.errors).flat()[0] ?? failure.message;
    return 'Koneksi belum tersedia. Periksa jaringan lalu coba lagi.';
}

export default function TicketPage(props: TicketPageProps) {
    // Navigating between tickets must discard all previous private view state.
    return <TicketContent key={props.ticket_id} {...props} />;
}

function TicketContent({
    ticket_id: ticketId,
    urls,
    timezone,
    grace_minutes: graceMinutes,
}: TicketPageProps) {
    const fragment = useRef(getTicketFragment(ticketId));
    const [readyId, setReadyId] = useState<string | null>(null);
    const [accessRevision, setAccessRevision] = useState(0);
    const [recoveryToken, setRecoveryToken] = useState<string | undefined>(() =>
        recoveryTokenFor(ticketId),
    );
    const [accessValue, setAccessValue] = useState('');
    const [scannerOpen, setScannerOpen] = useState(false);
    const mutationPending = useRef(false);
    const [pending, setPending] = useState<
        'access' | 'checkin' | 'cancel' | null
    >(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [cancelOpen, setCancelOpen] = useState(false);
    const secretRequested = useRef(false);
    const polling = useTicketPolling(urls.status, readyId === ticketId);

    useEffect(() => {
        const recover = () => {
            const next = getTicketFragment(ticketId);
            if (!next?.token) return;
            fragment.current = next;
            setReadyId(null);
            setAccessRevision((value) => value + 1);
        };
        window.addEventListener('hashchange', recover);
        return () => window.removeEventListener('hashchange', recover);
    }, [ticketId]);

    useEffect(() => {
        let disposed = false;
        const controller = new AbortController();
        const authorize = async () => {
            const secret = fragment.current?.token;
            if (secret) {
                try {
                    await queueRequest(urls.access, {
                        method: 'POST',
                        token: secret,
                        signal: controller.signal,
                    });
                    rememberRecoveryToken(ticketId, secret);
                    clearTicketFragment(ticketId);
                    if (!disposed) setRecoveryToken(secret);
                } catch (failure) {
                    if (!disposed) setActionError(requestMessage(failure));
                }
            }
            if (!disposed) setReadyId(ticketId);
        };
        void authorize();
        return () => {
            disposed = true;
            controller.abort();
        };
    }, [ticketId, urls.access, accessRevision]);

    useEffect(() => {
        if (
            !polling.ticket ||
            polling.ticket.id !== ticketId ||
            recoveryToken ||
            secretRequested.current
        )
            return;
        secretRequested.current = true;
        // Only this memory cache receives the one-time handoff; it is never an Inertia prop.
        void queueRequest<{ access_token: string | null }>(urls.secret, {
            method: 'POST',
        })
            .then(async ({ access_token: token }) => {
                if (token) {
                    rememberRecoveryToken(ticketId, token);
                    setRecoveryToken(token);
                    await queueRequest(urls.secret_ack, { method: 'POST' });
                }
            })
            .catch(() => {
                // A failed handoff is retried on the next successful status update.
                secretRequested.current = false;
            });
    }, [polling.ticket, recoveryToken, ticketId, urls.secret, urls.secret_ack]);

    const exchangeAccess = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        let token = accessValue.trim();
        if (!/^[a-f0-9]{64}$/i.test(token)) {
            const parsed = parseTicketRecovery(token);
            if (!parsed || parsed.id !== ticketId || !parsed.token) {
                setActionError(
                    'Plak tautan pemulihan untuk tiket ini atau kode akses rahasia yang valid.',
                );
                return;
            }
            token = parsed.token;
        }
        setPending('access');
        setActionError(null);
        try {
            await queueRequest(urls.access, { method: 'POST', token });
            rememberRecoveryToken(ticketId, token);
            setRecoveryToken(token);
            setAccessValue('');
            polling.refresh();
        } catch (failure) {
            setActionError(requestMessage(failure));
        } finally {
            setPending(null);
        }
    };

    const mutate = async (action: 'checkin' | 'cancel', scannedQr?: string) => {
        if (mutationPending.current) {
            if (action === 'checkin')
                throw new Error('Tiket sedang diproses. Tunggu sebentar.');
            return;
        }
        mutationPending.current = true;
        setPending(action);
        setActionError(null);
        setNotice(null);
        try {
            const result = await queueRequest<{ ticket: CustomerTicket }>(
                action === 'checkin' ? urls.check_in : urls.cancel,
                {
                    method: 'POST',
                    data: action === 'checkin' ? { qr_code: scannedQr } : {},
                },
            );
            polling.update(result.ticket);
            polling.refresh();
            setCancelOpen(false);
            setScannerOpen(false);
            setNotice(
                action === 'checkin'
                    ? 'Check-in berhasil. Nomor antrean Anda tetap sama.'
                    : 'Antrean telah dibatalkan.',
            );
        } catch (failure) {
            setActionError(requestMessage(failure));
            polling.refresh();
            if (action === 'checkin') throw new Error(requestMessage(failure));
        } finally {
            mutationPending.current = false;
            setPending(null);
        }
    };

    const recoveryLink = () =>
        `${window.location.origin}/antrean/saya/${ticketId}#access=${recoveryToken}`;
    const copyRecovery = async () => {
        if (!recoveryToken) return;
        try {
            await navigator.clipboard.writeText(recoveryLink());
            setNotice(
                'Tautan pemulihan disalin. Simpan secara pribadi dan jangan bagikan.',
            );
        } catch {
            setActionError(
                'Tautan belum dapat disalin. Gunakan Unduh Akses untuk menyimpannya.',
            );
        }
    };
    const downloadRecovery = () => {
        if (!recoveryToken) return;
        const blob = new Blob(
            [
                `BARBERFLOW — akses tiket pribadi\nJangan bagikan tautan ini kepada orang lain.\n\n${recoveryLink()}\n`,
            ],
            { type: 'text/plain;charset=utf-8' },
        );
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `barberflow-akses-${ticketId}.txt`;
        anchor.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        setNotice('Akses tiket diunduh. Simpan file secara pribadi.');
    };
    const timeLabel = (date: Date) =>
        new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: timezone,
        }).format(date);
    const ticket = polling.ticket;
    const canCancel =
        ticket && ['waiting_checkin', 'checked_in'].includes(ticket.status);

    return (
        <PublicLayout showQueueCta={false}>
            <Head title="Tiket Digital">
                <meta name="robots" content="noindex, nofollow, noarchive" />
                <meta name="referrer" content="no-referrer" />
            </Head>
            <PublicNavbar />
            <main
                id="konten-utama"
                tabIndex={-1}
                className="public-container py-10 sm:py-14"
            >
                <div className="mx-auto max-w-xl">
                    <Link
                        href="/antrean"
                        className="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground hover:text-primary"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Kembali ke antrean
                    </Link>
                    <p className="mt-6 text-xs font-semibold tracking-[0.2em] text-primary uppercase">
                        Antrean Saya
                    </p>
                    <h1 className="mt-3 font-display text-4xl uppercase sm:text-5xl">
                        Tiket Digital
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                        Pantau giliran Anda. Detail tiket ini hanya tersedia
                        untuk pemegang akses yang sah.
                    </p>

                    <div
                        role="status"
                        aria-live="polite"
                        className="mt-6 space-y-3"
                    >
                        {notice && (
                            <p className="flex items-start gap-2 rounded-xl border border-success/30 bg-success/10 p-4 text-sm text-success">
                                <Check
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {notice}
                            </p>
                        )}
                        {!polling.online && (
                            <p className="flex items-start gap-2 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
                                <WifiOff
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                Anda sedang offline. Data yang tampil adalah
                                pembaruan terakhir.
                            </p>
                        )}
                        {(actionError || polling.error) && (
                            <p
                                role="alert"
                                className="rounded-xl border border-danger/30 bg-danger/10 p-4 text-sm leading-6 text-danger"
                            >
                                {actionError ?? polling.error}
                            </p>
                        )}
                    </div>

                    {(polling.loading || readyId !== ticketId) && !ticket && (
                        <div
                            role="status"
                            className="mt-6 rounded-3xl border border-border bg-card px-6 py-16 text-center"
                        >
                            <LoaderCircle
                                className="mx-auto size-8 animate-spin text-primary"
                                aria-hidden="true"
                            />
                            <p className="mt-4 text-sm text-muted-foreground">
                                Memeriksa akses tiket…
                            </p>
                        </div>
                    )}

                    {!polling.loading && readyId === ticketId && !ticket && (
                        <section
                            className="mt-6 rounded-2xl border border-border bg-card p-6 sm:p-8"
                            aria-labelledby="access-title"
                        >
                            <LockKeyhole
                                className="size-8 text-primary"
                                aria-hidden="true"
                            />
                            <h2
                                id="access-title"
                                className="mt-5 text-xl font-semibold"
                            >
                                Buka tiket pribadi Anda
                            </h2>
                            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                ID tiket saja tidak membuka detail pelanggan.
                                Gunakan browser saat mendaftar, atau masukkan
                                tautan pemulihan/kode akses yang Anda simpan.
                            </p>
                            <form
                                onSubmit={(event) => void exchangeAccess(event)}
                                className="mt-6 space-y-4"
                            >
                                <div>
                                    <Label htmlFor="ticket-access">
                                        Tautan pemulihan atau kode akses
                                    </Label>
                                    <Input
                                        id="ticket-access"
                                        type="password"
                                        value={accessValue}
                                        onChange={(event) =>
                                            setAccessValue(event.target.value)
                                        }
                                        autoComplete="off"
                                        spellCheck={false}
                                        className="mt-2 min-h-12"
                                        required
                                        disabled={pending !== null}
                                    />
                                </div>
                                <Button
                                    className="min-h-12 w-full"
                                    disabled={
                                        pending !== null || !polling.online
                                    }
                                >
                                    {pending === 'access' ? (
                                        <LoaderCircle
                                            className="size-4 animate-spin"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <KeyRound
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    )}
                                    Buka Tiket
                                </Button>
                            </form>
                            <Button
                                type="button"
                                variant="outline"
                                className="mt-3 min-h-11 w-full"
                                disabled={!polling.online || pending !== null}
                                onClick={polling.refresh}
                            >
                                <RefreshCw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Coba Sesi Browser Ini
                            </Button>
                        </section>
                    )}

                    {ticket && (
                        <div className="mt-6">
                            <QueueTicketCard ticket={ticket} />
                            {ticket.source === 'online' &&
                                ticket.status === 'waiting_checkin' && (
                                    <section
                                        className="mt-6 rounded-2xl border border-primary/30 bg-primary/5 p-5"
                                        aria-labelledby="checkin-title"
                                    >
                                        <h2
                                            id="checkin-title"
                                            className="flex items-center gap-2 font-semibold text-primary"
                                        >
                                            <QrCode
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                            Datang &amp; check-in di lokasi
                                        </h2>
                                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                            Tekan tombol scan lalu arahkan
                                            kamera ke QR lokasi di studio. Tiket
                                            ini langsung check-in setelah QR
                                            berhasil dibaca dan diperiksa. Nomor
                                            tidak berubah. Toleransi{' '}
                                            {graceMinutes} menit dimulai saat
                                            nomor Anda dilewati pada panggilan
                                            yang siap.
                                        </p>
                                        {ticket.grace_expires_at && (
                                            <p className="mt-3 text-sm font-medium text-warning">
                                                Batas toleransi:{' '}
                                                {timeLabel(
                                                    new Date(
                                                        ticket.grace_expires_at,
                                                    ),
                                                )}{' '}
                                                ({timezone}). Status terbaru
                                                tetap ditentukan server.
                                            </p>
                                        )}
                                        <Button
                                            type="button"
                                            className="mt-5 min-h-12 w-full whitespace-normal"
                                            disabled={
                                                pending !== null ||
                                                !polling.online
                                            }
                                            onClick={() => {
                                                setActionError(null);
                                                setScannerOpen(true);
                                            }}
                                        >
                                            <QrCode
                                                className="size-5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            Scan QR untuk Check-in
                                        </Button>
                                        <QrScannerDialog
                                            open={scannerOpen}
                                            onOpenChange={setScannerOpen}
                                            onScan={(code) =>
                                                mutate('checkin', code)
                                            }
                                        />
                                    </section>
                                )}

                            <div className="mt-6 rounded-2xl border border-border p-5">
                                <div className="flex items-start gap-3">
                                    <ShieldCheck
                                        className="mt-0.5 size-5 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <h2 className="font-semibold">
                                            Simpan akses tiket
                                        </h2>
                                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                            Sesi browser ini dapat membuka
                                            tiket. Simpan tautan pemulihan untuk
                                            perangkat lain; siapa pun yang
                                            memegang tautan dapat mengakses
                                            tiket ini.
                                        </p>
                                    </div>
                                </div>
                                {recoveryToken ? (
                                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => void copyRecovery()}
                                            className="min-h-11"
                                        >
                                            <Copy
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Salin Tautan
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={downloadRecovery}
                                            className="min-h-11"
                                        >
                                            <Download
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Unduh Akses
                                        </Button>
                                    </div>
                                ) : (
                                    <p className="mt-3 text-xs leading-5 text-muted-foreground">
                                        Tautan rahasia hanya diberikan saat
                                        pembuatan tiket. Gunakan tautan yang
                                        sudah Anda simpan bila sesi browser
                                        berakhir.
                                    </p>
                                )}
                            </div>

                            {canCancel && (
                                <div className="mt-6">
                                    <ConfirmActionDialog
                                        open={cancelOpen}
                                        onOpenChange={setCancelOpen}
                                        onConfirm={() => void mutate('cancel')}
                                        pending={pending === 'cancel'}
                                        disabled={
                                            pending !== null || !polling.online
                                        }
                                    />
                                </div>
                            )}
                            <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                                <p className="text-xs leading-5 text-muted-foreground">
                                    {polling.lastUpdated
                                        ? `Terakhir diperbarui ${timeLabel(polling.lastUpdated)}`
                                        : 'Belum ada pembaruan'}
                                    <br />
                                    {[
                                        'completed',
                                        'cancelled',
                                        'missed',
                                    ].includes(ticket.status)
                                        ? 'Tiket berakhir · pembaruan otomatis dihentikan'
                                        : 'Pembaruan berkala sekitar 12 detik'}
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="min-h-11"
                                    onClick={polling.refresh}
                                    disabled={
                                        !polling.online || pending !== null
                                    }
                                >
                                    <RefreshCw
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Perbarui
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </main>
        </PublicLayout>
    );
}
