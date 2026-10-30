import { Camera, ImagePlus, LoaderCircle, QrCode, Square } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { createQrScanSession, parseLocationQr } from '@/lib/location-qr';

type ScannerMode = 'idle' | 'starting' | 'camera' | 'reading' | 'checking';

function cameraMessage(failure: unknown): string {
    const name = failure instanceof Error ? failure.name : '';
    if (['NotAllowedError', 'SecurityError'].includes(name))
        return 'Izin kamera belum diberikan. Izinkan kamera di browser, lalu coba lagi atau baca QR dari foto.';
    if (['NotFoundError', 'DevicesNotFoundError'].includes(name))
        return 'Kamera tidak ditemukan. Gunakan perangkat berkamera atau baca QR dari foto.';
    if (['NotReadableError', 'TrackStartError'].includes(name))
        return 'Kamera sedang digunakan atau belum dapat dibuka. Tutup aplikasi kamera lain lalu coba lagi.';
    return 'Kamera belum dapat digunakan. Coba lagi atau baca QR dari foto.';
}

export function QrScannerDialog({
    open,
    onOpenChange,
    onScan,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onScan: (qrCode: string) => Promise<void>;
}) {
    const video = useRef<HTMLVideoElement>(null);
    const session = useRef(createQrScanSession());
    const submitting = useRef(false);
    const onScanRef = useRef(onScan);
    const onOpenChangeRef = useRef(onOpenChange);
    const [mode, setMode] = useState<ScannerMode>('idle');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        onScanRef.current = onScan;
        onOpenChangeRef.current = onOpenChange;
    }, [onScan, onOpenChange]);

    useEffect(() => {
        const lifecycle = session.current;
        lifecycle.reset();
        if (!open) return;
        setMode('idle');
        setError(null);
        const pause = () => {
            if (!document.hidden || submitting.current) return;
            lifecycle.reset();
            setMode('idle');
            setError(
                'Pemindaian dihentikan saat halaman tidak aktif. Tekan Buka Kamera untuk melanjutkan.',
            );
        };
        const pagehide = () => lifecycle.reset();
        document.addEventListener('visibilitychange', pause);
        window.addEventListener('pagehide', pagehide);
        return () => {
            lifecycle.reset();
            document.removeEventListener('visibilitychange', pause);
            window.removeEventListener('pagehide', pagehide);
        };
    }, [open]);

    async function accept(raw: string) {
        if (submitting.current) return;
        session.current.reset();
        const qrCode = parseLocationQr(raw, window.location.origin);
        if (!qrCode) {
            setMode('idle');
            setError(
                'QR ini bukan QR lokasi BARBERFLOW yang sesuai. Pindai QR lokasi pada alamat website yang sama.',
            );
            return;
        }
        submitting.current = true;
        const request = session.current.scope();
        setMode('checking');
        setError(null);
        try {
            await onScanRef.current(qrCode);
            if (request.current()) onOpenChangeRef.current(false);
        } catch (failure) {
            if (request.current()) {
                setMode('idle');
                setError(
                    failure instanceof Error
                        ? failure.message.slice(0, 500)
                        : 'Check-in belum berhasil. Silakan coba lagi.',
                );
            }
        } finally {
            submitting.current = false;
        }
    }

    async function startCamera() {
        if (submitting.current) return;
        session.current.reset();
        setError(null);
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setMode('idle');
            setError(
                'Kamera memerlukan HTTPS atau localhost dan browser yang mendukung kamera. Anda tetap dapat membaca QR dari foto.',
            );
            return;
        }
        const request = session.current.scope();
        setMode('starting');
        try {
            const { BrowserQRCodeReader } = await import('@zxing/browser');
            if (!request.current()) return;
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });
            request.retain(() =>
                stream.getTracks().forEach((track) => track.stop()),
            );
            if (!request.current()) return;
            const preview = video.current;
            if (!preview) throw new Error('Camera preview unavailable');
            preview.srcObject = stream;
            request.retain(() => {
                preview.pause();
                preview.srcObject = null;
            });
            await preview.play();
            if (!request.current()) return;
            setMode('camera');
            const reader = new BrowserQRCodeReader();
            let timer: ReturnType<typeof setTimeout>;
            request.retain(() => clearTimeout(timer));
            const scan = () => {
                if (!request.current()) return;
                try {
                    if (preview.readyState >= 2 && preview.videoWidth > 0) {
                        const result = reader.decode(preview);
                        void accept(result.getText());
                        return;
                    }
                } catch {
                    // A frame without a decodable QR is expected while positioning.
                }
                timer = setTimeout(scan, 300);
            };
            scan();
        } catch (failure) {
            if (!request.current()) return;
            session.current.reset();
            setMode('idle');
            setError(cameraMessage(failure));
        }
    }

    async function readPhoto(file: File) {
        if (submitting.current) return;
        session.current.reset();
        setError(null);
        if (
            !['image/png', 'image/jpeg', 'image/webp'].includes(file.type) ||
            file.size > 5 * 1024 * 1024
        ) {
            setMode('idle');
            setError(
                'Pilih foto PNG, JPG atau WebP dengan ukuran maksimal 5 MB.',
            );
            return;
        }
        const request = session.current.scope();
        setMode('reading');
        try {
            const { PhotoQrReader } = await import('@/lib/qr-photo-reader');
            if (!request.current()) return;
            const url = URL.createObjectURL(file);
            request.retain(() => URL.revokeObjectURL(url));
            const result = await new PhotoQrReader().decodeFromImageUrl(url);
            if (request.current()) await accept(result.getText());
        } catch {
            if (!request.current()) return;
            session.current.reset();
            setMode('idle');
            setError(
                'QR belum terbaca dari foto. Pilih foto QR yang jelas, utuh dan tidak terpotong.',
            );
        }
    }

    const busy = ['starting', 'reading', 'checking'].includes(mode);
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (submitting.current) return;
                session.current.reset();
                onOpenChange(value);
            }}
        >
            <DialogContent
                closeLabel="Tutup pemindai QR"
                className="public-theme max-h-[90dvh] overflow-y-auto border-border bg-background text-foreground [&>button]:size-11 [&>button]:translate-x-2 [&>button]:-translate-y-2"
                onEscapeKeyDown={(event) => {
                    if (submitting.current) event.preventDefault();
                }}
                onInteractOutside={(event) => {
                    if (submitting.current) event.preventDefault();
                }}
            >
                <DialogHeader className="pr-7 text-left">
                    <DialogTitle>Scan QR lokasi untuk check-in</DialogTitle>
                    <DialogDescription>
                        Arahkan kamera ke QR BARBERFLOW di studio. QR yang
                        terbaca akan langsung dipakai untuk check-in tiket ini.
                    </DialogDescription>
                </DialogHeader>
                <div className="relative aspect-[4/3] overflow-hidden rounded-xl border border-border bg-black">
                    <video
                        ref={video}
                        muted
                        playsInline
                        autoPlay
                        aria-label="Pratinjau kamera pemindai QR"
                        className="h-full w-full object-cover"
                    />
                    {mode !== 'camera' && (
                        <div
                            className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-card px-5 text-center text-sm text-muted-foreground"
                            role="status"
                        >
                            {busy ? (
                                <LoaderCircle
                                    className="size-9 animate-spin text-primary"
                                    aria-hidden="true"
                                />
                            ) : (
                                <QrCode
                                    className="size-12 text-primary"
                                    aria-hidden="true"
                                />
                            )}
                            <p>
                                {mode === 'checking'
                                    ? 'QR terbaca. Memproses check-in…'
                                    : mode === 'reading'
                                      ? 'Membaca QR dari foto…'
                                      : mode === 'starting'
                                        ? 'Menyiapkan kamera. Izinkan akses kamera jika diminta.'
                                        : 'Kamera belum aktif.'}
                            </p>
                        </div>
                    )}
                </div>
                {error && (
                    <p
                        role="alert"
                        className="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm leading-6 text-danger"
                    >
                        {error}
                    </p>
                )}
                <div className="grid gap-3 sm:grid-cols-2">
                    {mode === 'camera' ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-12"
                            onClick={() => {
                                session.current.reset();
                                setMode('idle');
                            }}
                        >
                            <Square className="size-4" aria-hidden="true" />
                            Hentikan Kamera
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            className="min-h-12"
                            disabled={busy}
                            onClick={() => void startCamera()}
                        >
                            <Camera className="size-4" aria-hidden="true" />
                            Buka Kamera
                        </Button>
                    )}
                    <label
                        data-disabled={busy}
                        className={buttonVariants({
                            variant: 'outline',
                            className:
                                'relative min-h-12 cursor-pointer focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 data-[disabled=true]:cursor-default data-[disabled=true]:opacity-50',
                        })}
                    >
                        <ImagePlus className="size-4" aria-hidden="true" />
                        Baca QR dari Foto
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            className="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-default"
                            aria-label="Foto QR lokasi"
                            disabled={busy}
                            onClick={() => {
                                session.current.reset();
                                setMode('idle');
                                setError(null);
                            }}
                            onChange={(event) => {
                                const file = event.currentTarget.files?.[0];
                                event.currentTarget.value = '';
                                if (file) void readPhoto(file);
                            }}
                        />
                    </label>
                </div>
                <p className="text-xs leading-5 text-muted-foreground">
                    Foto diproses pada perangkat Anda dan tidak diunggah. Kamera
                    berhenti ketika pemindai ditutup. Anda dapat mencoba lagi
                    jika QR tidak terbaca.
                </p>
            </DialogContent>
        </Dialog>
    );
}
