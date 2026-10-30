import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const steps = ['Layanan', 'Barber', 'Data diri', 'Konfirmasi'];

export function QueueStepIndicator({ current }: { current: number }) {
    return (
        <ol
            aria-label="Langkah pengambilan antrean"
            className="mb-8 grid grid-cols-4 gap-2 sm:gap-3"
        >
            {steps.map((label, index) => (
                <li
                    key={label}
                    aria-current={current === index ? 'step' : undefined}
                    className="min-w-0"
                >
                    <div
                        className={cn(
                            'mb-3 h-1.5 rounded-full',
                            index <= current
                                ? 'bg-primary'
                                : 'bg-surface-raised',
                        )}
                    />
                    <span
                        className={cn(
                            'flex items-center gap-1 text-[11px] sm:text-sm',
                            index === current
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground',
                        )}
                    >
                        {index < current && (
                            <Check
                                className="hidden size-3 shrink-0 sm:inline"
                                aria-hidden="true"
                            />
                        )}
                        <span>{label}</span>
                    </span>
                </li>
            ))}
        </ol>
    );
}
