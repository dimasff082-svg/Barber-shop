import { LoaderCircle } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';

export function LoadingButton({
    loading,
    children,
    disabled,
    ...props
}: ComponentProps<typeof Button> & { loading?: boolean }) {
    return (
        <Button
            {...props}
            disabled={loading || disabled}
            aria-busy={loading || undefined}
        >
            {loading && (
                <LoaderCircle
                    aria-hidden="true"
                    className="size-4 animate-spin motion-reduce:animate-none"
                />
            )}
            {children}
        </Button>
    );
}
