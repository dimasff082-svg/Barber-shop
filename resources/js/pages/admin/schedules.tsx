import { router, useForm } from '@inertiajs/react';
import { CalendarDays, Plus, Save, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { weekdays } from '@/components/public/business-hours';
import { Pagination } from '@/components/staff/pagination';
import { StaffPage } from '@/components/staff/staff-page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    barber as updateBarber,
    hours as updateHours,
} from '@/routes/admin/schedules';
import { destroy, store } from '@/routes/admin/schedules/closures';

type Hour = {
    weekday: number;
    starts_at: string;
    ends_at: string;
    is_closed: boolean;
};
type Schedule = {
    weekday: number;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
};
type Barber = { id: number; name: string; schedules: Schedule[] };
type Closure = { id: number; date: string; reason: string };
type Page<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

type ScheduleRowsProps = { errors: Record<string, string | undefined> } & (
    | { kind: 'hours'; rows: Hour[]; onChange: (rows: Hour[]) => void }
    | {
          kind: 'schedules';
          rows: Schedule[];
          onChange: (rows: Schedule[]) => void;
      }
);

function ScheduleRows(props: ScheduleRowsProps) {
    const { rows, errors, kind } = props;
    const patch = (
        index: number,
        values: { starts_at?: string; ends_at?: string; enabled?: boolean },
    ) => {
        const { enabled, ...times } = values;
        if (props.kind === 'hours') {
            props.onChange(
                props.rows.map((row, position) =>
                    position === index
                        ? {
                              ...row,
                              ...times,
                              is_closed:
                                  enabled === undefined
                                      ? row.is_closed
                                      : !enabled,
                          }
                        : row,
                ),
            );
        } else {
            props.onChange(
                props.rows.map((row, position) =>
                    position === index
                        ? {
                              ...row,
                              ...times,
                              is_active:
                                  enabled === undefined
                                      ? row.is_active
                                      : enabled,
                          }
                        : row,
                ),
            );
        }
    };
    return (
        <div className="space-y-3">
            {rows.map((row, index) => (
                <div key={row.weekday} className="rounded-xl border p-4">
                    <div className="grid items-center gap-3 sm:grid-cols-[1fr_1fr_1fr]">
                        <label className="flex min-h-11 items-center gap-3 text-sm font-medium">
                            <input
                                type="checkbox"
                                className="size-5 accent-primary"
                                checked={
                                    'is_closed' in row
                                        ? !row.is_closed
                                        : row.is_active
                                }
                                onChange={(event) =>
                                    patch(index, {
                                        enabled: event.target.checked,
                                    })
                                }
                            />
                            {weekdays[row.weekday]} ·{' '}
                            {'is_closed' in row
                                ? row.is_closed
                                    ? 'Tutup'
                                    : 'Buka'
                                : row.is_active
                                  ? 'Bertugas'
                                  : 'Tidak bertugas'}
                        </label>
                        <div>
                            <Label
                                className="text-xs"
                                htmlFor={`${kind}-${row.weekday}-start`}
                            >
                                Mulai
                            </Label>
                            <Input
                                id={`${kind}-${row.weekday}-start`}
                                type="time"
                                className="mt-1 min-h-11"
                                value={row.starts_at}
                                disabled={'is_closed' in row && row.is_closed}
                                onChange={(event) =>
                                    patch(index, {
                                        starts_at: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div>
                            <Label
                                className="text-xs"
                                htmlFor={`${kind}-${row.weekday}-end`}
                            >
                                Selesai
                            </Label>
                            <Input
                                id={`${kind}-${row.weekday}-end`}
                                type="time"
                                className="mt-1 min-h-11"
                                value={row.ends_at}
                                disabled={'is_closed' in row && row.is_closed}
                                onChange={(event) =>
                                    patch(index, {
                                        ends_at: event.target.value,
                                    })
                                }
                            />
                        </div>
                    </div>
                    {Object.entries(errors)
                        .filter(([field]) =>
                            field.startsWith(`${kind}.${index}.`),
                        )
                        .map(([field, message]) => (
                            <p
                                key={field}
                                role="alert"
                                className="mt-2 text-sm text-destructive"
                            >
                                {message}
                            </p>
                        ))}
                </div>
            ))}
        </div>
    );
}

function BarberSchedules({ barber }: { barber: Barber }) {
    const form = useForm({ schedules: barber.schedules });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(updateBarber.url(barber.id), { preserveScroll: true });
    };
    return (
        <form
            onSubmit={submit}
            aria-busy={form.processing}
            className="mt-5 space-y-5"
        >
            <ScheduleRows
                rows={form.data.schedules}
                onChange={(rows) => form.setData('schedules', rows)}
                errors={form.errors}
                kind="schedules"
            />
            <Button
                className="min-h-11 w-full sm:w-auto"
                disabled={form.processing}
            >
                <Save className="size-4" aria-hidden="true" />
                {form.processing ? 'Menyimpan…' : 'Simpan jadwal barber'}
            </Button>
        </form>
    );
}

export default function Schedules({
    hours,
    barbers,
    closures,
    timezone,
}: {
    hours: Hour[];
    barbers: Barber[];
    closures: Page<Closure>;
    timezone: string;
}) {
    const hoursForm = useForm({ hours });
    const closureForm = useForm({ date: '', reason: '' });
    const [selected, setSelected] = useState(barbers[0]?.id ?? 0);
    const [removing, setRemoving] = useState<Closure | null>(null);
    const [busy, setBusy] = useState(false);
    const barber = barbers.find((item) => item.id === selected);
    const submitHours = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        hoursForm.put(updateHours.url(), { preserveScroll: true });
    };
    const addClosure = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        closureForm.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => closureForm.reset(),
        });
    };
    const removeClosure = () => {
        if (!removing) return;
        setBusy(true);
        router.delete(destroy.url(removing.id), {
            preserveScroll: true,
            onSuccess: () => setRemoving(null),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <StaffPage
            title="Jadwal & Jam Buka"
            description={`Atur kalender studio dan jadwal setiap barber dalam zona waktu ${timezone}. Jadwal berada pada hari yang sama, tanpa reservasi slot jam.`}
        >
            <div className="grid items-start gap-6 xl:grid-cols-2">
                <section className="min-w-0 rounded-2xl border bg-card p-5 sm:p-6">
                    <h2 className="text-lg font-semibold">
                        Jam operasional studio
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        Hari tanpa pengaturan dianggap tutup. Jam selesai harus
                        setelah jam mulai.
                    </p>
                    <form
                        onSubmit={submitHours}
                        className="mt-5 space-y-5"
                        aria-busy={hoursForm.processing}
                    >
                        <ScheduleRows
                            rows={hoursForm.data.hours}
                            onChange={(rows) =>
                                hoursForm.setData('hours', rows)
                            }
                            errors={hoursForm.errors}
                            kind="hours"
                        />
                        <Button
                            className="min-h-11 w-full sm:w-auto"
                            disabled={hoursForm.processing}
                        >
                            <Save className="size-4" aria-hidden="true" />
                            {hoursForm.processing
                                ? 'Menyimpan…'
                                : 'Simpan jam buka'}
                        </Button>
                    </form>
                </section>
                <section className="min-w-0 rounded-2xl border bg-card p-5 sm:p-6">
                    <h2 className="text-lg font-semibold">Jadwal barber</h2>
                    {barbers.length ? (
                        <>
                            <Label htmlFor="schedule-barber" className="mt-5">
                                Pilih barber aktif
                            </Label>
                            <select
                                id="schedule-barber"
                                className="mt-2 min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm"
                                value={selected}
                                onChange={(event) =>
                                    setSelected(Number(event.target.value))
                                }
                            >
                                {barbers.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                            {barber && (
                                <BarberSchedules
                                    key={barber.id}
                                    barber={barber}
                                />
                            )}
                        </>
                    ) : (
                        <p className="mt-4 text-sm leading-6 text-muted-foreground">
                            Belum ada barber aktif. Tambahkan atau aktifkan
                            barber melalui menu Barber terlebih dahulu.
                        </p>
                    )}
                </section>
            </div>
            <section className="mt-6 rounded-2xl border bg-card p-5 sm:p-6">
                <h2 className="flex items-center gap-2 text-lg font-semibold">
                    <CalendarDays
                        className="size-5 text-primary"
                        aria-hidden="true"
                    />
                    Hari libur studio
                </h2>
                <p className="mt-2 text-sm text-muted-foreground">
                    Tanggal libur menutup pendaftaran baru; tiket yang sudah ada
                    tidak otomatis dibatalkan.
                </p>
                <form
                    onSubmit={addClosure}
                    className="mt-5 grid gap-4 sm:grid-cols-[1fr_2fr_auto] sm:items-start"
                >
                    <div>
                        <Label htmlFor="closure-date">Tanggal</Label>
                        <Input
                            id="closure-date"
                            type="date"
                            className="mt-2 min-h-11"
                            value={closureForm.data.date}
                            onChange={(event) =>
                                closureForm.setData('date', event.target.value)
                            }
                            required
                        />
                        {closureForm.errors.date && (
                            <p
                                role="alert"
                                className="mt-2 text-sm text-destructive"
                            >
                                {closureForm.errors.date}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="closure-reason">Alasan libur</Label>
                        <Input
                            id="closure-reason"
                            className="mt-2 min-h-11"
                            value={closureForm.data.reason}
                            onChange={(event) =>
                                closureForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            maxLength={255}
                            required
                        />
                        {closureForm.errors.reason && (
                            <p
                                role="alert"
                                className="mt-2 text-sm text-destructive"
                            >
                                {closureForm.errors.reason}
                            </p>
                        )}
                    </div>
                    <Button
                        className="min-h-11 sm:mt-6"
                        disabled={closureForm.processing}
                    >
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah
                    </Button>
                </form>
                <div className="mt-6 divide-y">
                    {closures.data.length ? (
                        closures.data.map((item) => (
                            <div
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-3 py-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">{item.date}</p>
                                    <p className="mt-1 text-sm break-words text-muted-foreground">
                                        {item.reason}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => setRemoving(item)}
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Hapus libur
                                </Button>
                            </div>
                        ))
                    ) : (
                        <p className="py-5 text-sm text-muted-foreground">
                            Belum ada tanggal libur khusus.
                        </p>
                    )}
                </div>
                <div className="mt-4">
                    <Pagination links={closures.links} />
                </div>
            </section>
            <Dialog
                open={Boolean(removing)}
                onOpenChange={(open) => {
                    if (!busy && !open) setRemoving(null);
                }}
            >
                <DialogContent className="staff-theme">
                    <DialogHeader>
                        <DialogTitle>Hapus tanggal libur?</DialogTitle>
                        <DialogDescription>
                            Tanggal {removing?.date} akan kembali mengikuti jam
                            operasional biasa. Audit perubahan tetap tersimpan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Button
                            variant="outline"
                            className="min-h-11"
                            disabled={busy}
                            onClick={() => setRemoving(null)}
                        >
                            Tetap simpan
                        </Button>
                        <Button
                            variant="destructive"
                            className="min-h-11"
                            disabled={busy}
                            onClick={removeClosure}
                        >
                            {busy ? 'Menghapus…' : 'Hapus tanggal libur'}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </StaffPage>
    );
}
