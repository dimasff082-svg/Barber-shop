import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Info,
    RefreshCw,
    Scissors,
    UserRound,
} from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { ContentImage } from '@/components/public/content-image';
import { FormFieldError } from '@/components/queue/form-field-error';
import { LoadingButton } from '@/components/queue/loading-button';
import { QueueEmptyState } from '@/components/queue/queue-empty-state';
import { QueueStepIndicator } from '@/components/queue/queue-step-indicator';
import {
    formatQueuePrice,
    QueueSummary,
} from '@/components/queue/queue-summary';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { contact } from '@/routes/public';
import type { QueueFormProps } from '@/types/customer';
import { useLiveUpdates } from '@/hooks/use-live-updates';

const titles = [
    'Pilih layanan Anda.',
    'Pilih barber Anda.',
    'Lengkapi data diri.',
    'Periksa antrean Anda.',
];
const descriptions = [
    'Satu layanan untuk penampilan terbaik Anda.',
    'Pilih barber yang sedang menerima antrean.',
    'Tanpa membuat akun. Gunakan nama dan nomor HP yang benar.',
    'Pastikan pilihan dan data Anda sudah sesuai.',
];
const availabilityLabels = {
    available: 'Tersedia',
    busy: 'Sedang melayani',
    break: 'Istirahat',
    off_duty: 'Tidak bertugas',
};

export function QueueBookingForm(props: QueueFormProps) {
    useLiveUpdates('public');
    const {
        services,
        barbers,
        registration,
        qr,
        selected_service,
        selected_barber,
        store_url,
    } = props;
    const [step, setStep] = useState(0);
    const [networkError, setNetworkError] = useState<string | null>(null);
    const [refreshing, setRefreshing] = useState(false);
    const heading = useRef<HTMLHeadingElement>(null);
    const form = useForm({
        name: '',
        phone: '',
        service_id: services.some((service) => service.id === selected_service)
            ? String(selected_service)
            : '',
        barber_id: barbers.some(
            (barber) =>
                barber.id === selected_barber && barber.registration.allowed,
        )
            ? String(selected_barber)
            : '',
        consent: false,
        idempotency_key: crypto.randomUUID(),
    });
    const service = services.find(
        (item) => String(item.id) === form.data.service_id,
    );
    const barber = barbers.find(
        (item) => String(item.id) === form.data.barber_id,
    );
    const errors: Record<string, string | undefined> = form.errors;

    useEffect(() => {
        heading.current?.focus({ preventScroll: true });
    }, [step]);

    function refresh() {
        setRefreshing(true);
        router.reload({
            only: ['services', 'barbers', 'registration'],
            onFinish: () => setRefreshing(false),
        });
    }

    function advance() {
        if (step === 0 && !service) {
            form.setError(
                'service_id',
                'Pilih satu layanan untuk melanjutkan.',
            );
            return;
        }
        if (step === 1 && (!barber || !barber.registration.allowed)) {
            form.setError(
                'barber_id',
                'Pilih barber yang sedang menerima antrean.',
            );
            return;
        }
        if (step === 2) {
            if (form.data.name.trim().length < 2) {
                form.setError('name', 'Nama minimal 2 karakter.');
                return;
            }
            if (!form.data.phone.trim()) {
                form.setError('phone', 'Nomor HP wajib diisi.');
                return;
            }
        }
        form.clearErrors();
        setStep(Math.min(step + 1, 3));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (step < 3) {
            advance();
            return;
        }
        if (form.processing || !registration.open) return;
        if (!service) {
            form.setError(
                'service_id',
                'Layanan pilihan sudah tidak tersedia. Pilih layanan lain.',
            );
            setStep(0);
            return;
        }
        if (!barber?.registration.allowed) {
            form.setError(
                'barber_id',
                'Barber pilihan sudah tidak menerima antrean. Pilih barber lain.',
            );
            setStep(1);
            return;
        }
        setNetworkError(null);
        form.post(store_url, {
            preserveScroll: true,
            onError: (serverErrors) => {
                if (serverErrors.service_id) setStep(0);
                else if (serverErrors.barber_id) setStep(1);
                else if (serverErrors.name || serverErrors.phone) setStep(2);
            },
            onNetworkError: () => {
                setNetworkError(
                    'Koneksi terputus. Data tetap tersimpan pada formulir ini. Coba kirim kembali tanpa mengganti pilihan agar permintaan yang sama tidak membuat tiket ganda.',
                );
                return false;
            },
            onHttpException: (response) => {
                setNetworkError(
                    response.status === 429
                        ? 'Terlalu banyak percobaan. Tunggu sebentar sebelum mencoba lagi.'
                        : response.status === 419
                          ? 'Sesi formulir telah berakhir. Muat ulang halaman sebelum mengirim kembali.'
                          : 'Permintaan belum dapat diselesaikan. Periksa tiket yang sudah dimiliki sebelum mencoba lagi.',
                );
                return false;
            },
        });
    }

    if (
        !registration.open ||
        services.length === 0 ||
        !barbers.some((item) => item.registration.allowed)
    ) {
        return (
            <QueueEmptyState
                title={
                    !registration.open
                        ? 'Pendaftaran belum dibuka'
                        : services.length === 0
                          ? 'Layanan belum tersedia'
                          : 'Belum ada barber yang menerima antrean'
                }
                description={
                    !registration.open
                        ? registration.message
                        : 'Silakan periksa kembali nanti. Tiket yang sudah dimiliki tetap dapat dibuka dan check-in melalui QR aktif di lokasi.'
                }
            >
                <LoadingButton
                    variant="outline"
                    className="min-h-11"
                    loading={refreshing}
                    onClick={refresh}
                >
                    <RefreshCw className="size-4" aria-hidden="true" />
                    Periksa ketersediaan
                </LoadingButton>
            </QueueEmptyState>
        );
    }

    return (
        <form
            onSubmit={submit}
            noValidate
            aria-busy={form.processing}
            data-realtime-pause={
                form.isDirty || step > 0 || form.processing ? 'true' : undefined
            }
        >
            <QueueStepIndicator current={step} />
            <p className="text-xs font-semibold tracking-widest text-primary uppercase">
                {qr ? 'Antrean di lokasi' : 'Antrean online'} · Langkah{' '}
                {step + 1} dari 4
            </p>
            <h2
                ref={heading}
                tabIndex={-1}
                className="mt-3 font-display text-3xl uppercase outline-none sm:text-4xl"
            >
                {titles[step]}
            </h2>
            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                {descriptions[step]}
            </p>
            <div className="my-6 flex items-start gap-3 rounded-xl border border-primary/25 bg-primary/5 p-4 text-sm leading-6">
                <Info
                    className="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <p>
                    {qr
                        ? 'Tiket baru melalui QR langsung tercatat sudah hadir. Sudah punya tiket online? Gunakan menu Check-in Antrean Saya, bukan membuat tiket kedua.'
                        : `Tiket online wajib check-in melalui QR di lokasi. Jika nomor Anda dilewati, masa toleransi ${registration.grace_minutes} menit dimulai saat barber memanggil pelanggan berikutnya.`}
                </p>
            </div>
            <fieldset disabled={form.processing} className="min-w-0">
                {step === 0 && (
                    <fieldset aria-describedby="service-error">
                        <legend className="sr-only">Pilih layanan</legend>
                        <div className="grid gap-3">
                            {services.map((item) => (
                                <label
                                    key={item.id}
                                    className={cn(
                                        'relative flex min-w-0 cursor-pointer items-center gap-4 rounded-2xl border bg-card p-4 transition-colors sm:p-5',
                                        service?.id === item.id
                                            ? 'border-primary'
                                            : 'border-border hover:border-primary/50',
                                    )}
                                >
                                    <input
                                        className="peer sr-only"
                                        type="radio"
                                        name="service_id"
                                        value={item.id}
                                        checked={service?.id === item.id}
                                        onChange={() => {
                                            form.setData(
                                                'service_id',
                                                String(item.id),
                                            );
                                            form.clearErrors('service_id');
                                        }}
                                    />
                                    <span className="absolute inset-0 rounded-2xl peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-primary" />
                                    {item.image ? (
                                        <div className="hidden size-16 shrink-0 overflow-hidden rounded-lg min-[360px]:block">
                                            <ContentImage
                                                src={item.image}
                                                alt={item.name}
                                                width={80}
                                                height={80}
                                            />
                                        </div>
                                    ) : (
                                        <span className="hidden size-14 shrink-0 items-center justify-center rounded-lg bg-surface-raised min-[360px]:flex">
                                            <Scissors
                                                className="size-6 text-primary"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    )}
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold">
                                            {item.name}
                                        </span>
                                        <span className="mt-1 block text-sm text-muted-foreground">
                                            {item.duration_minutes} menit ·
                                            durasi perkiraan
                                        </span>
                                        <span className="mt-2 block font-semibold text-primary">
                                            {formatQueuePrice(item.price)}
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-full border',
                                            service?.id === item.id
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-muted-foreground',
                                        )}
                                    >
                                        {service?.id === item.id && (
                                            <Check
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <FormFieldError
                            id="service-error"
                            message={errors.service_id}
                        />
                    </fieldset>
                )}
                {step === 1 && (
                    <fieldset aria-describedby="barber-error">
                        <legend className="sr-only">Pilih barber</legend>
                        <div className="grid gap-3">
                            {barbers.map((item) => (
                                <label
                                    key={item.id}
                                    className={cn(
                                        'relative flex min-w-0 items-start gap-4 rounded-2xl border bg-card p-4 sm:p-5',
                                        item.registration.allowed
                                            ? 'cursor-pointer'
                                            : 'cursor-not-allowed opacity-60',
                                        barber?.id === item.id
                                            ? 'border-primary'
                                            : 'border-border',
                                    )}
                                >
                                    <input
                                        className="peer sr-only"
                                        type="radio"
                                        name="barber_id"
                                        value={item.id}
                                        disabled={!item.registration.allowed}
                                        checked={barber?.id === item.id}
                                        onChange={() => {
                                            form.setData(
                                                'barber_id',
                                                String(item.id),
                                            );
                                            form.clearErrors('barber_id');
                                        }}
                                    />
                                    <span className="absolute inset-0 rounded-2xl peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-primary" />
                                    {item.photo ? (
                                        <div className="size-14 shrink-0 overflow-hidden rounded-lg">
                                            <ContentImage
                                                src={item.photo}
                                                alt={item.name}
                                                width={70}
                                                height={70}
                                            />
                                        </div>
                                    ) : (
                                        <span className="flex size-12 shrink-0 items-center justify-center rounded-lg bg-surface-raised">
                                            <UserRound
                                                className="size-5 text-primary"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    )}
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold">
                                            {item.name}
                                        </span>
                                        <span className="mt-1 block text-sm text-primary">
                                            {
                                                availabilityLabels[
                                                    item.availability
                                                ]
                                            }
                                        </span>
                                        {item.specialization && (
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {item.specialization}
                                            </span>
                                        )}
                                        <span className="mt-2 block text-sm text-muted-foreground">
                                            {item.registration.allowed
                                                ? `${item.waiting_count} antrean menunggu saat ini`
                                                : item.registration.reason}
                                        </span>
                                    </span>
                                    {barber?.id === item.id && (
                                        <Check
                                            className="size-5 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                    )}
                                </label>
                            ))}
                        </div>
                        <FormFieldError
                            id="barber-error"
                            message={errors.barber_id}
                        />
                    </fieldset>
                )}
                {step === 2 && (
                    <div className="grid gap-6 rounded-2xl border border-border bg-card p-5 sm:p-6">
                        <div>
                            <Label htmlFor="queue-name">Nama pelanggan</Label>
                            <Input
                                id="queue-name"
                                name="name"
                                autoComplete="name"
                                className="mt-2 min-h-12"
                                maxLength={100}
                                value={form.data.name}
                                aria-invalid={Boolean(errors.name)}
                                aria-describedby="name-error"
                                onChange={(event) => {
                                    form.setData('name', event.target.value);
                                    form.clearErrors('name');
                                }}
                                placeholder="Nama Anda"
                            />
                            <FormFieldError
                                id="name-error"
                                message={errors.name}
                            />
                        </div>
                        <div>
                            <Label htmlFor="queue-phone">Nomor HP</Label>
                            <Input
                                id="queue-phone"
                                name="phone"
                                type="tel"
                                inputMode="tel"
                                autoComplete="tel"
                                className="mt-2 min-h-12"
                                maxLength={30}
                                value={form.data.phone}
                                aria-invalid={Boolean(errors.phone)}
                                aria-describedby="phone-help phone-error"
                                onChange={(event) => {
                                    form.setData('phone', event.target.value);
                                    form.clearErrors('phone');
                                }}
                                placeholder="Contoh: 081234567890"
                            />
                            <p
                                id="phone-help"
                                className="mt-2 text-xs leading-5 text-muted-foreground"
                            >
                                Gunakan nomor HP Indonesia. Satu nomor hanya
                                dapat memiliki satu antrean aktif per hari.
                            </p>
                            <FormFieldError
                                id="phone-error"
                                message={errors.phone}
                            />
                        </div>
                        <p className="text-xs leading-5 text-muted-foreground">
                            Nama dan nomor HP digunakan untuk keperluan antrean
                            dan tidak ditampilkan pada ringkasan publik.
                        </p>
                    </div>
                )}
                {step === 3 && service && barber && (
                    <>
                        <QueueSummary
                            service={service}
                            barber={barber}
                            name={form.data.name}
                            phone={form.data.phone}
                            onsite={Boolean(qr)}
                            date={registration.date}
                        />
                        <label className="mt-6 flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-border p-4">
                            <input
                                type="checkbox"
                                name="consent"
                                className="mt-1 size-5 shrink-0 accent-primary"
                                checked={form.data.consent}
                                aria-describedby="consent-error"
                                onChange={(event) => {
                                    form.setData(
                                        'consent',
                                        event.target.checked,
                                    );
                                    form.clearErrors('consent');
                                }}
                            />
                            <span className="text-sm leading-6">
                                Saya menyetujui penggunaan data untuk antrean,
                                memahami bahwa estimasi dapat berubah, dan akan
                                mengikuti aturan check-in.
                            </span>
                        </label>
                        <FormFieldError
                            id="consent-error"
                            message={errors.consent}
                        />
                    </>
                )}
            </fieldset>
            {(errors.queue || errors.idempotency_key || networkError) && (
                <div
                    role="alert"
                    className="mt-6 rounded-xl border border-danger/40 bg-danger/5 p-4 text-sm leading-6 text-danger"
                >
                    {errors.queue || errors.idempotency_key || networkError}
                </div>
            )}
            <div className="mt-8 flex flex-col gap-3 border-t border-border pt-6">
                {step < 3 ? (
                    <Button
                        type="button"
                        onClick={advance}
                        className="min-h-12 w-full whitespace-normal"
                    >
                        {
                            [
                                'Lanjut pilih barber',
                                'Lanjut isi data',
                                'Periksa ringkasan',
                            ][step]
                        }
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </Button>
                ) : (
                    <LoadingButton
                        type="submit"
                        className="min-h-12 w-full whitespace-normal"
                        loading={form.processing}
                        disabled={!form.data.consent}
                    >
                        Konfirmasi dan ambil antrean
                        <Check className="size-4" aria-hidden="true" />
                    </LoadingButton>
                )}
                {step > 0 && (
                    <Button
                        type="button"
                        variant="ghost"
                        className="min-h-11"
                        disabled={form.processing}
                        onClick={() => setStep(step - 1)}
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Kembali
                    </Button>
                )}
            </div>
            <p className="mt-5 text-center text-xs leading-5 text-muted-foreground">
                Nomor antrean diterbitkan setelah konfirmasi berhasil.{' '}
                <Link
                    href={contact()}
                    className="text-primary underline underline-offset-4"
                >
                    Butuh bantuan?
                </Link>
            </p>
        </form>
    );
}
