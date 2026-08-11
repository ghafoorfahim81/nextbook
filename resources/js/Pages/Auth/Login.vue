<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const { t } = useI18n();

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const currentYear = new Date().getFullYear();

// Field-level validation happens client side (mirrors the design); anything the
// server rejects — bad credentials, throttling — surfaces in the banner above.
const clientErrors = reactive({ email: '', password: '' });

const serverError = computed(() => form.errors.email || form.errors.password || '');

const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

const clearErrors = (field) => {
    clientErrors[field] = '';
    form.clearErrors();
};

const submit = () => {
    if (form.processing) return;

    clientErrors.email = !form.email
        ? t('auth.email_required')
        : !isValidEmail(form.email)
            ? t('auth.email_invalid')
            : '';
    clientErrors.password = form.password ? '' : t('auth.password_required');

    if (clientErrors.email || clientErrors.password) return;

    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap"
            rel="stylesheet"
        >
    </Head>

    <div class="nb-login relative flex min-h-screen flex-col items-center justify-center px-6 pb-10 pt-[88px]">
        <!-- Top bar: brand + language -->
        <div class="absolute inset-x-0 top-0 flex h-16 items-center justify-between px-7">
            <div class="flex items-center gap-[9px]">
                <div class="nb-accent-bg flex h-[29px] w-[29px] items-center justify-center rounded-[8px] text-base font-semibold text-white nb-mono">
                    N
                </div>
                <span class="text-base font-bold tracking-[-0.02em] text-zinc-900">Nextbook</span>
                <span class="nb-mono ms-0.5 rounded-[5px] border border-zinc-200 px-[5px] py-[2px] text-[9.5px] font-medium tracking-[0.12em] text-zinc-400">
                    ERP
                </span>
            </div>

            <div class="flex items-center gap-2">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a1a1aa" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
                </svg>
                <LanguageSwitcher />
            </div>
        </div>

        <!-- Card -->
        <div class="nb-card relative z-10 w-full max-w-[406px] rounded-[14px] border border-[#ececee] bg-white px-9 pb-[26px] pt-9">
            <div class="nb-rise">
                <h1 class="mb-1.5 text-[21px] font-bold tracking-[-0.025em] text-zinc-900">
                    {{ t('auth.sign_in_title') }}
                </h1>
                <p class="mb-[22px] text-sm leading-normal text-zinc-500">
                    {{ t('auth.sign_in_subtitle') }}
                </p>

                <div
                    v-if="status"
                    class="mb-4 flex items-start gap-2 rounded-[9px] border border-emerald-200 bg-emerald-50 px-3 py-2.5"
                    role="status"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.4" class="mt-px flex-none" aria-hidden="true">
                        <path d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-[13px] leading-[1.45] text-emerald-700">{{ status }}</span>
                </div>

                <div
                    v-if="serverError"
                    class="nb-shake mb-4 flex items-start gap-2 rounded-[9px] border border-red-200 bg-red-50 px-3 py-2.5"
                    role="alert"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" class="mt-px flex-none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 8v5M12 16.5v.01" />
                    </svg>
                    <span class="text-[13px] leading-[1.45] text-red-700">{{ serverError }}</span>
                </div>

                <form class="flex flex-col gap-[15px]" @submit.prevent="submit">
                    <div>
                        <label for="email" class="mb-[7px] block text-[13px] font-semibold text-zinc-700">
                            {{ t('auth.work_email') }}
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            autofocus
                            placeholder="you@company.com"
                            class="nb-input"
                            :class="{ 'nb-input-error': clientErrors.email }"
                            @input="clearErrors('email')"
                        >
                        <p v-if="clientErrors.email" class="mt-1.5 text-[12.5px] text-red-600">
                            {{ clientErrors.email }}
                        </p>
                    </div>

                    <div>
                        <div class="mb-[7px] flex items-baseline justify-between">
                            <label for="password" class="text-[13px] font-semibold text-zinc-700">
                                {{ t('auth.password') }}
                            </label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="nb-accent-text text-[12.5px] font-semibold no-underline"
                            >
                                {{ t('auth.forgot_password') }}
                            </Link>
                        </div>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                :placeholder="t('auth.enter_your_password')"
                                class="nb-input pe-[58px]"
                                :class="{ 'nb-input-error': clientErrors.password }"
                                @input="clearErrors('password')"
                            >
                            <button
                                type="button"
                                class="absolute end-[11px] top-1/2 -translate-y-1/2 border-none bg-transparent p-0 text-xs font-semibold text-zinc-500"
                                @click="showPassword = !showPassword"
                            >
                                {{ showPassword ? t('auth.hide') : t('auth.show') }}
                            </button>
                        </div>
                        <p v-if="clientErrors.password" class="mt-1.5 text-[12.5px] text-red-600">
                            {{ clientErrors.password }}
                        </p>
                    </div>

                    <label class="mt-px flex cursor-pointer select-none items-center gap-[9px] text-[13.5px] text-zinc-600">
                        <input v-model="form.remember" type="checkbox" class="nb-checkbox">
                        <span>{{ t('auth.keep_me_signed_in') }}</span>
                    </label>

                    <button type="submit" class="nb-submit" :disabled="form.processing">
                        <span v-if="form.processing" class="nb-spinner" aria-hidden="true" />
                        <span>{{ form.processing ? t('auth.logging_in') : t('auth.sign_in') }}</span>
                    </button>
                </form>

                <p class="mt-5 text-center text-[13px] text-zinc-500">
                    {{ t('auth.dont_have_an_account') }}
                    <Link :href="route('register')" class="nb-accent-text ms-1 font-semibold">
                        {{ t('auth.sign_up') }}
                    </Link>
                </p>
            </div>

            <div class="mt-6 flex items-center justify-between border-t border-zinc-100 pt-4">
                <span class="nb-mono flex items-center gap-1.5 text-[10.5px] text-zinc-400">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#a1a1aa" stroke-width="2.2" aria-hidden="true">
                        <rect x="4" y="10" width="16" height="11" rx="2" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                    </svg>
                    {{ t('auth.secure_connection') }}
                </span>
                <span class="nb-mono text-[10.5px] text-zinc-400">Nextbook ERP</span>
            </div>
        </div>

        <p class="mt-[22px] text-[12.5px] text-zinc-400">
            &copy; {{ currentYear }} Nextbook, Inc.
        </p>
    </div>
</template>

<style scoped>
.nb-login {
    /* Accent follows the app's active theme rather than hard-coding a brand hex. */
    --nb-accent: hsl(var(--primary));
    --nb-accent-strong: color-mix(in srgb, hsl(var(--primary)) 82%, #000);
    --nb-accent-ring: color-mix(in srgb, hsl(var(--primary)) 14%, transparent);

    box-sizing: border-box;
    font-family: 'Public Sans', 'Poppins', system-ui, -apple-system, sans-serif;
    color: #18181b;
    background-color: #f5f6f6;
    background-image: radial-gradient(circle, rgba(15, 23, 42, 0.045) 1px, transparent 1.4px);
    background-size: 22px 22px;
}

.nb-mono {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
}

.nb-card {
    box-shadow:
        0 1px 2px rgba(16, 24, 40, 0.04),
        0 18px 40px -18px rgba(16, 24, 40, 0.16);
}

.nb-accent-bg {
    background: var(--nb-accent);
}

.nb-accent-text {
    color: var(--nb-accent);
}

.nb-input {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 13px;
    font-size: 14px;
    font-family: inherit;
    color: #18181b;
    background: #fff;
    border: 1px solid #d4d4d8;
    border-radius: 9px;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.nb-input::placeholder {
    color: #a1a1aa;
}

.nb-input:focus {
    border-color: var(--nb-accent);
    box-shadow: 0 0 0 3px var(--nb-accent-ring);
}

.nb-input-error {
    border-color: #dc2626;
}

.nb-checkbox {
    width: 16px;
    height: 16px;
    accent-color: var(--nb-accent);
    cursor: pointer;
    margin: 0;
}

.nb-submit {
    margin-top: 5px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    width: 100%;
    background: var(--nb-accent);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-family: inherit;
    font-size: 14.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease;
}

.nb-submit:hover:not(:disabled) {
    background: var(--nb-accent-strong);
}

.nb-submit:disabled {
    cursor: default;
    opacity: 0.85;
}

.nb-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    border-radius: 50%;
    display: inline-block;
    animation: nb-spin 0.6s linear infinite;
}

.nb-rise {
    animation: nb-rise 0.35s ease;
}

.nb-shake {
    animation: nb-shake 0.4s ease;
}

@keyframes nb-spin {
    to { transform: rotate(360deg); }
}

@keyframes nb-rise {
    from { transform: translateY(8px); }
    to { transform: translateY(0); }
}

@keyframes nb-shake {
    10%, 90% { transform: translateX(-1px); }
    20%, 80% { transform: translateX(2px); }
    30%, 50%, 70% { transform: translateX(-4px); }
    40%, 60% { transform: translateX(4px); }
}

@media (prefers-reduced-motion: reduce) {
    .nb-rise,
    .nb-shake,
    .nb-spinner {
        animation: none;
    }
}
</style>
