import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, Plus, X } from 'lucide-react';
import { useBranding } from '@/hooks/useBranding';

function checkIsActive(item) {
    if (typeof item.active === 'function') {
        try { return item.active(); } catch { return false; }
    }
    if (item.active !== undefined) return Boolean(item.active);
    try {
        if (typeof route === 'function') {
            if (item.route && route().current(item.route)) return true;
            if (item.activePattern) {
                if (Array.isArray(item.activePattern)) {
                    return item.activePattern.some(p => route().current(p));
                }
                return route().current(item.activePattern);
            }
        }
    } catch {
        return false;
    }
    return false;
}

function NavGroup({ label, items, onClose }) {
    const [open, setOpen] = useState(true);

    return (
        <div className="mb-0.5">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
                aria-controls={`nav-group-${label.replace(/\s+/g, '-').toLowerCase()}`}
                className="flex w-full items-center justify-between px-3 py-1.5 mt-3 text-[10px] font-bold uppercase tracking-widest text-white/70 hover:text-white transition-colors duration-150 select-none"
            >
                <span>{label}</span>
                <ChevronDown
                    className={[
                        'h-3 w-3 transition-transform duration-200',
                        open ? 'rotate-0' : '-rotate-90',
                    ].join(' ')}
                />
            </button>

            {open && (
                <div id={`nav-group-${label.replace(/\s+/g, '-').toLowerCase()}`} className="mt-0.5 space-y-0.5">
                    {items.map((item, i) => {
                        const isActive = checkIsActive(item);
                        return (
                            <Link
                                key={item.key ?? item.route ?? item.href ?? i}
                                href={item.href ?? (item.route ? route(item.route) : '#')}
                                onClick={onClose}
                                data-tour={item.dataTour}
                                className={[
                                    'group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150',
                                    isActive
                                        ? 'bg-emerald-600 text-white shadow-sm font-semibold'
                                        : 'text-white/80 hover:bg-white/10 hover:text-white',
                                ].join(' ')}
                            >
                                {item.icon && (
                                    <span className={[
                                        'shrink-0 transition-colors duration-150',
                                        isActive ? 'text-white' : 'text-white/65 group-hover:text-white',
                                    ].join(' ')}>
                                        {item.icon}
                                    </span>
                                )}
                                <span className="truncate">{item.label}</span>
                                {item.badge ? (
                                    <span className="ml-auto text-[11px] font-medium text-amber-300/90 bg-amber-500/15 px-1.5 py-0.5 rounded border border-amber-500/20 shrink-0">
                                        {item.badge}
                                    </span>
                                ) : isActive ? (
                                    <span className="ml-auto h-1.5 w-1.5 rounded-full bg-white/70 shrink-0" />
                                ) : null}
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default function Sidebar({
    navItems = [],
    navGroups = [],
    open = false,
    onClose,
    footer,
    title,
    logo,
    showCreateButton = true,
}) {
    const { t } = useTranslation();
    const { appName, logoUrl } = useBranding();

    const content = (
        <aside className="flex h-full w-64 flex-col bg-[#06281e] dark:bg-[#031c15] border-r border-[#0a382c] dark:border-[#07241c] text-white">
            {/* Brand header */}
            <div className="flex h-16 shrink-0 items-center gap-2.5 px-4 border-b border-white/10">
                {logo ? (
                    logo
                ) : (
                    <Link href={route('client.dashboard')} className="flex items-center gap-2.5 group transition-transform duration-150 hover:scale-[1.01]">
                        <img
                            src={logoUrl || '/images/brand/logo-full.png'}
                            alt={appName || 'Growbridge Connect'}
                            className="h-8 max-w-[180px] object-contain drop-shadow-sm"
                        />
                    </Link>
                )}
            </div>

            {showCreateButton && (
                <div className="shrink-0 p-3 pb-2 border-b border-white/10">
                    <button
                        type="button"
                        className="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition duration-150"
                    >
                        <Plus className="h-4 w-4" />
                        {t('common.create')}
                    </button>
                </div>
            )}

            <nav className="flex-1 overflow-y-auto px-2 py-3 space-y-0.5 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-white/10">
                {/* Standalone Nav Items (e.g. Dashboard, Clients) */}
                {navItems.length > 0 &&
                    navItems.map((item, i) => {
                        if (item.type === 'divider') {
                            return <hr key={`div-${i}`} className="my-2 border-white/10" />;
                        }
                        const isActive = checkIsActive(item);
                        return (
                            <Link
                                key={item.key ?? item.route ?? item.href ?? i}
                                href={item.href ?? (item.route ? route(item.route) : '#')}
                                onClick={onClose}
                                data-tour={item.dataTour}
                                className={[
                                    'group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150',
                                    isActive
                                        ? 'bg-emerald-600 text-white shadow-sm font-semibold'
                                        : 'text-white/80 hover:bg-white/10 hover:text-white',
                                ].join(' ')}
                            >
                                {item.icon && (
                                    <span className={[
                                        'shrink-0 transition-colors duration-150',
                                        isActive ? 'text-white' : 'text-white/65 group-hover:text-white',
                                    ].join(' ')}>
                                        {item.icon}
                                    </span>
                                )}
                                <span className="truncate">{item.label}</span>
                                {item.badge ? (
                                    <span className="ml-auto text-[11px] font-medium text-amber-300/90 bg-amber-500/15 px-1.5 py-0.5 rounded border border-amber-500/20 shrink-0">
                                        {item.badge}
                                    </span>
                                ) : isActive ? (
                                    <span className="ml-auto h-1.5 w-1.5 rounded-full bg-white/70 shrink-0" />
                                ) : null}
                            </Link>
                        );
                    })}

                {/* Collapsible Nav Groups */}
                {navGroups.length > 0 &&
                    navGroups.map((group, gi) => (
                        <NavGroup
                            key={`${gi}-${group.key ?? group.label ?? ''}`}
                            label={group.label}
                            items={group.items ?? []}
                            onClose={onClose}
                        />
                    ))}
            </nav>

            {footer && (
                <div className="shrink-0 border-t border-white/8 p-3">
                    <div className="text-white/55">
                        {footer}
                    </div>
                </div>
            )}
        </aside>
    );

    return (
        <>
            {/* Desktop: always visible */}
            <div className="hidden lg:fixed lg:inset-y-0 lg:z-20 lg:flex lg:w-64 lg:flex-col lg:left-0 rtl:lg:left-auto rtl:lg:right-0">
                {content}
            </div>

            {/* Mobile: overlay + drawer */}
            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />
                    <div className="fixed inset-y-0 left-0 w-64 shadow-2xl rtl:left-auto rtl:right-0">
                        <button
                            type="button"
                            onClick={onClose}
                            className="absolute top-3 right-3 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-white/10 text-white/70 hover:bg-white/20 transition"
                            aria-label={t('ui.close_menu')}
                        >
                            <X className="h-4 w-4" />
                        </button>
                        {content}
                    </div>
                </div>
            )}
        </>
    );
}
