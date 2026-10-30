import { LoaderCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export function ConfirmActionDialog({
    open,
    onOpenChange,
    onConfirm,
    pending,
    disabled,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    pending: boolean;
    disabled?: boolean;
}) {
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!pending) onOpenChange(value);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    disabled={disabled}
                    className="min-h-12 w-full border-danger/40 text-danger hover:bg-danger/10 hover:text-danger"
                >
                    Batalkan Antrean
                </Button>
            </DialogTrigger>
            <DialogContent
                closeLabel="Tutup konfirmasi"
                className="public-theme border-border bg-background text-foreground [&>button]:size-11 [&>button]:translate-x-2 [&>button]:-translate-y-2"
            >
                <DialogHeader className="pr-7 text-left">
                    <DialogTitle>Batalkan antrean ini?</DialogTitle>
                    <DialogDescription>
                        Nomor antrean ini tidak dapat dipakai kembali setelah
                        dibatalkan. Jika berubah pikiran, Anda perlu mengambil
                        antrean baru sesuai ketersediaan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={pending}
                        onClick={() => onOpenChange(false)}
                        className="min-h-11"
                    >
                        Tetap Menunggu
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={pending}
                        onClick={onConfirm}
                        className="min-h-11"
                    >
                        {pending && (
                            <LoaderCircle
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {pending ? 'Membatalkan…' : 'Ya, Batalkan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
