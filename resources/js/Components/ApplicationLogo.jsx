import { usePage } from '@inertiajs/react';

export default function ApplicationLogo({ className = 'h-9 w-auto', style, alt, variant = 'icon' }) {
    let customLogoUrl = null;
    try {
        // eslint-disable-next-line react-hooks/rules-of-hooks
        customLogoUrl = usePage().props.branding?.logo_url ?? null;
    } catch {
        customLogoUrl = null;
    }

    if (customLogoUrl) {
        return (
            <img
                src={customLogoUrl}
                alt={alt ?? 'Growbridge Connect'}
                className={`object-contain transition-transform duration-200 ${className}`}
                style={style}
                loading="eager"
            />
        );
    }

    if (variant === 'full') {
        return (
            <>
                <img
                    src="/images/brand/logo-full.png"
                    alt={alt ?? 'Growbridge Connect'}
                    className={`object-contain transition-transform duration-200 dark:hidden ${className}`}
                    style={style}
                    loading="eager"
                />
                <img
                    src="/images/brand/logo-full-dark.png"
                    alt={alt ?? 'Growbridge Connect'}
                    className={`object-contain transition-transform duration-200 hidden dark:block ${className}`}
                    style={style}
                    loading="eager"
                />
            </>
        );
    }

    return (
        <img
            src="/images/brand/logo-icon.png"
            alt={alt ?? 'Growbridge Connect'}
            className={`object-contain transition-transform duration-200 ${className}`}
            style={style}
            loading="eager"
        />
    );
}
