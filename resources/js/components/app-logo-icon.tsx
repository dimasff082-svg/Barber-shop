import { Scissors } from 'lucide-react';
import type { SVGAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <Scissors
            {...props}
            aria-hidden="true"
            className={cn(props.className, 'fill-none')}
        />
    );
}
