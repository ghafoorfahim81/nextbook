<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthShell from '@/Layouts/AuthShell.vue';
import { useSoundPreferences } from '@/composables/useSoundPreferences';

const { t } = useI18n();
const { play } = useSoundPreferences();

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
        // Fires after the post-login page's props (including the user's sound
        // preference) have landed, so the right choice plays for that user.
        onSuccess: () => play('login'),
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

    <AuthShell>
        <div class="nb-card relative z-10 w-full max-w-[430px] rounded-[16px] border px-9 pb-[26px] pt-9">
            <div class="nb-rise">
                <h1 class="mb-1.5 text-[22px] font-bold tracking-[-0.025em] text-zinc-900">
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
        </div>
    </AuthShell>
</template>

<style scoped>
/* Form chrome only — the page shell, palette tokens and footer live in AuthShell. */
.nb-input {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 13px;
    font-size: 14px;
    font-family: inherit;
    color: var(--nb-input-text);
    background: var(--nb-input-bg);
    border: 1px solid var(--nb-input-border);
    border-radius: 9px;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.25s ease, color 0.25s ease;
}

.nb-input::placeholder {
    color: var(--nb-input-placeholder);
}

.nb-input:focus {
    border-color: var(--nb-accent);
    box-shadow: 0 0 0 3px var(--nb-accent-ring);
}

.nb-input-error {
    border-color: #dc2626;
}

/* Keep autofilled fields on-theme (WebKit paints them white regardless). */
.nb-input:-webkit-autofill,
.nb-input:-webkit-autofill:hover,
.nb-input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--nb-input-text);
    -webkit-box-shadow: 0 0 0 1000px var(--nb-input-bg) inset;
    caret-color: var(--nb-input-text);
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

.nb-shake {
    animation: nb-shake 0.4s ease;
}

@keyframes nb-spin {
    to { transform: rotate(360deg); }
}

@keyframes nb-shake {
    10%, 90% { transform: translateX(-1px); }
    20%, 80% { transform: translateX(2px); }
    30%, 50%, 70% { transform: translateX(-4px); }
    40%, 60% { transform: translateX(4px); }
}

@media (prefers-reduced-motion: reduce) {
    .nb-shake,
    .nb-spinner {
        animation: none;
    }
}
</style>
