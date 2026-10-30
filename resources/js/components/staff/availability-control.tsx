import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { availability } from '@/routes/queue/staff';
import type { StaffBarber } from '@/types/operations';

export function AvailabilityControl({
    barber,
    processing,
}: {
    barber: StaffBarber;
    processing: boolean;
}) {
    const form = useForm({ availability: barber.availability });
    return (
        <form
            data-realtime-pause={
                form.isDirty || form.processing ? 'true' : undefined
            }
            className="rounded-xl border bg-card p-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(availability.url(barber.id), {
                    preserveScroll: true,
                });
            }}
        >
            <Label htmlFor={`barber-availability-${barber.id}`}>
                Ketersediaan kerja
            </Label>
            <div className="mt-3 flex flex-col gap-3 sm:flex-row">
                <select
                    id={`barber-availability-${barber.id}`}
                    className="min-h-11 min-w-0 flex-1 rounded-lg border bg-background px-3 text-sm"
                    value={form.data.availability}
                    onChange={(event) =>
                        form.setData(
                            'availability',
                            event.target.value as StaffBarber['availability'],
                        )
                    }
                    disabled={processing || form.processing}
                >
                    <option value="available">Tersedia</option>
                    <option value="busy" disabled>
                        Sedang melayani
                    </option>
                    <option value="break">Istirahat</option>
                    <option value="off_duty">Tidak bertugas</option>
                </select>
                <Button
                    className="min-h-11"
                    disabled={
                        processing ||
                        form.processing ||
                        form.data.availability === barber.availability
                    }
                >
                    {form.processing ? 'Menyimpan…' : 'Simpan Status'}
                </Button>
            </div>
            <p className="mt-3 text-xs leading-5 text-muted-foreground">
                {processing
                    ? 'Selesaikan antrean yang sedang diproses sebelum mengganti ketersediaan.'
                    : 'Status sibuk mengikuti layanan yang sedang berlangsung.'}
            </p>
            <p role="alert" className="mt-2 text-sm text-destructive">
                {form.errors.availability}
            </p>
        </form>
    );
}
