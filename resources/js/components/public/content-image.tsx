import { ImageOff } from 'lucide-react';
import { useState, type ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type ContentImageProps = Omit<ComponentProps<'img'>, 'src' | 'alt'> & {
    src: string;
    alt: string;
};

export function ContentImage({
    src,
    alt,
    className,
    loading = 'lazy',
    decoding = 'async',
    onError,
    ...props
}: ContentImageProps) {
    const [failedSource, setFailedSource] = useState<string | null>(null);

    if (failedSource === src) {
        return (
            <div
                role="img"
                aria-label={`Foto belum tersedia: ${alt}`}
                className="flex h-full w-full flex-col items-center justify-center gap-3 bg-muted p-6 text-center text-muted-foreground"
            >
                <ImageOff className="size-6" aria-hidden="true" />
                <span className="text-sm font-medium">Foto belum tersedia</span>
                <span className="max-w-64 text-xs leading-relaxed">{alt}</span>
            </div>
        );
    }

    return (
        <img
            {...props}
            src={src}
            alt={alt}
            loading={loading}
            decoding={decoding}
            className={cn('h-full w-full object-cover', className)}
            onError={(event) => {
                setFailedSource(src);
                onError?.(event);
            }}
        />
    );
}
