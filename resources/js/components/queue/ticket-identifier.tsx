import { Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function TicketIdentifier({ id }: { id: string }) {
    const [message, setMessage] = useState<string | null>(null);
    return (
        <div className="mx-5 border-t border-border py-5 sm:mx-8">
            <Label htmlFor={`ticket-id-${id}`}>ID tiket</Label>
            <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                <Input
                    id={`ticket-id-${id}`}
                    value={id}
                    readOnly
                    onFocus={(event) => event.currentTarget.select()}
                    className="min-h-12 min-w-0 flex-1 font-mono text-sm"
                    aria-describedby={`ticket-id-help-${id}`}
                />
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-12"
                    onClick={() => {
                        void navigator.clipboard?.writeText(id).then(
                            () => setMessage('ID tiket disalin.'),
                            () =>
                                setMessage(
                                    'Pilih ID tiket lalu salin secara manual.',
                                ),
                        );
                        if (!navigator.clipboard)
                            setMessage(
                                'Pilih ID tiket lalu salin secara manual.',
                            );
                    }}
                >
                    <Copy className="size-4" aria-hidden="true" />
                    Salin ID tiket
                </Button>
            </div>
            <p
                id={`ticket-id-help-${id}`}
                className="mt-2 text-xs leading-5 text-muted-foreground"
            >
                Gunakan ID ini pada portal QR lokasi untuk memilih tiket Anda.
                Buka dengan browser saat mendaftar atau pulihkan akses tiket
                terlebih dahulu.
            </p>
            {message && (
                <p role="status" className="mt-2 text-xs text-primary">
                    {message}
                </p>
            )}
        </div>
    );
}
