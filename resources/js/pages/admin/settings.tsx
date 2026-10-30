import { useForm } from '@inertiajs/react';
import { Save, Settings2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { StaffPage } from '@/components/staff/staff-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/admin/settings';

type Settings = {
    business_name: string;
    address: string | null;
    whatsapp: string | null;
    map_url: string | null;
    queue_registration_open: boolean;
    daily_queue_capacity: number;
    grace_minutes: number;
};

export default function BusinessSettings({
    settings,
    timezone,
}: {
    settings: Settings;
    timezone: string;
}) {
    const form = useForm({
        ...settings,
        address: settings.address ?? '',
        whatsapp: settings.whatsapp ?? '',
        map_url: settings.map_url ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };
    const fieldError = (field: keyof Settings) =>
        form.errors[field] && (
            <p id={`${field}-error`} className="mt-2 text-sm text-destructive">
                {form.errors[field]}
            </p>
        );

    return (
        <StaffPage
            title="Pengaturan Usaha"
            description="Atur identitas studio, kontak dan penerimaan antrean. Tiket yang sudah terbit tetap diproses sesuai aturan."
        >
            <form
                onSubmit={submit}
                className="max-w-3xl space-y-6"
                aria-busy={form.processing}
            >
                <section className="rounded-2xl border bg-card p-5 sm:p-7">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <Settings2
                            className="size-5 text-primary"
                            aria-hidden="true"
                        />
                        Identitas & kontak
                    </h2>
                    <div className="mt-6 space-y-5">
                        <div>
                            <Label htmlFor="business_name">Nama usaha</Label>
                            <Input
                                id="business_name"
                                className="mt-2 min-h-11"
                                value={form.data.business_name}
                                onChange={(event) =>
                                    form.setData(
                                        'business_name',
                                        event.target.value,
                                    )
                                }
                                maxLength={100}
                                aria-describedby="business_name-error"
                                required
                            />
                            {fieldError('business_name')}
                        </div>
                        <div>
                            <Label htmlFor="address">Alamat studio</Label>
                            <textarea
                                id="address"
                                className="mt-2 min-h-28 w-full rounded-md border border-input bg-transparent p-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                value={form.data.address}
                                onChange={(event) =>
                                    form.setData('address', event.target.value)
                                }
                                maxLength={1000}
                                aria-describedby="address-error"
                            />
                            {fieldError('address')}
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="whatsapp">
                                    Nomor WhatsApp resmi
                                </Label>
                                <Input
                                    id="whatsapp"
                                    type="tel"
                                    className="mt-2 min-h-11"
                                    placeholder="081234567890"
                                    value={form.data.whatsapp}
                                    onChange={(event) =>
                                        form.setData(
                                            'whatsapp',
                                            event.target.value,
                                        )
                                    }
                                    aria-describedby="whatsapp-error"
                                />
                                {fieldError('whatsapp')}
                            </div>
                            <div>
                                <Label htmlFor="map_url">
                                    Tautan peta (HTTPS)
                                </Label>
                                <Input
                                    id="map_url"
                                    type="url"
                                    className="mt-2 min-h-11"
                                    placeholder="https://maps.google.com/..."
                                    value={form.data.map_url}
                                    onChange={(event) =>
                                        form.setData(
                                            'map_url',
                                            event.target.value,
                                        )
                                    }
                                    aria-describedby="map_url-error"
                                />
                                {fieldError('map_url')}
                            </div>
                        </div>
                    </div>
                </section>
                <section className="rounded-2xl border bg-card p-5 sm:p-7">
                    <h2 className="text-lg font-semibold">
                        Penerimaan antrean
                    </h2>
                    <label className="mt-5 flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-4">
                        <input
                            type="checkbox"
                            className="mt-0.5 size-5 shrink-0 accent-primary"
                            checked={form.data.queue_registration_open}
                            onChange={(event) =>
                                form.setData(
                                    'queue_registration_open',
                                    event.target.checked,
                                )
                            }
                        />
                        <span className="text-sm leading-6">
                            <strong className="block">
                                Buka pendaftaran antrean
                            </strong>
                            Tiket baru juga memerlukan jam buka, jadwal barber,
                            layanan aktif, ketersediaan dan kapasitas. Menutup
                            pendaftaran tidak membatalkan tiket.
                        </span>
                    </label>
                    {fieldError('queue_registration_open')}
                    <div className="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="daily_queue_capacity">
                                Kapasitas default per barber/hari
                            </Label>
                            <Input
                                id="daily_queue_capacity"
                                type="number"
                                min={1}
                                max={32767}
                                className="mt-2 min-h-11"
                                value={form.data.daily_queue_capacity}
                                onChange={(event) =>
                                    form.setData(
                                        'daily_queue_capacity',
                                        Number(event.target.value),
                                    )
                                }
                                aria-describedby="daily_queue_capacity-error"
                                required
                            />
                            {fieldError('daily_queue_capacity')}
                        </div>
                        <div>
                            <Label htmlFor="grace_minutes">
                                Toleransi check-in (menit)
                            </Label>
                            <Input
                                id="grace_minutes"
                                type="number"
                                min={1}
                                max={120}
                                className="mt-2 min-h-11"
                                value={form.data.grace_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'grace_minutes',
                                        Number(event.target.value),
                                    )
                                }
                                aria-describedby="grace_minutes-error"
                                required
                            />
                            {fieldError('grace_minutes')}
                        </div>
                    </div>
                    <p className="mt-5 text-sm leading-6 text-muted-foreground">
                        Zona waktu usaha: <strong>{timezone}</strong>. Toleransi
                        mulai saat nomor belum hadir dilewati pada panggilan
                        berikutnya, bukan saat estimasi habis. Kapasitas dapat
                        diatur khusus pada profil barber.
                    </p>
                </section>
                <Button
                    className="min-h-12 w-full sm:w-auto"
                    disabled={form.processing}
                >
                    <Save className="size-4" aria-hidden="true" />
                    {form.processing ? 'Menyimpan…' : 'Simpan pengaturan'}
                </Button>
            </form>
        </StaffPage>
    );
}
