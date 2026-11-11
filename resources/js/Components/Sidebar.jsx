import { Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, Plus, X } from 'lucide-react';
import { useBranding } from '@/hooks/useBranding';
import axios from 'axios';

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

function NavGroup({ type = 'flat', label, icon, items, onClose, expandedItemKey, expandedContent }) {
    const [hoverKey, setHoverKey] = useState(null);
    const hoverCloseTimer = useRef(null);
    const hasActiveChild = items.some(checkIsActive);
    const [open, setOpen] = useState(true);
    const [expandedCollapsed, setExpandedCollapsed] = useState(false);
    const [hoverCounts, setHoverCounts] = useState({});
    const fetchedCountsFor = useRef(new Set());

    const openHover = (itemKey, countsRoute) => {
        if (hoverCloseTimer.current) { clearTimeout(hoverCloseTimer.current); hoverCloseTimer.current = null; }
        setHoverKey(itemKey);
        if (countsRoute && !fetchedCountsFor.current.has(itemKey)) {
            fetchedCountsFor.current.add(itemKey);
            try {
                axios.get(route(countsRoute)).then((res) => {
                    setHoverCounts((prev) => ({ ...prev, [itemKey]: res.data }));
                }).catch(() => { fetchedCountsFor.current.delete(itemKey); });
            } catch { fetchedCountsFor.current.delete(itemKey); }
        }
    };

    const scheduleHoverClose = () => {
        hoverCloseTimer.current = setTimeout(() => setHoverKey(null), 150);
    };

    return (
        <div className="mb-1">
            {type === 'group' && (
                <button
                    type="button"
                    onClick={() => setOpen((o) => !o)}
                    className={[
                        'group flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 mb-0.5 text-[11px] font-semibold uppercase tracking-wider transition-colors duration-100',
                        hasActiveChild
                            ? 'text-neutral-800 dark:text-neutral-200'
                            : 'text-neutral-600 dark:text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200',
                    ].join(' ')}
                >
                    {icon && (
                        <span className="shrink-0 flex h-5 w-5 items-center justify-center text-neutral-500 dark:text-neutral-500">
                            {icon}
                        </span>
                    )}
                    <span className="truncate flex-1 text-left">{label}</span>
                    <ChevronDown className={`h-3.5 w-3.5 shrink-0 text-neutral-500 dark:text-neutral-600 transition-transform duration-150 ${open ? 'rotate-180' : '-rotate-90'}`} />
                </button>
            )}
            <div className={`grid transition-[grid-template-rows] duration-200 ease-in-out ${open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
            <div className="overflow-hidden">
            <div className={['space-y-0.5', type === 'group' ? 'ml-[23px] pl-3 border-l border-neutral-200/80 dark:border-neutral-800 py-0.5' : ''].join(' ')}>
                {items.filter(item => !item.locked).map((item, i) => {
                        const isActive = checkIsActive(item);
                        const itemKey = item.key ?? item.route ?? item.href ?? i;
                        const isExpanded = expandedContent && expandedItemKey && itemKey === expandedItemKey;
                        const hasChildren = item.children?.length > 0;
                        const isHoverOpen = hasChildren && !isExpanded && hoverKey === itemKey;
                        return (
                            <div
                                key={itemKey}
                                onMouseEnter={() => hasChildren && !isExpanded && openHover(itemKey, item.countsRoute)}
                                onMouseLeave={() => hasChildren && !isExpanded && scheduleHoverClose()}
                            >
                                <div className="flex items-center gap-0.5">
                                    <Link
                                        href={item.href ?? (item.route ? route(item.route) : '#')}
                                        onClick={onClose}
                                        data-tour={item.dataTour}
                                        className={[
                                            'group flex flex-1 min-w-0 items-center gap-2.5 rounded-full transition-colors duration-100',
                                            type === 'group' ? 'px-2.5 py-1.5 text-[12.5px]' : 'px-3 py-1.5 text-[13px]',
                                            isActive
                                                ? 'bg-neutral-900 text-white font-semibold dark:bg-white dark:text-neutral-900'
                                                : 'text-neutral-700 dark:text-neutral-400 font-normal hover:bg-neutral-900/5 dark:hover:bg-white/10 hover:text-neutral-900 dark:hover:text-neutral-100',
                                        ].join(' ')}
                                    >
                                        {item.icon && (
                                            <span className={[
                                                'shrink-0 transition-colors duration-100',
                                                isActive ? 'text-white dark:text-neutral-900' : 'text-neutral-500 dark:text-neutral-500 group-hover:text-neutral-700 dark:group-hover:text-neutral-300',
                                            ].join(' ')}>
                                                {item.icon}
                                            </span>
                                        )}
                                        <span className="truncate">{item.label}</span>
                                        {item.badge ? (
                                            <span className="ml-auto text-[11px] font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800 shrink-0">
                                                {item.badge}
                                            </span>
                                        ) : null}
                                        {hasChildren && !isExpanded && (
                                            <ChevronDown className={[
                                                'h-3.5 w-3.5 shrink-0 transition-transform duration-150',
                                                item.badge ? '' : 'ml-auto',
                                                isHoverOpen ? 'rotate-180' : '',
                                                isActive ? 'text-white/70 dark:text-neutral-900/60' : 'text-neutral-500 dark:text-neutral-500',
                                            ].join(' ')} />
                                        )}
                                    </Link>
                                    {isExpanded && (
                                        <button
                                            type="button"
                                            onClick={() => setExpandedCollapsed((c) => !c)}
                                            aria-label={expandedCollapsed ? 'Expand' : 'Collapse'}
                                            className="shrink-0 flex h-6 w-6 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-900/5 dark:hover:bg-white/10 hover:text-neutral-800 dark:hover:text-neutral-200 transition-colors duration-100"
                                        >
                                            <ChevronDown className={`h-3.5 w-3.5 transition-transform duration-150 ${expandedCollapsed ? '-rotate-90' : ''}`} />
                                        </button>
                                    )}
                                </div>
                                {isExpanded && (
                                    <div className={`grid transition-[grid-template-rows] duration-200 ease-in-out ${expandedCollapsed ? 'grid-rows-[0fr]' : 'grid-rows-[1fr]'}`}>
                                        <div className="overflow-hidden">
                                            <div className="ml-[22px] pl-3 border-l border-neutral-300/70 dark:border-neutral-700 my-0.5 space-y-px">
                                                {expandedContent}
                                            </div>
                                        </div>
                                    </div>
                                )}
                                {hasChildren && !isExpanded && (
                                    <div className={`grid transition-[grid-template-rows] duration-200 ease-in-out ${isHoverOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                                        <div className="overflow-hidden">
                                            <div className="ml-[22px] pl-3 border-l border-neutral-300/70 dark:border-neutral-700 my-0.5 space-y-px">
                                                {item.children.map((child, ci) => {
                                                    const count = child.countKey ? hoverCounts[itemKey]?.[child.countKey] : undefined;
                                                    const isDefault = ci === 0;
                                                    return (
                                                        <Link
                                                            key={child.href}
                                                            href={child.href}
                                                            onClick={onClose}
                                                            className={[
                                                                'flex w-full items-center gap-2 rounded-md px-2.5 py-1 text-[12.5px] transition-colors duration-100',
                                                                isDefault
                                                                    ? 'bg-neutral-900/[0.07] dark:bg-white/10 text-neutral-900 dark:text-white font-medium'
                                                                    : 'text-neutral-700 dark:text-neutral-400 font-normal hover:text-neutral-900 dark:hover:text-neutral-100',
                                                            ].join(' ')}
                                                        >
                                                            <span className="truncate flex-1 text-left">{child.label}</span>
                                                            {count > 0 && (
                                                                <span className="text-[10px] text-neutral-500 dark:text-neutral-500 shrink-0 tabular-nums">{count}</span>
                                                            )}
                                                        </Link>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                })}
            </div>
            </div>
            </div>
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
    expandedItemKey,
    expandedContent,
}) {
    const { t } = useTranslation();
    const { appName, logoUrl } = useBranding();

    const content = (
        <aside className="flex h-full w-64 flex-col bg-amber-50 dark:bg-neutral-900 border-r border-amber-200/60 dark:border-neutral-800 text-neutral-900 dark:text-neutral-100">
            {/* Brand header */}
            <div className="flex h-14 shrink-0 items-center gap-2.5 px-3.5">
                {logo ? (
                    logo
                ) : (
                    <Link href={route('client.dashboard')} className="flex items-center gap-2.5 group transition-transform duration-150 hover:scale-[1.01]">
                        <img src={logoUrl || '/images/brand/logo-full.png'} alt={appName || 'Growbridge Connect'} className="h-8 max-w-[180px] object-contain dark:[filter:invert(1)_hue-rotate(180deg)_brightness(1.15)_saturate(1.5)]" />
                    </Link>
                )}

                {showCreateButton && (
                    <button
                        type="button"
                        title={t('common.create')}
                        className="ml-auto flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-200/70 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 transition duration-150"
                    >
                        <Plus className="h-4 w-4" />
                    </button>
                )}
            </div>

            <nav className="flex-1 min-h-0 overflow-y-auto px-2.5 py-2 space-y-0.5 scrollbar-hide">
                {/* Standalone Nav Items (e.g. Dashboard, Clients) */}
                {navItems.length > 0 &&
                    navItems.filter(item => !item.locked).map((item, i) => {
                        if (item.type === 'divider') {
                            return <hr key={`div-${i}`} className="my-2 border-neutral-200/70 dark:border-neutral-800" />;
                        }
                        const isActive = checkIsActive(item);
                        return (
                            <Link
                                key={item.key ?? item.route ?? item.href ?? i}
                                href={item.href ?? (item.route ? route(item.route) : '#')}
                                onClick={onClose}
                                data-tour={item.dataTour}
                                className={[
                                    'group flex items-center gap-2.5 rounded-full px-3 py-1.5 text-[13px] transition-colors duration-100',
                                    isActive
                                        ? 'bg-neutral-900 text-white font-semibold dark:bg-white dark:text-neutral-900'
                                        : 'text-neutral-600 dark:text-neutral-400 font-normal hover:bg-neutral-900/5 dark:hover:bg-white/10 hover:text-neutral-900 dark:hover:text-neutral-100',
                                ].join(' ')}
                            >
                                {item.icon && (
                                    <span className={[
                                        'shrink-0 transition-colors duration-100',
                                        isActive ? 'text-white dark:text-neutral-900' : 'text-neutral-400 dark:text-neutral-500 group-hover:text-neutral-600 dark:group-hover:text-neutral-300',
                                    ].join(' ')}>
                                        {item.icon}
                                    </span>
                                )}
                                <span className="truncate">{item.label}</span>
                                {item.badge ? (
                                    <span className="ml-auto text-[11px] font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800 shrink-0">
                                        {item.badge}
                                    </span>
                                ) : null}
                            </Link>
                        );
                    })}

                {/* Collapsible Nav Groups */}
                {navGroups.length > 0 &&
                    navGroups.map((group, gi) => (
                        <div key={`${gi}-${group.key ?? group.label ?? ''}`}>
                            {group.type === 'group' && navGroups[gi - 1]?.type !== 'group' && (
                                <hr className="my-2.5 border-neutral-200/70 dark:border-neutral-800" />
                            )}
                            <NavGroup
                                type={group.type}
                                label={group.label}
                                icon={group.icon}
                                items={group.items ?? []}
                                onClose={onClose}
                                expandedItemKey={expandedItemKey}
                                expandedContent={expandedContent}
                            />
                        </div>
                    ))}
            </nav>

            {footer && (
                <div className="shrink-0 border-t border-neutral-200/70 dark:border-neutral-800 p-2.5">
                    <div className="text-neutral-600 dark:text-neutral-400">
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
