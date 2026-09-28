import { Link } from '@inertiajs/react';
import LandingLayout from '@/Layouts/LandingLayout';
import SeoHead from '@/Components/SeoHead';
import { FeatureIcon } from '@/Components/LandingIcons';
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

export default function About({ canRegister, landing = {} }) {
    const { t } = useTranslation();
    const { appName } = useBranding();
    const s = (key, def = '') => landing[`landing.${key}`] ?? def;

    const values = [1, 2, 3, 4].map((i) => ({
        icon: s(`about_value_${i}_icon`, 'star'),
        title: s(`about_value_${i}_title`),
        desc: s(`about_value_${i}_desc`),
    })).filter((v) => v.title);

    const stats = [1, 2, 3, 4].map((i) => ({
        value: s(`about_stat_${i}_value`),
        label: s(`about_stat_${i}_label`),
    })).filter((st) => st.value);

    const storyParagraphs = (s('about_story_body') || '').split('\n').map((p) => p.trim()).filter(Boolean);

    return (
        <LandingLayout>
            <SeoHead
                title={`${s('about_title') || t('nav.about', { defaultValue: 'About' })} — ${appName}`}
                description={s('about_subtitle')}
            />

            {/* Hero */}
            <section className="py-20 bg-[#FAFAF9] border-b border-[#E5E5E5] text-center">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-center mb-6"><Badge text={s('about_badge')} /></div>
                    <h1 className="text-4xl sm:text-5xl font-bold tracking-tight text-[#171717] max-w-3xl mx-auto leading-tight">
                        {s('about_title')}
                    </h1>
                    {s('about_subtitle') && (
                        <p className="mt-6 text-lg text-[#737373] max-w-2xl mx-auto leading-relaxed">{s('about_subtitle')}</p>
                    )}
                </div>
            </section>

            {/* Stats band */}
            {stats.length > 0 && (
                <section className="border-b border-[#E5E5E5] bg-white">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-6">
                            {stats.map((st, idx) => (
                                <div key={idx} className="text-center">
                                    <p className="text-3xl sm:text-4xl font-bold tracking-tight text-[#171717]">{st.value}</p>
                                    <p className="mt-1 text-sm text-[#737373]">{st.label}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Story */}
            {storyParagraphs.length > 0 && (
                <section className="py-20 bg-white">
                    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h2 className="text-3xl font-bold text-[#171717] tracking-tight mb-6">
                            {s('about_story_title')}
                        </h2>
                        <div className="space-y-4">
                            {storyParagraphs.map((p, idx) => (
                                <p key={idx} className="text-lg text-[#737373] leading-relaxed">{p}</p>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Values */}
            {values.length > 0 && (
                <section className="py-20 bg-[#FAFAF9] border-t border-[#E5E5E5]">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl sm:text-4xl font-bold text-[#171717] tracking-tight">
                                {t('about_page.values_title', { defaultValue: 'What we stand for' })}
                            </h2>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {values.map((v, idx) => (
                                <div key={idx} className="rounded-xl border border-[#E5E5E5] bg-white p-6 shadow-sm">
                                    <div className="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-[#2563EB]">
                                        <FeatureIcon name={v.icon} className="h-5 w-5 text-[#2563EB]" />
                                    </div>
                                    <h3 className="text-base font-semibold text-[#171717] mb-1">{v.title}</h3>
                                    <p className="text-sm text-[#737373] leading-relaxed">{v.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* CTA */}
            <section className="py-20 bg-[#FAFAF9]">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-[#E5E5E5] bg-white px-8 py-16 text-center shadow-sm">
                        <h2 className="text-3xl sm:text-4xl font-bold text-[#171717] tracking-tight max-w-2xl mx-auto">
                            {s('about_cta_title')}
                        </h2>
                        {s('about_cta_subtitle') && (
                            <p className="mt-4 text-lg text-[#737373] max-w-xl mx-auto leading-relaxed">{s('about_cta_subtitle')}</p>
                        )}
                        <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
                            {canRegister && (
                                <Link href={route('register')} className="inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] shadow-sm transition-all">
                                    {t('welcome.get_started_free', { defaultValue: 'Get Started Free' })}
                                </Link>
                            )}
                            <Link href="/contact" className="inline-flex items-center gap-2 rounded-xl border border-[#E5E5E5] bg-white text-[#171717] px-7 py-3.5 text-base font-semibold hover:bg-neutral-50 transition-all">
                                {t('nav.contact', { defaultValue: 'Contact' })}
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
