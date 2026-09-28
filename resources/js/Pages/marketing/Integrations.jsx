import { Link } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';
import SeoHead from '@/Components/SeoHead';
import { BrandMark } from '@/Components/BrandIcons';
import { useTranslation } from 'react-i18next';
import { useBranding } from '@/hooks/useBranding';

function Badge({ text }) {
    if (!text) return null;
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 text-[#2563EB] text-xs font-semibold px-3 py-1 border border-blue-200">
            <span className="h-1.5 w-1.5 rounded-full bg-[#2563EB] inline-block" />
            {text}
        </span>
    );
}

export default function Integrations({ canRegister, landing = {} }) {
    const { t } = useTranslation();
    const { appName } = useBranding();
    const s = (key, def = '') => landing[`landing.${key}`] ?? def;

    const categories = [1, 2, 3, 4, 5, 6, 7].map((i) => ({
        title: s(`intcat_${i}_title`),
        items: (s(`intcat_${i}_items`) || '').split('\n').map((x) => x.trim()).filter(Boolean),
    })).filter((c) => c.title && c.items.length);

    return (
        <LandingLayout>
            <SeoHead
                title={`${s('integrations_page_title') || t('nav.integrations', { defaultValue: 'Integrations' })} — ${appName}`}
                description={s('integrations_page_subtitle')}
            />

            {/* Hero */}
            <section className="py-20 bg-[#FAFAF9] border-b border-[#E5E5E5] text-center">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-center mb-6"><Badge text={s('integrations_page_badge')} /></div>
                    <h1 className="text-4xl sm:text-5xl font-bold tracking-tight text-[#171717] max-w-3xl mx-auto leading-tight">
                        {s('integrations_page_title')}
                    </h1>
                    {s('integrations_page_subtitle') && (
                        <p className="mt-6 text-lg text-[#737373] max-w-2xl mx-auto leading-relaxed">{s('integrations_page_subtitle')}</p>
                    )}
                </div>
            </section>

            {/* Categories */}
            <section className="py-20 bg-white">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                    {categories.map((cat, ci) => (
                        <div key={ci}>
                            <h2 className="text-xl font-bold text-[#171717] tracking-tight mb-5">{cat.title}</h2>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {cat.items.map((item, ii) => (
                                    <div
                                        key={ii}
                                        className="flex items-center gap-3 rounded-xl border border-[#E5E5E5] bg-white p-4 hover:border-blue-300 hover:shadow-sm transition-all duration-200"
                                    >
                                        <BrandMark name={item} tileClassName="h-10 w-10 rounded-lg shrink-0 bg-[#FAFAF9] border border-[#E5E5E5]" glyphClassName="h-5 w-5" />
                                        <span className="text-sm font-semibold text-[#171717]">{item}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* CTA */}
            <section className="py-20 bg-[#FAFAF9] border-t border-[#E5E5E5]">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h2 className="text-3xl font-bold text-[#171717] tracking-tight">
                        {t('integrations_page.cta_title', { defaultValue: 'Need a custom integration?' })}
                    </h2>
                    <p className="mt-4 text-lg text-[#737373] leading-relaxed">
                        {t('integrations_page.cta_subtitle', { defaultValue: `Use our REST API and webhooks to connect ${appName} to anything, or talk to our team.` })}
                    </p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                        {canRegister && (
                            <Link href={route('register')} className="inline-flex items-center gap-2 rounded-lg px-7 py-3.5 text-base font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] shadow-sm transition-all duration-200">
                                {t('welcome.get_started_free', { defaultValue: 'Get Started Free' })}
                            </Link>
                        )}
                        <Link href="/contact" className="inline-flex items-center gap-2 rounded-lg border border-[#E5E5E5] bg-white px-7 py-3.5 text-base font-semibold text-[#171717] hover:bg-neutral-50 transition-all duration-200">
                            {t('nav.contact', { defaultValue: 'Contact Sales' })}
                        </Link>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
