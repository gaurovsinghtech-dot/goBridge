import { usePage } from '@inertiajs/react';

export default function ApplicationLogo({ className = 'h-9 w-auto', style, alt, variant = 'icon' }) {
    let customLogoUrl = null;
    try {
        // eslint-disable-next-line react-hooks/rules-of-hooks
        customLogoUrl = usePage().props.branding?.logo_url ?? null;
    } catch {
        customLogoUrl = null;
    }

    const src = customLogoUrl || (variant === 'full' ? '/images/brand/logo-full.png' : '/images/brand/logo-icon.png');

    return (
        <img
            src={src}
            alt={alt ?? 'Growbridge Connect'}
            className={`object-contain transition-transform duration-200 drop-shadow-sm dark:[filter:invert(1)_hue-rotate(180deg)_brightness(1.15)_saturate(1.5)] ${className}`}
            style={style}
            loading="eager"
        />
    );
}
