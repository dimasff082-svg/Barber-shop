import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { complete, start, missed, cancel } from '@/routes/queue/staff';
import type { StaffTicket } from '@/types/operations';

const labels = {
    start: 'Mulai Layanan',
    complete: 'Selesaikan Layanan',
    missed: 'Tandai Terlewat',
    cancel: 'Batalkan Antrean',
};
type Action = keyof typeof labels;

export function TicketActions({ ticket }: { ticket: StaffTicket }) {
    const [action, setAction] = useState<Action | null>(null);
    const form = useForm({ reason: '' });
    const errors: Record<string, string | undefined> = form.errors;
    const endpoints = { start, complete, missed, cancel };
    function submit() {
        if (!action || form.processing) return;
        form.post(endpoints[action].url(ticket.id), {
            preserveScroll: true,
            onSuccess: () => {
                setAction(null);
                form.reset();
            },
        });
    }
    return (
        <>
            <div className="flex flex-wrap gap-2">
                {(Object.keys(labels) as Action[])
                    .filter((key) => ticket.actions[key])
                    .map((key) => (
                        <Button
                            key={key}
                            type="button"
                            variant={
                                key === 'cancel' ? 'destructive' : 'outline'
                            }
                            className="min-h-11 whitespace-normal"
                            disabled={form.processing}
                            onClick={() => {
                                form.clearErrors();
                                setAction(key);
                            }}
                        >
                            {labels[key]}
                        </Button>
                    ))}
            </div>
            <Dialog
                open={action !== null}
                onOpenChange={(open) => {
                    if (!open && !form.processing) setAction(null);
                }}
            >
                <DialogContent
                    className="staff-theme"
                    closeLabel="Tutup konfirmasi"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {action ? labels[action] : 'Konfirmasi'}
                        </DialogTitle>
                        <DialogDescription>
                            Konfirmasi perubahan untuk {ticket.queue_number}.
                            Status dan kelayakan diperiksa kembali oleh server.
                        </DialogDescription>
                    </DialogHeader>
                    {action === 'cancel' && (
                        <div className="space-y-2">
                            <Label htmlFor={`reason-${ticket.id}`}>
                                Alasan pembatalan
                            </Label>
                            <Input
                                id={`reason-${ticket.id}`}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                maxLength={500}
                                disabled={form.processing}
                                className="min-h-11"
                            />
                            <p className="text-sm text-destructive">
                                {errors.reason}
                            </p>
                        </div>
                    )}
                    {(errors.queue || errors.availability) && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.queue || errors.availability}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            disabled={form.processing}
                            onClick={() => setAction(null)}
                        >
                            Kembali
                        </Button>
                        <Button
                            type="button"
                            variant={
                                action === 'cancel' ? 'destructive' : 'default'
                            }
                            className="min-h-11"
                            disabled={
                                form.processing ||
                                (action === 'cancel' &&
                                    !form.data.reason.trim())
                            }
                            onClick={submit}
                        >
                            {form.processing ? 'Memproses…' : 'Ya, Konfirmasi'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
