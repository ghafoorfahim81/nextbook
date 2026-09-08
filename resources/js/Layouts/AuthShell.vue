<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useColorMode, useMediaQuery } from '@vueuse/core';
import { Moon, Sun } from 'lucide-vue-next';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const { t } = useI18n();

// Standalone theme switch for the guest screens — the app layout owns the same
// preference once the user is signed in, so we reuse the shared color mode.
const mode = useColorMode({ emitAuto: true });
const prefersDark = useMediaQuery('(prefers-color-scheme: dark)');
const isDark = computed(() => mode.value === 'dark' || (mode.value === 'auto' && prefersDark.value));

const toggleTheme = () => {
    mode.value = isDark.value ? 'light' : 'dark';
};
</script>

<template>
    <div class="nb-login relative flex min-h-screen flex-col items-center px-6 pb-16 pt-[104px]" :class="{ 'nb-dark': isDark }">
        <!-- Top bar: brand + language + theme -->
        <div class="absolute inset-x-0 top-0 flex h-16 items-center justify-between px-6 sm:px-8">
            <Link :href="route('login')" class="flex items-center gap-[9px] no-underline">
                <div class="nb-accent-bg nb-mono flex h-[29px] w-[29px] items-center justify-center rounded-[8px] text-base font-semibold text-white">
                    N
                </div>
                <span class="nb-brand text-base font-bold tracking-[-0.02em]">Nextbook</span>
                <span class="nb-brand-tag nb-mono ms-0.5 rounded-[5px] border px-[5px] py-[2px] text-[9.5px] font-medium tracking-[0.12em]">
                    ERP
                </span>
            </Link>

            <div class="flex items-center gap-2.5">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="nb-muted-icon" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
                </svg>
                <LanguageSwitcher />
                <button
                    type="button"
                    class="nb-theme-toggle"
                    :aria-label="isDark ? t('auth.switch_to_light') : t('auth.switch_to_dark')"
                    @click="toggleTheme"
                >
                    <Moon v-if="isDark" class="h-[17px] w-[17px]" />
                    <Sun v-else class="h-[17px] w-[17px]" />
                </button>
            </div>
        </div>

        <slot />

        <!-- Footer -->
        <div class="relative z-10 mt-9 flex flex-col items-center gap-3 text-center">
            <div class="flex items-center gap-2.5" aria-hidden="true">
                <span v-for="i in 5" :key="i" class="nb-suite-dot" :style="{ '--d': i }" />
            </div>
            <p class="nb-foot-strong text-[13px] font-semibold">
                {{ t('auth.tagline') }}
            </p>
            <p class="nb-foot flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 text-[12px]">
                <Link :href="route('terms.show')" class="nb-foot-link">{{ t('auth.terms') }}</Link>
                <span class="nb-foot-sep" aria-hidden="true">|</span>
                <Link :href="route('policy.show')" class="nb-foot-link">{{ t('auth.privacy_policy') }}</Link>
                <span class="nb-foot-sep" aria-hidden="true">|</span>
                <span>{{ t('auth.version') }} {{ $page.props.app?.version }}</span>
            </p>
        </div>
    </div>
</template>

<!--
  Deliberately unscoped: the sign-in card and the legal pages render their own
  markup in the default slot, and slotted content never carries this component's
  scope id. Every selector is namespaced under .nb-login, so nothing leaks.
-->
<style>
.nb-login {
    /* Accent follows the app's active theme rather than hard-coding a brand hex. */
    --nb-accent: hsl(var(--primary));
    --nb-accent-strong: color-mix(in srgb, hsl(var(--primary)) 82%, #000);
    --nb-accent-ring: color-mix(in srgb, hsl(var(--primary)) 16%, transparent);

    /* Light theme tokens */
    --nb-bg: #f1eefb;
    --nb-glow: radial-gradient(ellipse 42% 26% at 50% 62%, rgba(139, 92, 246, 0.16), transparent 72%);
    --nb-dots: rgba(80, 50, 160, 0.05);
    --nb-card-bg: #ffffff;
    --nb-card-border: #ffffff;
    --nb-card-shadow: 0 1px 3px rgba(24, 24, 40, 0.04), 0 26px 55px -22px rgba(76, 45, 168, 0.22);
    --nb-brand: #18181b;
    --nb-brand-tag-border: #e4e4e7;
    --nb-brand-tag-text: #a1a1aa;
    --nb-muted-icon: #a1a1aa;
    --nb-input-bg: #ffffff;
    --nb-input-border: #d9d7e2;
    --nb-input-text: #18181b;
    --nb-input-placeholder: #a1a1aa;
    --nb-toggle-text: #6b6b78;
    --nb-toggle-hover-bg: rgba(24, 24, 40, 0.06);
    --nb-foot: #8b8b96;
    --nb-foot-strong: #52525b;
    --nb-suite-a: #a78bfa;
    --nb-suite-b: #7c3aed;
    --nb-prose-heading: #18181b;
    --nb-prose-body: #52525b;
    --nb-prose-rule: #ececf1;

    box-sizing: border-box;
    font-family: 'Public Sans', 'Poppins', system-ui, -apple-system, sans-serif;
    color: var(--nb-input-text);
    background-color: var(--nb-bg);
    background-image: var(--nb-glow);
    background-attachment: fixed;
    transition: background-color 0.25s ease;
}

/*
  RTL locales (fa, ps). Two things break if this is left alone:
  app.css sets the Arabic-script face on `[dir="rtl"] body`, but .nb-login
  re-declares font-family and would win — and Public Sans has no Arabic
  coverage. Tracking, meanwhile, severs the cursive joining that Arabic script
  depends on, so it is neutralised for everything that inherits text here.
*/
[dir='rtl'] .nb-login {
    font-family: 'iranyekan', 'Noto Sans Arabic', 'Segoe UI', Tahoma, sans-serif;
    letter-spacing: normal;
}

[dir='rtl'] .nb-login * {
    letter-spacing: inherit;
}

/* Latin regardless of locale, so it keeps its tracking. */
[dir='rtl'] .nb-login .nb-brand-tag {
    letter-spacing: 0.12em;
}

.nb-login::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle, var(--nb-dots) 1px, transparent 1.4px);
    background-size: 24px 24px;
    pointer-events: none;
}

.nb-login.nb-dark {
    /* Matched to Proton's auth backdrop: a near-black ground with a soft violet
       bloom sitting under the card, fading out again before the bottom edge. */
    --nb-bg: #0e0c12;
    --nb-glow:
        radial-gradient(ellipse 38% 22% at 50% 62%, rgba(118, 82, 255, 0.30) 0%, rgba(110, 76, 240, 0.14) 48%, transparent 76%),
        radial-gradient(ellipse 62% 40% at 50% 64%, rgba(72, 46, 150, 0.22) 0%, transparent 72%),
        linear-gradient(148deg, rgba(140, 120, 255, 0.045) 0%, transparent 38%);
    --nb-dots: rgba(255, 255, 255, 0.02);
    --nb-card-bg: #1c1a21;
    --nb-card-border: rgba(255, 255, 255, 0.055);
    --nb-card-shadow: 0 1px 3px rgba(0, 0, 0, 0.45), 0 36px 68px -28px rgba(0, 0, 0, 0.7);
    --nb-brand: #f4f4f5;
    --nb-brand-tag-border: rgba(255, 255, 255, 0.14);
    --nb-brand-tag-text: #8b8b96;
    --nb-muted-icon: #8b8b96;
    --nb-input-bg: #141319;
    --nb-input-border: rgba(255, 255, 255, 0.14);
    --nb-input-text: #f4f4f5;
    --nb-input-placeholder: #75727f;
    --nb-toggle-text: #a1a1aa;
    --nb-toggle-hover-bg: rgba(255, 255, 255, 0.08);
    --nb-foot: #7c7a87;
    --nb-foot-strong: #d4d4d8;
    --nb-suite-a: #8b5cf6;
    --nb-suite-b: #6d28d9;
    --nb-prose-heading: #f4f4f5;
    --nb-prose-body: #a1a1aa;
    --nb-prose-rule: rgba(255, 255, 255, 0.08);
}

.nb-login .nb-mono {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
}

.nb-login .nb-brand {
    color: var(--nb-brand);
}

.nb-login .nb-brand-tag {
    border-color: var(--nb-brand-tag-border);
    color: var(--nb-brand-tag-text);
}

.nb-login .nb-muted-icon {
    color: var(--nb-muted-icon);
}

.nb-login .nb-theme-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: var(--nb-toggle-text);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.nb-login .nb-theme-toggle:hover {
    background: var(--nb-toggle-hover-bg);
    color: var(--nb-accent);
}

.nb-login .nb-card {
    background: var(--nb-card-bg);
    border-color: var(--nb-card-border);
    box-shadow: var(--nb-card-shadow);
    transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
}

.nb-login .nb-accent-bg {
    background: var(--nb-accent);
}

.nb-login .nb-accent-text {
    color: var(--nb-accent);
}

.nb-login .nb-suite-dot {
    width: 22px;
    height: 22px;
    border-radius: 7px;
    background: linear-gradient(150deg, var(--nb-suite-a), var(--nb-suite-b));
    opacity: 0.85;
    transform: rotate(calc(var(--d) * 4deg - 10deg));
}

.nb-login .nb-foot {
    color: var(--nb-foot);
}

.nb-login .nb-foot-strong {
    color: var(--nb-foot-strong);
}

.nb-login .nb-foot-link {
    color: var(--nb-foot);
    text-decoration: underline;
    text-underline-offset: 2px;
    transition: color 0.15s ease;
}

.nb-login .nb-foot-link:hover {
    color: var(--nb-accent);
}

.nb-login .nb-foot-sep {
    opacity: 0.45;
}

/* Dark-mode fixes for the Tailwind text utilities used in slotted card markup. */
.nb-login.nb-dark .text-zinc-900 { color: #f4f4f5; }
.nb-login.nb-dark .text-zinc-700 { color: #d4d4d8; }
.nb-login.nb-dark .text-zinc-600 { color: #a1a1aa; }
.nb-login.nb-dark .text-zinc-500 { color: #8b8b96; }
.nb-login.nb-dark .bg-emerald-50 { background-color: rgba(16, 185, 129, 0.12); }
.nb-login.nb-dark .border-emerald-200 { border-color: rgba(16, 185, 129, 0.3); }
.nb-login.nb-dark .text-emerald-700 { color: #6ee7b7; }
.nb-login.nb-dark .bg-red-50 { background-color: rgba(220, 38, 38, 0.12); }
.nb-login.nb-dark .border-red-200 { border-color: rgba(220, 38, 38, 0.32); }
.nb-login.nb-dark .text-red-700,
.nb-login.nb-dark .text-red-600 { color: #fca5a5; }

.nb-login .nb-rise {
    animation: nb-rise 0.35s ease;
}

@keyframes nb-rise {
    from { transform: translateY(8px); }
    to { transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
    .nb-login .nb-rise {
        animation: none;
    }
}
</style>
