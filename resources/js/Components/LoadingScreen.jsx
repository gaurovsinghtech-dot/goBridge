import { useEffect, useRef, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { subscribeLoading } from '@/lib/navigationLoading';

/**
 * Full-screen overlay shown for the duration of an Inertia page navigation.
 * Mounted once at the app root (see app.jsx) so it works across every page
 * without each one wiring up its own transition state.
 */
export default function LoadingScreen() {
    const [visible, setVisible] = useState(false);
    const showTimer = useRef(null);

    useEffect(() => {
        return subscribeLoading((active) => {
            clearTimeout(showTimer.current);
            if (active) {
                // Delay showing briefly so fast navigations don't flash the overlay.
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
            className="fixed inset-0 z-[9999] flex items-center justify-center bg-white/70 dark:bg-neutral-950/70 backdrop-blur-sm"
        >
            <div className="flex flex-col items-center gap-3">
                <Loader2 className="h-9 w-9 animate-spin text-brand-600 dark:text-brand-400" />
                <span className="text-xs font-medium text-neutral-500 dark:text-neutral-400">Loading…</span>
            </div>
        </div>
    );
}
