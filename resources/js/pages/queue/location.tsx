import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Info,
    Plus,
    QrCode,
    Ticket,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { PublicNavbar } from '@/components/public/public-navbar';
import { FormFieldError } from '@/components/queue/form-field-error';
import { LoadingButton } from '@/components/queue/loading-button';
import { QueueBookingForm } from '@/components/queue/queue-booking-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/public-layout';
import {
    parseTicketRecovery,
    QueueRequestError,
    queueRequest,
} from '@/lib/queue-request';
import { access, checkIn, show } from '@/routes/queue';
import { home } from '@/routes';
import type { QueueFormProps } from '@/types/customer';

export default function QueueLocation(props: QueueFormProps) {
    const [mode, setMode] = useState<'new' | 'check-in' | null>(null);
    const [value, setValue] = useState('');
    const [recoveryValue, setRecoveryValue] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);

    async function submitCheckIn(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (busy || !props.qr) return;
        const identifier = parseTicketRecovery(value.trim());
        const recovered = recoveryValue.trim()
            ? parseTicketRecovery(recoveryValue.trim())
            : null;
        if (
            !identifier ||
            identifier.token ||
            (recoveryValue.trim() &&
                (!recovered?.token || recovered.id !== identifier.id))
        ) {
            setError(
                'Gunakan ID tiket yang valid. Jika memulihkan akses, tautan rahasia harus sesuai dengan ID tiket ini.',
            );
            return;
        }
        const parsed = { id: identifier.id, token: recovered?.token };
        setRecoveryValue('');
        setBusy(true);
        setError(null);
        try {
            if (parsed.token)
                await queueRequest(access.url(parsed.id), {
                    method: 'POST',
                    token: parsed.token,
                });
            await queueRequest(checkIn.url(parsed.id), {
                method: 'POST',
                body: { qr_code: props.qr.code },
            });
            setSuccess(true);
            router.visit(show.url(parsed.id));
        } catch (failure) {
            if (failure instanceof QueueRequestError) {
                setError(
                    failure.status === 404
                        ? 'Akses tiket belum ditemukan. Tempel tautan akses lengkap milik Anda, atau buka dengan perangkat yang digunakan saat mengambil antrean.'
                        : failure.status === 429
                          ? 'Terlalu banyak percobaan. Tunggu sebentar sebelum mencoba lagi.'
                          : failure.status === 419
                            ? 'Sesi telah berakhir. Muat ulang halaman lalu coba kembali.'
                            : Object.values(failure.errors).flat().join(' ') ||
                              'Check-in belum berhasil. Periksa masa berlaku tiket dan QR lokasi.',
                );
            } else
                setError(
                    'Koneksi terputus. Periksa koneksi lalu coba lagi. Nomor antrean Anda tetap sama.',
                );
        } finally {
            setBusy(false);
        }
    }

    return (
        <PublicLayout showQueueCta={false}>
            <Head title="Antrean di Lokasi">
                <meta name="referrer" content="no-referrer" />
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <PublicNavbar />
            <main
                id="konten-utama"
                tabIndex={-1}
                className="public-container py-10 outline-none sm:py-14"
            >
                <div className="mx-auto max-w-160">
                    <p className="text-xs font-semibold tracking-widest text-primary uppercase">
                        Selamat datang di BARBERFLOW
                    </p>
                    <h1 className="mt-3 font-display text-4xl uppercase sm:text-5xl">
                        Antre lebih mudah.
                    </h1>
                    <p className="mt-4 text-sm leading-6 text-muted-foreground">
                        Ambil antrean baru atau check-in tiket online yang sudah
                        Anda miliki.
                    </p>
                    <div className="mt-7 flex items-center gap-4 rounded-2xl border border-primary/30 bg-card p-5">
                        <span className="flex size-14 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                            <QrCode
                                className="size-7 text-primary"
                                aria-hidden="true"
                            />
                        </span>
                        <div className="min-w-0">
                            <p className="font-semibold">
                                Portal QR lokasi aktif
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {props.qr?.label || 'BARBERFLOW'}
                            </p>
                        </div>
                    </div>
                    <div
                        className="mt-6 grid gap-3 sm:grid-cols-2"
                        aria-label="Pilih tindakan"
                    >
                        <Button
                            type="button"
                            className="min-h-14 whitespace-normal"
                            variant={
                                mode === 'check-in' ? 'outline' : 'default'
                            }
                            onClick={() => {
                                setMode('new');
                                setError(null);
                            }}
                            aria-pressed={mode === 'new'}
                            disabled={busy}
                        >
                            <Plus
                                className="size-5 shrink-0"
                                aria-hidden="true"
                            />
                            Ambil Antrean Baru
                        </Button>
                        <Button
                            type="button"
                            className="min-h-14 whitespace-normal"
                            variant={
                                mode === 'check-in' ? 'default' : 'outline'
                            }
                            onClick={() => {
                                setMode('check-in');
                                setError(null);
                            }}
                            aria-pressed={mode === 'check-in'}
                            disabled={busy}
                        >
                            <Check
                                className="size-5 shrink-0"
                                aria-hidden="true"
                            />
                            Check-in Antrean Saya
                        </Button>
                    </div>
                    {!mode && (
                        <div className="mt-6 flex items-start gap-3 rounded-xl border border-border bg-card p-5 text-sm leading-6">
                            <Info
                                className="mt-0.5 size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <p>
                                Sudah daftar dari rumah? Gunakan tiket lama
                                untuk check-in. Tidak perlu mengambil nomor
                                baru.
                            </p>
                        </div>
                    )}
                    {mode === 'new' && (
                        <section
                            className="mt-10"
                            aria-label="Ambil antrean baru di lokasi"
                        >
                            <QueueBookingForm {...props} />
                        </section>
                    )}
                    {mode === 'check-in' && (
                        <section
                            className="mt-8 rounded-2xl border border-border bg-card p-5 sm:p-6"
                            aria-labelledby="checkin-heading"
                        >
                            <div className="mb-5 flex items-center gap-3">
                                <Ticket
                                    className="size-6 text-primary"
                                    aria-hidden="true"
                                />
                                <h2
                                    id="checkin-heading"
                                    className="text-xl font-semibold"
                                >
                                    Gunakan tiket Anda
                                </h2>
                            </div>
                            <p className="mb-6 text-sm leading-6 text-muted-foreground">
                                Salin ID dari tiket digital Anda, lalu tempel di
                                sini. Gunakan browser saat mengambil antrean.
                                Anda juga dapat langsung menekan Scan QR untuk
                                Check-in pada tiket digital.
                            </p>
                            <form onSubmit={submitCheckIn}>
                                <Label htmlFor="ticket-access">ID tiket</Label>
                                <Input
                                    id="ticket-access"
                                    type="text"
                                    autoComplete="off"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    value={value}
                                    onChange={(event) => {
                                        setValue(event.target.value);
                                        setRecoveryValue('');
                                        setError(null);
                                    }}
                                    className="mt-2 min-h-12"
                                    placeholder="Tempel ID tiket (26 karakter)"
                                    disabled={busy}
                                    aria-describedby="ticket-access-help ticket-access-error"
                                    aria-invalid={Boolean(error)}
                                    maxLength={26}
                                    required
                                />
                                <p
                                    id="ticket-access-help"
                                    className="mt-2 text-xs leading-5 text-muted-foreground"
                                >
                                    Check-in memakai ID tiket ini. Akses tiket
                                    tetap diverifikasi dari sesi browser Anda.
                                </p>
                                <FormFieldError
                                    id="ticket-access-error"
                                    message={error || undefined}
                                />
                                <details className="mt-5 rounded-xl border border-border p-4">
                                    <summary className="cursor-pointer py-2 text-sm font-medium">
                                        Menggunakan perangkat lain?
                                    </summary>
                                    <Label
                                        htmlFor="ticket-recovery"
                                        className="mt-3"
                                    >
                                        Tautan pemulihan tiket
                                    </Label>
                                    <Input
                                        id="ticket-recovery"
                                        type="password"
                                        value={recoveryValue}
                                        onChange={(event) => {
                                            const next = event.target.value;
                                            setRecoveryValue(next);
                                            const recovered =
                                                parseTicketRecovery(
                                                    next.trim(),
                                                );
                                            if (recovered?.token)
                                                setValue(recovered.id);
                                            setError(null);
                                        }}
                                        autoComplete="off"
                                        autoCapitalize="none"
                                        spellCheck={false}
                                        maxLength={1000}
                                        className="mt-2 min-h-12"
                                        placeholder="Tempel tautan rahasia yang disimpan"
                                        disabled={busy}
                                    />
                                    <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                        Tautan ini memberi akses ke tiket.
                                        Simpan secara pribadi.
                                    </p>
                                </details>
                                <LoadingButton
                                    type="submit"
                                    loading={busy}
                                    disabled={!value.trim() || success}
                                    className="mt-6 min-h-12 w-full"
                                >
                                    Check-in dengan tiket ini
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </LoadingButton>
                            </form>
                            {success && (
                                <p
                                    role="status"
                                    className="mt-5 text-sm leading-6 text-success"
                                >
                                    Check-in berhasil. Membuka tiket Anda…
                                </p>
                            )}
                            {props.recent_tickets.length > 0 && (
                                <div className="mt-6 border-t border-border pt-5">
                                    <p className="text-sm text-muted-foreground">
                                        Tiket pada sesi ini
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {props.recent_tickets.map(
                                            (ticket, index) => (
                                                <Button
                                                    key={ticket.id}
                                                    type="button"
                                                    variant="outline"
                                                    className="min-h-11"
                                                    disabled={busy}
                                                    onClick={() => {
                                                        setValue(ticket.id);
                                                        setRecoveryValue('');
                                                        setError(null);
                                                    }}
                                                >
                                                    Gunakan tiket {index + 1}
                                                </Button>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}
                        </section>
                    )}
                    <p className="mt-8 text-xs leading-5 text-muted-foreground">
                        QR statis membantu membuka portal lokasi dan dapat
                        dibagikan. QR ini belum menjadi bukti kehadiran fisik
                        yang kuat.
                    </p>
                    <Link
                        href={home()}
                        className="mt-6 inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground hover:text-primary"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Kembali ke beranda
                    </Link>
                </div>
            </main>
        </PublicLayout>
    );
}
