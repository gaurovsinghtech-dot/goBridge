import { useEffect, useRef, useState } from 'react';
import { subscribeLoading } from '@/lib/navigationLoading';

export default function LoadingScreen() {
    const [visible, setVisible] = useState(false);
    const showTimer = useRef(null);

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
                        src="/images/brand/logo-full.png"
                        alt="Loading"
                        className="h-12 w-auto max-w-[220px] object-contain drop-shadow-md dark:[filter:invert(1)_hue-rotate(180deg)_brightness(1.15)_saturate(1.5)]"
                    />
                </div>
            </div>
        </div>
    );
}
