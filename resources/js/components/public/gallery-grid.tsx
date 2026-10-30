import { ZoomIn } from 'lucide-react';
import { ContentImage } from '@/components/public/content-image';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export function GalleryGrid({
    images = [],
    illustrative = false,
}: {
    images?: { title: string; src: string; alt: string }[];
    illustrative?: boolean;
}) {
    return (
        <div className="grid gap-4 min-[375px]:grid-cols-2 sm:gap-6 lg:grid-cols-3">
            {images.map((photo) => (
                <Dialog key={photo.src}>
                    <figure className="min-w-0">
                        <DialogTrigger asChild>
                            <button
                                type="button"
                                className="group relative block aspect-[4/5] w-full overflow-hidden rounded-xl border border-border bg-card text-left outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:ring-offset-background"
                                aria-label={`Perbesar foto gaya ${photo.title}`}
                            >
                                <ContentImage
                                    src={photo.src}
                                    alt={photo.alt}
                                    width={800}
                                    height={1000}
                                    className="transition-transform duration-200 motion-safe:group-hover:scale-105 motion-reduce:transition-none"
                                />
                                <span className="absolute right-4 bottom-4 flex size-11 items-center justify-center rounded-lg border border-border bg-background/90 text-foreground transition-colors group-hover:text-primary">
                                    <ZoomIn
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                            </button>
                        </DialogTrigger>
                        <figcaption className="pt-4">
                            <h3 className="font-display text-2xl uppercase">
                                {photo.title}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {illustrative
                                    ? 'Inspirasi gaya · foto ilustrasi'
                                    : 'Galeri studio'}
                            </p>
                        </figcaption>
                    </figure>
                    <DialogContent
                        lang="id"
                        closeLabel="Tutup foto"
                        className="public-theme max-h-[90dvh] overflow-y-auto border-border bg-background text-foreground sm:max-w-2xl [&>button]:top-3 [&>button]:right-3 [&>button]:size-11 [&>button]:rounded-lg [&>button]:opacity-100"
                    >
                        <DialogHeader className="pr-12 text-left">
                            <DialogTitle className="font-display text-2xl uppercase">
                                Inspirasi {photo.title}
                            </DialogTitle>
                            <DialogDescription>
                                {illustrative
                                    ? 'Inspirasi gaya · foto ilustrasi'
                                    : 'Galeri studio'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="aspect-[4/5] max-h-[60dvh] overflow-hidden rounded-lg bg-card">
                            <ContentImage
                                src={photo.src}
                                alt={photo.alt}
                                width={800}
                                height={1000}
                                className="object-contain"
                            />
                        </div>
                        <div className="flex justify-end">
                            <DialogClose asChild>
                                <Button
                                    variant="outline"
                                    className="min-h-11 border-border"
                                >
                                    Tutup foto
                                </Button>
                            </DialogClose>
                        </div>
                    </DialogContent>
                </Dialog>
            ))}
        </div>
    );
}
