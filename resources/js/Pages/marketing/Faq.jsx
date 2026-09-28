import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';
import { useTranslation } from 'react-i18next';

function Badge({ text }) {
    if (!text) return null;
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 text-[#2563EB] text-xs font-semibold px-3 py-1 border border-blue-200">
            <span className="h-1.5 w-1.5 rounded-full bg-[#2563EB] inline-block" />
            {text}
        </span>
    );
}

const CATEGORIES = [
    { key: 'all',       labelKey: 'faq.cat_all' },
    { key: 'general',   labelKey: 'faq.cat_general' },
    { key: 'billing',   labelKey: 'faq.cat_billing' },
    { key: 'technical', labelKey: 'faq.cat_technical' },
    { key: 'security',  labelKey: 'faq.cat_security' },
];

export default function Faq({ landing = {}, canRegister }) {
    const { t } = useTranslation();
    const s = (key, def = '') => landing[`landing.${key}`] ?? def;
    const [open, setOpen] = useState(null);
    const [cat, setCat] = useState('all');

    const faqs = [1, 2, 3, 4, 5].map((i) => ({
        q: s(`faq_${i}_q`),
        a: s(`faq_${i}_a`),
    })).filter((f) => f.q && f.a);

    const title = s('faq_title', 'Frequently Asked Questions');
    const subtitle = s('faq_subtitle', 'Everything you need to know about our platform.');

    return (
        <LandingLayout>
            <Head>
                <title>{t('faq.head_title', { title })}</title>
                <meta name="description" content={subtitle} />
            </Head>

            {/* ── Page hero ── */}
            <section className="py-20 bg-[#FAFAF9] border-b border-[#E5E5E5] text-center">
                <div className="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Badge text={t('nav.faq')} />
                    <h1 className="mt-4 text-4xl sm:text-5xl font-bold text-[#171717] tracking-tight">{title}</h1>
                    <p className="mt-4 text-lg text-[#737373] max-w-xl mx-auto leading-relaxed">{subtitle}</p>
                </div>
            </section>

            {/* ── Category filter ── */}
            <section className="border-b border-[#E5E5E5] bg-white sticky top-20 z-10">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-2 overflow-x-auto py-3 scrollbar-none">
                    {CATEGORIES.map((c) => (
                        <button
                            key={c.key}
                            onClick={() => setCat(c.key)}
                            className={`shrink-0 rounded-lg px-4 py-1.5 text-sm font-semibold transition-all ${
                                cat === c.key
                                    ? 'bg-[#2563EB] text-white shadow-sm'
                                    : 'text-[#737373] hover:text-[#171717] bg-[#FAFAF9] border border-[#E5E5E5]'
                            }`}
                        >
                            {t(c.labelKey)}
                        </button>
                    ))}
                </div>
            </section>

            {/* ── FAQ list ── */}
            <section className="py-16 bg-[#FAFAF9]">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    {faqs.length === 0 ? (
                        <p className="text-center text-[#737373] py-16">{t('faq.no_items')}</p>
                    ) : (
                        <div className="space-y-3">
                            {faqs.map((faq, idx) => (
                                <div
                                    key={idx}
                                    className="rounded-xl border border-[#E5E5E5] bg-white overflow-hidden shadow-sm"
                                >
                                    <button
                                        className="w-full flex items-center justify-between px-5 py-4 text-left gap-4"
                                        onClick={() => setOpen(open === idx ? null : idx)}
                                    >
                                        <span className="font-semibold text-[#171717] text-sm">{faq.q}</span>
                                        <svg
                                            className={`h-5 w-5 shrink-0 text-[#737373] transition-transform duration-200 ${open === idx ? 'rotate-180' : ''}`}
                                            fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"
                                        >
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    {open === idx && (
                                        <div className="px-5 pb-5 border-t border-[#E5E5E5] pt-4">
                                            <p className="text-sm text-[#737373] leading-relaxed">{faq.a}</p>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Still have questions? */}
                    <div className="mt-12 rounded-xl p-8 text-center bg-white border border-[#E5E5E5] shadow-sm">
                        <h3 className="text-lg font-bold text-[#171717] mb-2">{t('faq.still_have_questions')}</h3>
                        <p className="text-sm text-[#737373] mb-5 leading-relaxed">{t('faq.still_have_questions_desc')}</p>
                        <Link
                            href="/contact"
                            className="inline-flex items-center gap-2 rounded-lg px-6 py-2.5 text-sm font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] transition-all shadow-sm"
                        >
                            {t('faq.contact_support')}
                        </Link>
                    </div>
                </div>
            </section>

            {/* ── CTA ── */}
            {canRegister && (
                <section className="py-16 bg-white border-t border-[#E5E5E5]">
                    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                        <h2 className="text-2xl font-bold text-[#171717]">{t('faq.ready_to_try')}</h2>
                        <p className="mt-2 text-[#737373] text-sm leading-relaxed">{t('faq.ready_to_try_desc')}</p>
                        <Link
                            href={route('register')}
                            className="mt-6 inline-flex items-center gap-2 rounded-lg px-7 py-3.5 text-base font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] shadow-sm transition-all"
                        >
                            {t('welcome.get_started_free')}
                        </Link>
                    </div>
                </section>
            )}
        </LandingLayout>
    );
}
