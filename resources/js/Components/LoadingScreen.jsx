import { useEffect, useRef, useState } from 'react';
import { subscribeLoading } from '@/lib/navigationLoading';

const FADE_MS = 200;

export default function LoadingScreen() {
    const [rendered, setRendered] = useState(false);
    const [shown, setShown] = useState(false);
    const showTimer = useRef(null);
    const hideTimer = useRef(null);

    useEffect(() => {
        return subscribeLoading((active) => {
            clearTimeout(showTimer.current);
            clearTimeout(hideTimer.current);
            if (active) {
                showTimer.current = setTimeout(() => {
                    setRendered(true);
                    // Mount invisible first, then flip opacity on the next frame
                    // so the browser actually animates the transition instead of
                    // snapping straight to visible.
                    requestAnimationFrame(() => requestAnimationFrame(() => setShown(true)));
                }, 150);
            } else {
                setShown(false);
                hideTimer.current = setTimeout(() => setRendered(false), FADE_MS);
            }
        });
    }, []);

    if (!rendered) return null;

    return (
        <div
            role="status"
            aria-live="polite"
            aria-label="Loading"
            className={`fixed inset-0 z-[9999] flex items-center justify-center bg-white/80 dark:bg-neutral-950/80 backdrop-blur-sm transition-opacity duration-200 ease-in-out ${shown ? 'opacity-100' : 'opacity-0'}`}
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
