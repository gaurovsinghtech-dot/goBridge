import { usePage } from '@inertiajs/react';

/**
 * Official Growbridge Connect Application Logo Component
 * Uses the uploaded official brand assets:
 * - Icon: /images/brand/logo-icon.png (emerald/teal "G" leaf icon)
 * - Full Wordmark: /images/brand/logo-full.png
 */
export default function ApplicationLogo({ className = 'h-9 w-auto', style, alt, variant = 'icon' }) {
    let customLogoUrl = null;
    try {
        // eslint-disable-next-line react-hooks/rules-of-hooks
        customLogoUrl = usePage().props.branding?.logo_url ?? null;
    } catch {
        customLogoUrl = null;
    }

    const defaultSrc = variant === 'full' 
        ? '/images/brand/logo-full.png' 
        : '/images/brand/logo-icon.png';

    const src = customLogoUrl || defaultSrc;

    return (
        <img
            src={src}
            alt={alt ?? 'Growbridge Connect'}
            className={`object-contain transition-transform duration-200 ${className}`}
            style={style}
            loading="eager"
        />
    );
}
