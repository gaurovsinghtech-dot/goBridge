import { useState } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ShieldCheck, Mail, Lock, Eye, EyeOff, ArrowRight } from 'lucide-react';

export default function AdminLogin({ status, error }) {
    const { t } = useTranslation();
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.login'), { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout
            variant="admin"
            title="Admin Control Center"
            subtitle="Secure access for system administrators & platform managers."
            status={status}
            error={error || errors.email || errors.password}
        >
            <Head title="Admin Log In · Growbridge Connect" />

            <form onSubmit={submit} className="space-y-4">
                {/* Email Address */}
                <div>
                    <label className="block text-xs font-semibold text-neutral-300 mb-1.5">
                        Admin Email
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
                            placeholder="admin@growbridge.com"
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
                            placeholder="Enter password"
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

                {/* Remember Me */}
                <div className="flex items-center justify-between text-xs pt-1">
                    <label className="flex items-center gap-2 text-neutral-300 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded bg-[#031510] border-neutral-700 text-emerald-500 focus:ring-emerald-500/30 focus:ring-offset-0 h-4 w-4 cursor-pointer"
                        />
                        <span>Remember credentials</span>
                    </label>
                </div>

                {/* Submit Button */}
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-bold py-3.5 text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-200 flex items-center justify-center gap-2 group disabled:opacity-50 cursor-pointer mt-2"
                >
                    <ShieldCheck className="h-4 w-4" />
                    <span>{processing ? 'Authenticating...' : 'Authorize Admin Login'}</span>
                </button>

                {/* Client Portal Link */}
                <div className="text-center text-xs text-neutral-400 pt-3">
                    Looking for the client workspace?{' '}
                    <Link
                        href={route('login')}
                        className="text-emerald-400 hover:text-emerald-300 font-bold transition underline underline-offset-2"
                    >
                        Client Sign In
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}
