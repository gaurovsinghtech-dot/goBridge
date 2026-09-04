import { useState } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Mail, Lock, Eye, EyeOff, ArrowRight } from 'lucide-react';

function GoogleIcon() {
    return (
        <svg className="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
        </svg>
    );
}

function MicrosoftIcon() {
    return (
        <svg className="h-4 w-4 shrink-0" viewBox="0 0 23 23">
            <path fill="#f35325" d="M1 1h10v10H1z" />
            <path fill="#81bc06" d="M12 1h10v10H12z" />
            <path fill="#05a6f0" d="M1 12h10v10H1z" />
            <path fill="#ffba08" d="M12 12h10v10H12z" />
        </svg>
    );
}

function AppleIcon() {
    return (
        <svg className="h-4 w-4 shrink-0 fill-current" viewBox="0 0 24 24">
            <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.37c.62-.75 1.04-1.8 0.92-2.85-.9.04-1.99.6-2.64 1.36-.58.67-.99 1.74-.86 2.76 1.01.08 2.04-.52 2.58-1.27z" />
        </svg>
    );
}

export default function Login({ status, canResetPassword = true, socialProviders = [] }) {
    const { t } = useTranslation();
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout
            variant="login"
            title="Welcome back"
            subtitle="Log in to your Growbridge Connect account and continue growing your business."
            status={status}
            error={errors.email || errors.password}
        >
            <Head title="Log In · Growbridge Connect" />

            <form onSubmit={submit} className="space-y-4">
                {/* Email Address */}
                <div>
                    <label className="block text-xs font-semibold text-neutral-300 mb-1.5">
                        Email address
                    </label>
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-neutral-400">
                            <Mail className="h-4 w-4" />
                        </div>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            autoComplete="username"
                            autoFocus
                            required
                            placeholder="Enter your email"
                            onChange={(e) => setData('email', e.target.value)}
                            className="w-full rounded-xl bg-[#031510] border border-neutral-700/80 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 pl-10 pr-4 py-2.5 text-sm text-white placeholder-neutral-500 transition-colors shadow-inner"
                        />
                    </div>
                </div>

                {/* Password */}
                <div>
                    <label className="block text-xs font-semibold text-neutral-300 mb-1.5">
                        Password
                    </label>
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-neutral-400">
                            <Lock className="h-4 w-4" />
                        </div>
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            autoComplete="current-password"
                            required
                            placeholder="Enter your password"
                            onChange={(e) => setData('password', e.target.value)}
                            className="w-full rounded-xl bg-[#031510] border border-neutral-700/80 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 pl-10 pr-10 py-2.5 text-sm text-white placeholder-neutral-500 transition-colors shadow-inner"
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-neutral-400 hover:text-neutral-200 transition"
                        >
                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                    </div>
                </div>

                {/* Remember Me & Forgot Password Row */}
                <div className="flex items-center justify-between text-xs pt-1">
                    <label className="flex items-center gap-2 text-neutral-300 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded bg-[#031510] border-neutral-700 text-emerald-500 focus:ring-emerald-500/30 focus:ring-offset-0 h-4 w-4 cursor-pointer"
                        />
                        <span>Remember me</span>
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-emerald-400 hover:text-emerald-300 font-medium transition"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                {/* Submit Button */}
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-bold py-3.5 text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-200 flex items-center justify-center gap-2 group disabled:opacity-50 cursor-pointer mt-2"
                >
                    <span>{processing ? 'Logging in...' : 'Log In'}</span>
                    <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                </button>

                {/* Divider */}
                <div className="relative my-6">
                    <div className="absolute inset-0 flex items-center">
                        <div className="w-full border-t border-neutral-700/80" />
                    </div>
                    <div className="relative flex justify-center">
                        <span className="bg-[#051f17] px-3 text-xs text-neutral-400">
                            or continue with
                        </span>
                    </div>
                </div>

                {/* Social Login 3-Button Row */}
                <div className="grid grid-cols-3 gap-2.5">
                    <button
                        type="button"
                        onClick={() => window.location.href = route('auth.social.redirect', { provider: 'google' })}
                        className="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl bg-[#031711] border border-neutral-700/80 hover:border-emerald-500/50 hover:bg-[#06241b] text-xs font-medium text-white transition-all"
                    >
                        <GoogleIcon />
                        <span>Google</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => window.location.href = route('auth.social.redirect', { provider: 'microsoft' })}
                        className="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl bg-[#031711] border border-neutral-700/80 hover:border-emerald-500/50 hover:bg-[#06241b] text-xs font-medium text-white transition-all"
                    >
                        <MicrosoftIcon />
                        <span>Microsoft</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => window.location.href = route('auth.social.redirect', { provider: 'apple' })}
                        className="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl bg-[#031711] border border-neutral-700/80 hover:border-emerald-500/50 hover:bg-[#06241b] text-xs font-medium text-white transition-all"
                    >
                        <AppleIcon />
                        <span>Apple</span>
                    </button>
                </div>

                {/* Bottom Account Switcher */}
                <div className="text-center text-xs text-neutral-400 pt-3">
                    Don't have an account?{' '}
                    <Link
                        href={route('register')}
                        className="text-emerald-400 hover:text-emerald-300 font-bold transition underline underline-offset-2"
                    >
                        Sign up
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}
