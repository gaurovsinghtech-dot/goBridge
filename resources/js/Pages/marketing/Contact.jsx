import { useForm, usePage } from '@inertiajs/react';
import { Mail, Send, MessageSquare, Clock, CheckCircle2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import LandingLayout from '@/Layouts/LandingLayout';
import SeoHead from '@/Components/SeoHead';
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

const inputClass =
    'w-full rounded-lg border border-[#E5E5E5] bg-white px-4 py-2.5 text-sm text-[#171717] placeholder:text-[#737373] transition-colors focus:border-[#2563EB] focus:outline-none focus:ring-2 focus:ring-blue-100';

export default function Contact({ landing = {} }) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const { appName } = useBranding();
    const contactEmail = landing['landing.contact_email'] || `support@${appName.toLowerCase().replace(/\s+/g, '')}.com`;

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        name: '',
        email: '',
        subject: '',
        message: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('contact.store'), { preserveScroll: true, onSuccess: () => setData({ name: '', email: '', subject: '', message: '' }) });
    };

    const infoCards = [
        {
            icon: Mail,
            label: t('contact_page.email_label', { defaultValue: 'Email us' }),
            desc: t('contact_page.email_desc', { defaultValue: 'We reply to every message.' }),
            value: contactEmail,
            href: `mailto:${contactEmail}`,
        },
        {
            icon: MessageSquare,
            label: t('contact_page.chat_label', { defaultValue: 'Live chat' }),
            desc: t('contact_page.chat_desc', { defaultValue: 'Available in your dashboard, Mon–Fri.' }),
        },
        {
            icon: Clock,
            label: t('contact_page.response_label', { defaultValue: 'Response time' }),
            desc: t('contact_page.response_desc', { defaultValue: 'Within one business day.' }),
        },
    ];

    return (
        <LandingLayout>
            <SeoHead
                title={`${t('contact_page.title', { defaultValue: 'Contact Us' })} — ${appName}`}
                description={t('contact_page.subtitle')}
            />

            {/* Hero */}
            <section className="py-20 bg-[#FAFAF9] border-b border-[#E5E5E5] text-center">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-center mb-6">
                        <Badge text={t('contact_page.badge', { defaultValue: 'Get in touch' })} />
                    </div>
                    <h1 className="text-4xl sm:text-5xl font-bold tracking-tight text-[#171717] max-w-3xl mx-auto leading-tight">
                        {t('contact_page.title', { defaultValue: 'Contact Us' })}
                    </h1>
                    <p className="mt-6 text-lg text-[#737373] max-w-2xl mx-auto leading-relaxed">
                        {t('contact_page.subtitle')}
                    </p>
                </div>
            </section>

            {/* Body */}
            <section className="py-20 bg-white">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-10 lg:grid-cols-5 lg:gap-12">
                        {/* Contact info */}
                        <div className="lg:col-span-2">
                            <h2 className="text-xl font-bold text-[#171717] tracking-tight">
                                {t('contact_page.info_heading', { defaultValue: 'Other ways to reach us' })}
                            </h2>
                            <div className="mt-6 space-y-4">
                                {infoCards.map((card, idx) => {
                                    const Icon = card.icon;
                                    const inner = (
                                        <>
                                            <div className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#2563EB]">
                                                <Icon className="h-5 w-5 text-[#2563EB]" />
                                            </div>
                                            <div>
                                                <h3 className="text-sm font-semibold text-[#171717]">{card.label}</h3>
                                                <p className="mt-0.5 text-sm text-[#737373] leading-relaxed">{card.desc}</p>
                                                {card.value && (
                                                    <p className="mt-1 text-sm font-medium text-[#2563EB] break-all">{card.value}</p>
                                                )}
                                            </div>
                                        </>
                                    );
                                    const cls = 'flex items-start gap-4 rounded-xl border border-[#E5E5E5] bg-white p-5 transition-all duration-200 shadow-sm';
                                    return card.href ? (
                                        <a key={idx} href={card.href} className={`${cls} hover:border-blue-300`}>
                                            {inner}
                                        </a>
                                    ) : (
                                        <div key={idx} className={cls}>{inner}</div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Form */}
                        <div className="lg:col-span-3">
                            <div className="rounded-xl border border-[#E5E5E5] bg-white p-6 sm:p-8 shadow-sm">
                                <h2 className="text-xl font-bold text-[#171717] tracking-tight mb-6">
                                    {t('contact_page.form_heading', { defaultValue: 'Send us a message' })}
                                </h2>

                                {(flash?.success || recentlySuccessful) && (
                                    <div className="mb-6 flex items-start gap-2.5 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-[#171717]">
                                        <CheckCircle2 className="h-5 w-5 shrink-0 text-[#2563EB]" />
                                        <span>{flash?.success || t('contact_page.title')}</span>
                                    </div>
                                )}

                                <form onSubmit={submit} className="space-y-5">
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <label className="block text-sm font-medium text-[#171717] mb-1.5">{t('common.name')}</label>
                                            <input
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                placeholder={t('contact_page.name_placeholder', { defaultValue: '' })}
                                                className={inputClass}
                                                required
                                            />
                                            {errors.name && <p className="text-red-600 text-xs mt-1.5">{errors.name}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-[#171717] mb-1.5">{t('common.email')}</label>
                                            <input
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                placeholder={t('contact_page.email_placeholder', { defaultValue: '' })}
                                                className={inputClass}
                                                required
                                            />
                                            {errors.email && <p className="text-red-600 text-xs mt-1.5">{errors.email}</p>}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-[#171717] mb-1.5">{t('contact_page.subject')}</label>
                                        <input
                                            type="text"
                                            value={data.subject}
                                            onChange={(e) => setData('subject', e.target.value)}
                                            placeholder={t('contact_page.subject_placeholder', { defaultValue: '' })}
                                            className={inputClass}
                                        />
                                        {errors.subject && <p className="text-red-600 text-xs mt-1.5">{errors.subject}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-[#171717] mb-1.5">{t('contact_page.message')}</label>
                                        <textarea
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            rows={6}
                                            placeholder={t('contact_page.message_placeholder', { defaultValue: '' })}
                                            className={`${inputClass} resize-y`}
                                            required
                                        />
                                        {errors.message && <p className="text-red-600 text-xs mt-1.5">{errors.message}</p>}
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg px-7 py-3 text-base font-semibold text-white bg-[#2563EB] hover:bg-[#1D4ED8] shadow-sm transition-all duration-200 disabled:opacity-50 sm:w-auto"
                                    >
                                        <Send className="h-4 w-4" />
                                        {processing ? t('contact_page.sending', { defaultValue: 'Sending…' }) : t('contact_page.send_message')}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </LandingLayout>
    );
}
