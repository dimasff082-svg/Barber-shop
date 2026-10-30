import { Link, router } from '@inertiajs/react';
import { LoaderCircle, Search } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import InputError from '@/components/input-error';
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

export type CatalogPage<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

export function CatalogErrorSummary({ errors }: { errors: object }) {
    const messages = [
        ...new Set(
            Object.values(errors).filter(
                (message): message is string => typeof message === 'string',
            ),
        ),
    ];
    if (messages.length === 0) return null;
    return (
        <div
            role="alert"
            className="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
        >
            <p className="font-medium">Periksa kembali data formulir.</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {messages.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        </div>
    );
}

export function CatalogField({
    id,
    label,
    error,
    children,
    description,
}: {
    id: string;
    label: string;
    error?: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {description && (
                <p
                    id={`${id}-help`}
                    className="text-xs leading-5 text-muted-foreground"
                >
                    {description}
                </p>
            )}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

export function CatalogToggle({
    id,
    label,
    checked,
    onChange,
    description,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
    description?: string;
}) {
    return (
        <label
            htmlFor={id}
            className="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3"
        >
            <input
                id={id}
                type="checkbox"
                className="mt-0.5 size-5 shrink-0 accent-primary"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
            />
            <span className="min-w-0 text-sm">
                <span className="block font-medium">{label}</span>
                {description && (
                    <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                        {description}
                    </span>
                )}
            </span>
        </label>
    );
}

export function CatalogSearch({
    search,
    url,
}: {
    search: string;
    url: string;
}) {
    const [value, setValue] = useState(search);
    return (
        <form
            className="flex min-w-0 gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                router.get(
                    url,
                    { search: value },
                    { preserveState: true, replace: true },
                );
            }}
        >
            <Label className="sr-only" htmlFor="catalog-search">
                Cari data
            </Label>
            <Input
                id="catalog-search"
                className="min-h-11 max-w-sm"
                value={value}
                onChange={(event) => setValue(event.target.value)}
                placeholder="Cari berdasarkan nama"
                maxLength={100}
            />
            <Button type="submit" variant="outline" className="min-h-11">
                <Search aria-hidden="true" className="size-4" />
                <span className="sr-only sm:not-sr-only">Cari</span>
            </Button>
        </form>
    );
}

export function CatalogPagination({ page }: { page: CatalogPage<unknown> }) {
    return (
        <div className="mt-6 flex flex-wrap items-center justify-between gap-4 border-t pt-5">
            <p className="text-sm text-muted-foreground">
                {page.total} data tersimpan
            </p>
            <nav aria-label="Halaman data" className="flex flex-wrap gap-1">
                {page.links.map((link, index) => (
                    <Button
                        key={index}
                        asChild={Boolean(link.url)}
                        disabled={!link.url}
                        variant={link.active ? 'default' : 'outline'}
                        className="min-h-11 min-w-11"
                    >
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                aria-current={link.active ? 'page' : undefined}
                            >
                                {link.label
                                    .replace(/&laquo;/g, '«')
                                    .replace(/&raquo;/g, '»')
                                    .replace(/Previous/g, 'Sebelumnya')
                                    .replace(/Next/g, 'Berikutnya')}
                            </Link>
                        ) : (
                            <span>
                                {link.label
                                    .replace(/&laquo;/g, '«')
                                    .replace(/&raquo;/g, '»')
                                    .replace(/Previous/g, 'Sebelumnya')
                                    .replace(/Next/g, 'Berikutnya')}
                            </span>
                        )}
                    </Button>
                ))}
            </nav>
        </div>
    );
}

export function CatalogArchive({
    target,
    onClose,
}: {
    target: { name: string; url: string } | null;
    onClose: () => void;
}) {
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    return (
        <Dialog
            open={target !== null}
            onOpenChange={(open) => {
                if (!open && !pending) {
                    setError(null);
                    onClose();
                }
            }}
        >
            <DialogContent
                className="staff-theme max-h-[90dvh] overflow-y-auto"
                closeLabel="Tutup konfirmasi"
            >
                <DialogHeader>
                    <DialogTitle>Nonaktifkan {target?.name}?</DialogTitle>
                    <DialogDescription>
                        Data dan riwayat tetap disimpan. Data ini tidak lagi
                        tersedia untuk pendaftaran atau tampilan publik. Tiket
                        yang sudah diterbitkan perlu ditangani secara terpisah.
                    </DialogDescription>
                </DialogHeader>
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={pending}
                        className="min-h-11"
                        onClick={onClose}
                    >
                        Kembali
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={pending}
                        className="min-h-11"
                        onClick={() => {
                            if (!target) return;
                            setPending(true);
                            setError(null);
                            router.post(
                                target.url,
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: onClose,
                                    onError: (errors) =>
                                        setError(
                                            Object.values(errors)[0] ||
                                                'Data belum dapat dinonaktifkan.',
                                        ),
                                    onFinish: () => setPending(false),
                                },
                            );
                        }}
                    >
                        {pending && (
                            <LoaderCircle
                                aria-hidden="true"
                                className="size-4 animate-spin"
                            />
                        )}
                        Nonaktifkan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
