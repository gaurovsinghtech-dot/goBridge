import { useEffect, useRef, useState } from 'react';
import { subscribeLoading } from '@/lib/navigationLoading';
import { useBranding } from '@/hooks/useBranding';

export default function LoadingScreen() {
    const [visible, setVisible] = useState(false);
    const showTimer = useRef(null);
    const { logoUrl } = useBranding();

    useEffect(() => {
        return subscribeLoading((active) => {
            clearTimeout(showTimer.current);
            if (active) {
                showTimer.current = setTimeout(() => setVisible(true), 150);
            } else {
                setVisible(false);
            }
        });
    }, []);

    if (!visible) return null;

    return (
        <div
            role="status"
            aria-live="polite"
            aria-label="Loading"
            className="fixed inset-0 z-[9999] flex items-center justify-center bg-white/80 dark:bg-neutral-950/80 backdrop-blur-sm"
        >
            <div className="flex flex-col items-center gap-4">
                <div className="animate-pulse-logo">
                    <img
                        src={logoUrl || '/images/brand/logo-full.png'}
                        alt="Loading"
                        className="h-12 w-auto max-w-[220px] object-contain drop-shadow-md dark:[filter:invert(1)_hue-rotate(180deg)_brightness(1.15)_saturate(1.5)]"
                    />
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="h-1.5 w-1.5 rounded-full bg-orange-500 animate-bounce [animation-delay:0ms]" />
                    <span className="h-1.5 w-1.5 rounded-full bg-orange-500 animate-bounce [animation-delay:150ms]" />
                    <span className="h-1.5 w-1.5 rounded-full bg-orange-500 animate-bounce [animation-delay:300ms]" />
                </div>
            </div>
        </div>
    );
}
