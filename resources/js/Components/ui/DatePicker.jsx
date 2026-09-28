import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { Calendar, ChevronLeft, ChevronRight, Clock, X, Check, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * Custom calendar date & time picker — redesigned for effortless date & time selection.
 *
 * Value format mirrors native inputs:
 *   mode="date"     → "yyyy-mm-dd"
 *   mode="datetime" → "yyyy-mm-ddThh:mm"
 */

const pad2 = (n) => String(n).padStart(2, '0');
const DATE_RE = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/;

function parseValue(str) {
    if (!str) return null;
    const m = DATE_RE.exec(str);
    if (!m) return null;
    const [, y, mo, d, h, mi] = m;
    return {
        year: +y,
        month: +mo - 1,
        day: +d,
        hour: h != null ? +h : 9, // Default to 9 AM if no time provided
        minute: mi != null ? +mi : 0,
    };
}

function partsToDate(p) {
    return new Date(p.year, p.month, p.day, p.hour || 0, p.minute || 0);
}

function formatValue(p, mode) {
    const date = `${p.year}-${pad2(p.month + 1)}-${pad2(p.day)}`;
    if (mode === 'datetime') return `${date}T${pad2(p.hour)}:${pad2(p.minute)}`;
    return date;
}

function dayKey(year, month, day) {
    return year * 10000 + month * 100 + day;
}

function to12Hour(hour24) {
    const ampm = hour24 >= 12 ? 'PM' : 'AM';
    let hour12 = hour24 % 12;
    if (hour12 === 0) hour12 = 12;
    return { hour12, ampm };
}

function to24Hour(hour12, ampm) {
    let h24 = (hour12 % 12);
    if (ampm === 'PM') h24 += 12;
    return h24;
}

export default function DatePicker({
    value,
    onChange,
    mode = 'date',
    min,
    max,
    placeholder,
    error = false,
    disabled = false,
    className = '',
    id,
    name,
    required = false,
    ...rest
}) {
    const { t, i18n } = useTranslation();
    const lang = i18n?.language || 'en';

    const [open, setOpen] = useState(false);
    const [view, setView] = useState('days'); // 'days' | 'months'
    const containerRef = useRef(null);
    const popoverRef = useRef(null);
    const [dropUp, setDropUp] = useState(false);

    const selected = useMemo(() => parseValue(value), [value]);

    const [cursor, setCursor] = useState(() => {
        const base = selected || null;
        const now = new Date();
        return {
            year: base ? base.year : now.getFullYear(),
            month: base ? base.month : now.getMonth(),
        };
    });

    const openPicker = () => {
        const now = new Date();
        setCursor({
            year: selected ? selected.year : now.getFullYear(),
            month: selected ? selected.month : now.getMonth(),
        });
        setView('days');
        setOpen(true);
    };

    const minKey = useMemo(() => {
        const p = parseValue(min);
        return p ? dayKey(p.year, p.month, p.day) : null;
    }, [min]);

    const maxKey = useMemo(() => {
        const p = parseValue(max);
        return p ? dayKey(p.year, p.month, p.day) : null;
    }, [max]);

    useEffect(() => {
        if (!open) return;
        const handler = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpen(false);
                setView('days');
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    useEffect(() => {
        if (!open) return;
        const handler = (e) => {
            if (e.key === 'Escape') {
                setOpen(false);
                setView('days');
            }
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [open]);

    useEffect(() => {
        if (!open || !containerRef.current) return;
        const rect = containerRef.current.getBoundingClientRect();
        const estimated = mode === 'datetime' ? 460 : 380;
        setDropUp(rect.bottom + estimated > window.innerHeight && rect.top > estimated);
    }, [open, mode]);

    const weekdays = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(lang, { weekday: 'narrow' });
        return Array.from({ length: 7 }, (_, i) => fmt.format(new Date(2023, 0, 1 + i)));
    }, [lang]);

    const monthNames = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(lang, { month: 'short' });
        return Array.from({ length: 12 }, (_, i) => fmt.format(new Date(2023, i, 1)));
    }, [lang]);

    const headerLabel = useMemo(
        () => new Intl.DateTimeFormat(lang, { month: 'long', year: 'numeric' })
            .format(new Date(cursor.year, cursor.month, 1)),
        [lang, cursor],
    );

    const triggerLabel = useMemo(() => {
        if (!selected) {
            return placeholder
                || (mode === 'datetime'
                    ? t('ui.date_select_datetime', 'Select date & time')
                    : t('ui.date_select', 'Select date'));
        }
        const opts = mode === 'datetime'
            ? { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }
            : { year: 'numeric', month: 'short', day: 'numeric' };
        return new Intl.DateTimeFormat(lang, opts).format(partsToDate(selected));
    }, [selected, mode, lang, placeholder, t]);

    const cells = useMemo(() => {
        const firstDow = new Date(cursor.year, cursor.month, 1).getDay();
        const start = new Date(cursor.year, cursor.month, 1 - firstDow);
        return Array.from({ length: 42 }, (_, i) => {
            const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            return {
                year: d.getFullYear(),
                month: d.getMonth(),
                day: d.getDate(),
                inMonth: d.getMonth() === cursor.month,
            };
        });
    }, [cursor]);

    const isDisabledDay = useCallback((c) => {
        const k = dayKey(c.year, c.month, c.day);
        if (minKey != null && k < minKey) return true;
        if (maxKey != null && k > maxKey) return true;
        return false;
    }, [minKey, maxKey]);

    const emit = useCallback((parts) => {
        if (parts == null) {
            onChange?.('');
            return;
        }
        onChange?.(formatValue(parts, mode));
    }, [onChange, mode]);

    const selectDay = (c) => {
        if (isDisabledDay(c)) return;
        const now = new Date();
        const next = {
            year: c.year,
            month: c.month,
            day: c.day,
            hour: selected ? selected.hour : (now.getHours() < 23 ? now.getHours() + 1 : 9),
            minute: selected ? selected.minute : 0,
        };
        emit(next);
        setCursor({ year: c.year, month: c.month });
        if (mode !== 'datetime') {
            setOpen(false);
            setView('days');
        }
    };

    const setTimePart = (h24, mi) => {
        const base = selected || (() => {
            const now = new Date();
            return { year: now.getFullYear(), month: now.getMonth(), day: now.getDate(), hour: 9, minute: 0 };
        })();
        emit({ ...base, hour: h24, minute: mi });
    };

    const setHour12 = (h12) => {
        const currentH24 = selected ? selected.hour : 9;
        const { ampm } = to12Hour(currentH24);
        const newH24 = to24Hour(h12, ampm);
        setTimePart(newH24, selected ? selected.minute : 0);
    };

    const setAmPm = (ampm) => {
        const currentH24 = selected ? selected.hour : 9;
        const { hour12 } = to12Hour(currentH24);
        const newH24 = to24Hour(hour12, ampm);
        setTimePart(newH24, selected ? selected.minute : 0);
    };

    const setMinute = (mi) => {
        const currentH24 = selected ? selected.hour : 9;
        setTimePart(currentH24, mi);
    };

    const selectPresetDate = (daysFromNow) => {
        const d = new Date();
        d.setDate(d.getDate() + daysFromNow);
        const c = { year: d.getFullYear(), month: d.getMonth(), day: d.getDate() };
        if (isDisabledDay(c)) return;
        selectDay(c);
    };

    const applyTimePreset = (h24, mi) => {
        if (!selected) {
            const now = new Date();
            selectDay({ year: now.getFullYear(), month: now.getMonth(), day: now.getDate() });
        }
        setTimePart(h24, mi);
    };

    const stepMonth = (delta) => {
        setCursor((c) => {
            const d = new Date(c.year, c.month + delta, 1);
            return { year: d.getFullYear(), month: d.getMonth() };
        });
    };

    const todayKey = useMemo(() => {
        const n = new Date();
        return dayKey(n.getFullYear(), n.getMonth(), n.getDate());
    }, []);

    const selectedKey = selected ? dayKey(selected.year, selected.month, selected.day) : null;
    const { hour12, ampm } = useMemo(() => to12Hour(selected ? selected.hour : 9), [selected]);

    const triggerClasses = [
        'w-full flex items-center gap-2.5 rounded-xl border bg-white dark:bg-neutral-900 px-3.5 py-2.5 text-xs text-left shadow-xs transition duration-150 focus:outline-none focus:ring-2',
        error
            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
            : 'border-neutral-200 dark:border-neutral-700/80 focus:border-blue-600 focus:ring-blue-600/20 hover:border-neutral-300 dark:hover:border-neutral-600',
        disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
    ].join(' ');

    return (
        <div ref={containerRef} className={`relative ${className}`}>
            <input type="hidden" id={id} name={name} value={value || ''} required={required} {...rest} />

            <button
                type="button"
                disabled={disabled}
                onClick={() => !disabled && (open ? setOpen(false) : openPicker())}
                className={triggerClasses}
                aria-haspopup="dialog"
                aria-expanded={open}
            >
                <Calendar className="h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                <span className={`flex-1 truncate font-medium ${selected ? 'text-neutral-900 dark:text-neutral-100' : 'text-neutral-400 dark:text-neutral-500'}`}>
                    {triggerLabel}
                </span>
                {selected && !disabled && (
                    <span
                        role="button"
                        tabIndex={-1}
                        onClick={(e) => { e.stopPropagation(); emit(null); }}
                        className="shrink-0 rounded-lg p-1 text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 hover:text-neutral-600 dark:hover:text-neutral-300 transition"
                        aria-label={t('ui.date_clear', 'Clear')}
                    >
                        <X className="h-3.5 w-3.5" />
                    </span>
                )}
            </button>

            {open && (
                <div
                    ref={popoverRef}
                    className={`absolute z-50 w-80 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4 shadow-xl ${
                        dropUp ? 'bottom-full mb-2' : 'top-full mt-2'
                    } left-0 rtl:left-auto rtl:right-0 space-y-3.5`}
                    role="dialog"
                >
                    {/* Top Preset Chips */}
                    <div className="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                        <button
                            type="button"
                            onClick={() => selectPresetDate(0)}
                            className="px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-blue-50 dark:hover:bg-neutral-700 hover:text-blue-600 transition shrink-0"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            onClick={() => selectPresetDate(1)}
                            className="px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-blue-50 dark:hover:bg-neutral-700 hover:text-blue-600 transition shrink-0"
                        >
                            Tomorrow
                        </button>
                        <button
                            type="button"
                            onClick={() => selectPresetDate(2)}
                            className="px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-blue-50 dark:hover:bg-neutral-700 hover:text-blue-600 transition shrink-0"
                        >
                            In 2 Days
                        </button>
                        {mode === 'datetime' && (
                            <button
                                type="button"
                                onClick={() => {
                                    const now = new Date();
                                    now.setHours(now.getHours() + 1);
                                    selectDay({ year: now.getFullYear(), month: now.getMonth(), day: now.getDate() });
                                    setTimePart(now.getHours(), 0);
                                }}
                                className="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/60 transition shrink-0 flex items-center gap-1"
                            >
                                <Sparkles className="w-3 h-3" /> +1 Hr
                            </button>
                        )}
                    </div>

                    {/* Month / Year Header */}
                    <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-2.5">
                        <button
                            type="button"
                            onClick={() => setView((v) => (v === 'days' ? 'months' : 'days'))}
                            className="rounded-lg px-2.5 py-1 text-xs font-bold text-neutral-900 dark:text-neutral-100 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                        >
                            {view === 'days' ? headerLabel : cursor.year}
                        </button>
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={() => (view === 'days' ? stepMonth(-1) : setCursor((c) => ({ ...c, year: c.year - 1 })))}
                                className="rounded-lg p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                aria-label={t('ui.date_prev', 'Previous')}
                            >
                                <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                            </button>
                            <button
                                type="button"
                                onClick={() => (view === 'days' ? stepMonth(1) : setCursor((c) => ({ ...c, year: c.year + 1 })))}
                                className="rounded-lg p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                aria-label={t('ui.date_next', 'Next')}
                            >
                                <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                            </button>
                        </div>
                    </div>

                    {/* Day Grid view */}
                    {view === 'days' ? (
                        <div className="space-y-3">
                            <div className="grid grid-cols-7 gap-1">
                                {weekdays.map((w, i) => (
                                    <div key={i} className="py-1 text-center text-[10px] font-bold text-neutral-400 uppercase tracking-wider">
                                        {w}
                                    </div>
                                ))}
                                {cells.map((c, i) => {
                                    const k = dayKey(c.year, c.month, c.day);
                                    const isSelected = k === selectedKey;
                                    const isToday = k === todayKey;
                                    const disabledDay = isDisabledDay(c);
                                    return (
                                        <button
                                            type="button"
                                            key={i}
                                            disabled={disabledDay}
                                            onClick={() => selectDay(c)}
                                            className={[
                                                'h-8 w-8 mx-auto flex items-center justify-center rounded-xl text-xs font-semibold transition-all',
                                                isSelected
                                                    ? 'bg-blue-600 text-white font-bold shadow-xs scale-105'
                                                    : disabledDay
                                                        ? 'text-neutral-300 dark:text-neutral-700 cursor-not-allowed'
                                                        : c.inMonth
                                                            ? 'text-neutral-800 dark:text-neutral-200 hover:bg-blue-50 dark:hover:bg-neutral-800 hover:text-blue-600'
                                                            : 'text-neutral-400 dark:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800',
                                                !isSelected && isToday ? 'ring-1 ring-blue-500 text-blue-600 dark:text-blue-400 font-bold' : '',
                                            ].join(' ')}
                                        >
                                            {c.day}
                                        </button>
                                    );
                                })}
                            </div>

                            {/* Redesigned 12-Hour Time Selector for mode="datetime" */}
                            {mode === 'datetime' && (
                                <div className="border-t border-neutral-100 dark:border-neutral-800 pt-3 space-y-2.5">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-1.5">
                                            <Clock className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                                            {t('ui.date_time', 'Set Time')}
                                        </span>
                                        <span className="text-[11px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 rounded-lg">
                                            {pad2(hour12)}:{pad2(selected ? selected.minute : 0)} {ampm}
                                        </span>
                                    </div>

                                    {/* Quick Time Presets */}
                                    <div className="grid grid-cols-4 gap-1.5 text-[10px]">
                                        {[
                                            { label: '9:00 AM', h: 9, m: 0 },
                                            { label: '12:00 PM', h: 12, m: 0 },
                                            { label: '3:00 PM', h: 15, m: 0 },
                                            { label: '6:00 PM', h: 18, m: 0 },
                                        ].map((tPreset) => (
                                            <button
                                                key={tPreset.label}
                                                type="button"
                                                onClick={() => applyTimePreset(tPreset.h, tPreset.m)}
                                                className="py-1 rounded-lg border border-neutral-200 dark:border-neutral-700/60 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-700 dark:text-neutral-300 font-semibold hover:bg-blue-50 dark:hover:bg-neutral-700 hover:text-blue-600 transition"
                                            >
                                                {tPreset.label}
                                            </button>
                                        ))}
                                    </div>

                                    {/* Interactive 12-Hour Time Controls */}
                                    <div className="flex items-center gap-2 bg-neutral-50 dark:bg-neutral-800/60 p-2 rounded-xl border border-neutral-200 dark:border-neutral-700/60">
                                        {/* Hour Dropdown (1..12) */}
                                        <div className="flex-1">
                                            <select
                                                value={hour12}
                                                onChange={(e) => setHour12(+e.target.value)}
                                                className="w-full text-xs font-bold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 py-1.5 px-2 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:border-blue-600 cursor-pointer"
                                            >
                                                {Array.from({ length: 12 }, (_, i) => i + 1).map((h) => (
                                                    <option key={h} value={h}>
                                                        {pad2(h)}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <span className="text-sm font-bold text-neutral-400">:</span>

                                        {/* Minute Step Dropdown (00..55 in steps of 5) */}
                                        <div className="flex-1">
                                            <select
                                                value={selected ? selected.minute : 0}
                                                onChange={(e) => setMinute(+e.target.value)}
                                                className="w-full text-xs font-bold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 py-1.5 px-2 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:border-blue-600 cursor-pointer"
                                            >
                                                {Array.from({ length: 60 }, (_, mi) => mi).map((mi) => (
                                                    <option key={mi} value={mi}>
                                                        {pad2(mi)}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        {/* AM / PM Segmented Toggle */}
                                        <div className="flex p-0.5 rounded-lg bg-neutral-200 dark:bg-neutral-700 shrink-0">
                                            <button
                                                type="button"
                                                onClick={() => setAmPm('AM')}
                                                className={`px-2 py-1 rounded-md text-[11px] font-extrabold transition ${
                                                    ampm === 'AM'
                                                        ? 'bg-blue-600 text-white shadow-xs'
                                                        : 'text-neutral-600 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white'
                                                }`}
                                            >
                                                AM
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setAmPm('PM')}
                                                className={`px-2 py-1 rounded-md text-[11px] font-extrabold transition ${
                                                    ampm === 'PM'
                                                        ? 'bg-blue-600 text-white shadow-xs'
                                                        : 'text-neutral-600 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white'
                                                }`}
                                            >
                                                PM
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : (
                        /* Month Selector Grid */
                        <div className="grid grid-cols-3 gap-2 py-2">
                            {monthNames.map((mName, i) => (
                                <button
                                    type="button"
                                    key={i}
                                    onClick={() => { setCursor((c) => ({ ...c, month: i })); setView('days'); }}
                                    className={[
                                        'rounded-xl py-2 text-xs font-bold transition',
                                        i === cursor.month
                                            ? 'bg-blue-600 text-white shadow-xs'
                                            : 'text-neutral-700 dark:text-neutral-200 hover:bg-blue-50 dark:hover:bg-neutral-800 hover:text-blue-600',
                                    ].join(' ')}
                                >
                                    {mName}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Popover Footer: Clear / Apply Actions */}
                    <div className="flex items-center justify-between border-t border-neutral-100 dark:border-neutral-800 pt-3">
                        <button
                            type="button"
                            onClick={() => { emit(null); setOpen(false); }}
                            className="text-xs font-bold text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200 transition"
                        >
                            {t('ui.date_clear', 'Clear')}
                        </button>

                        <button
                            type="button"
                            onClick={() => {
                                if (!selected) {
                                    const now = new Date();
                                    selectDay({ year: now.getFullYear(), month: now.getMonth(), day: now.getDate() });
                                }
                                setOpen(false);
                            }}
                            className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold shadow-xs transition"
                        >
                            <Check className="w-3.5 h-3.5" /> Done
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
