<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft } from 'lucide-vue-next';
import AuthShell from '@/Layouts/AuthShell.vue';

const { t } = useI18n();
const page = usePage();

const props = defineProps({
    titleKey: { type: String, required: true },
    // Server-rendered markdown from resources/markdown — trusted, repo-owned content.
    body: { type: String, required: true },
    // The document's own language/direction, which is not necessarily the UI's:
    // an untranslated locale falls back to the English source.
    bodyLocale: { type: String, default: 'en' },
    bodyDir: { type: String, default: 'ltr' },
    updatedAt: { type: String, default: null },
});

const title = computed(() => t(props.titleKey));
const version = computed(() => page.props.app?.version);

const proseEl = ref(null);
const sections = ref([]);
const activeId = ref('');

// The markdown arrives as flat HTML, so headings get their anchors here rather
// than needing a CommonMark extension server side.
const slugify = (text) => text
    .toLowerCase()
    .replace(/[^a-z0-9؀-ۿ]+/g, '-')
    .replace(/^-+|-+$/g, '');

let scrollFrame = null;

const syncActiveSection = () => {
    if (scrollFrame) return;

    scrollFrame = requestAnimationFrame(() => {
        scrollFrame = null;

        const headings = sections.value
            .map(({ id }) => document.getElementById(id))
            .filter(Boolean);

        // The heading nearest to — but not past — the reading line is active.
        const current = headings.filter((el) => el.getBoundingClientRect().top <= 140).pop();

        activeId.value = current?.id ?? headings[0]?.id ?? '';
    });
};

const scrollTo = (id) => {
    const el = document.getElementById(id);
    if (!el) return;

    window.scrollTo({
        top: el.getBoundingClientRect().top + window.scrollY - 96,
        behavior: 'smooth',
    });
};

onMounted(() => {
    const headings = proseEl.value?.querySelectorAll('h2') ?? [];

    sections.value = Array.from(headings).map((el) => {
        const label = el.textContent.trim();
        el.id = el.id || slugify(label);

        // Strip the leading "1. " so the contents list reads as a clean index.
        // Covers Arabic-Indic and Persian digits, and the Arabic full stop.
        const numbering = /^[\d٠-٩۰-۹]+\s*[.۔)]\s*/;

        return { id: el.id, label: label.replace(numbering, '') };
    });

    syncActiveSection();
    window.addEventListener('scroll', syncActiveSection, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', syncActiveSection);
    if (scrollFrame) cancelAnimationFrame(scrollFrame);
});
</script>

<template>
    <Head :title="title">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap"
            rel="stylesheet"
        >
    </Head>

    <AuthShell>
        <div class="nb-legal">
            <Link :href="route('login')" class="nb-back">
                <ArrowLeft class="nb-back-icon" />
                <span>{{ t('auth.back_to_sign_in') }}</span>
            </Link>

            <article class="nb-card nb-legal-card">
                <header class="nb-legal-head">
                    <h1 class="nb-legal-title">{{ title }}</h1>
                    <p class="nb-legal-meta">
                        <span v-if="updatedAt" class="nb-legal-badge nb-mono">
                            {{ t('auth.last_updated') }} {{ updatedAt }}
                        </span>
                        <span v-if="version" class="nb-legal-badge nb-mono">
                            {{ t('auth.version') }} {{ version }}
                        </span>
                    </p>
                </header>

                <div class="nb-legal-body">
                    <nav v-if="sections.length" class="nb-toc" :aria-label="t('auth.on_this_page')">
                        <p class="nb-toc-label nb-mono">{{ t('auth.on_this_page') }}</p>
                        <ol class="nb-toc-list">
                            <li v-for="(section, index) in sections" :key="section.id">
                                <button
                                    type="button"
                                    class="nb-toc-link"
                                    :class="{ 'is-active': activeId === section.id }"
                                    @click="scrollTo(section.id)"
                                >
                                    <span class="nb-toc-num nb-mono">{{ index + 1 }}</span>
                                    <span>{{ section.label }}</span>
                                </button>
                            </li>
                        </ol>
                    </nav>

                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div ref="proseEl" class="nb-prose" :dir="bodyDir" :lang="bodyLocale" v-html="body" />
                </div>
            </article>
        </div>
    </AuthShell>
</template>

<style scoped>
/*
  Layout lives in real CSS rather than Tailwind utilities: this page is bespoke,
  and it keeps the column width independent of JIT class scanning.
*/
.nb-legal {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 1000px;
}

.nb-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 16px;
    padding-block: 6px;
    padding-inline: 9px 12px;
    border: 1px solid var(--nb-card-border);
    border-radius: 999px;
    background: var(--nb-card-bg);
    color: var(--nb-foot-strong);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}

.nb-back:hover {
    color: var(--nb-accent);
    border-color: var(--nb-accent);
    transform: translateX(-2px);
}

.nb-back-icon {
    width: 15px;
    height: 15px;
}

[dir='rtl'] .nb-back-icon {
    transform: scaleX(-1);
}

[dir='rtl'] .nb-back:hover {
    transform: translateX(2px);
}

.nb-legal-card {
    border-width: 1px;
    border-style: solid;
    border-radius: 16px;
    padding: 36px 40px 44px;
}

.nb-legal-head {
    padding-bottom: 22px;
    border-bottom: 1px solid var(--nb-prose-rule);
}

.nb-legal-title {
    margin: 0;
    font-size: 27px;
    font-weight: 700;
    letter-spacing: -0.025em;
    color: var(--nb-prose-heading);
}

.nb-legal-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 14px 0 0;
}

.nb-legal-badge {
    padding: 3px 9px;
    border: 1px solid var(--nb-prose-rule);
    border-radius: 6px;
    background: var(--nb-input-bg);
    color: var(--nb-foot);
    font-size: 10.5px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.nb-legal-body {
    display: grid;
    grid-template-columns: 216px minmax(0, 1fr);
    gap: 44px;
    margin-top: 30px;
}

/* Table of contents */
.nb-toc {
    position: sticky;
    top: 88px;
    align-self: start;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
}

.nb-toc-label {
    margin: 0 0 12px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--nb-foot);
}

.nb-toc-list {
    margin: 0;
    padding: 0;
    list-style: none;
    border-inline-start: 1px solid var(--nb-prose-rule);
}

.nb-toc-link {
    display: flex;
    gap: 8px;
    width: 100%;
    padding: 6px 10px;
    margin-inline-start: -1px;
    border: 0;
    border-inline-start: 1px solid transparent;
    background: none;
    color: var(--nb-foot);
    font-family: inherit;
    font-size: 12.5px;
    line-height: 1.45;
    text-align: start;
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease;
}

.nb-toc-link:hover {
    color: var(--nb-prose-heading);
}

.nb-toc-link.is-active {
    color: var(--nb-accent);
    border-inline-start-color: var(--nb-accent);
    font-weight: 600;
}

.nb-toc-num {
    flex: none;
    opacity: 0.55;
    font-size: 11px;
}

/* Document body */
.nb-prose {
    color: var(--nb-prose-body);
    font-size: 14.5px;
    line-height: 1.72;
    max-width: 68ch;
}

.nb-prose :deep(h1) {
    /* The page header already renders the document's H1. */
    display: none;
}

.nb-prose :deep(h2) {
    margin: 40px 0 12px;
    padding-top: 4px;
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.015em;
    line-height: 1.35;
    color: var(--nb-prose-heading);
    scroll-margin-top: 96px;
}

.nb-prose :deep(h2:first-child) {
    margin-top: 0;
}

.nb-prose :deep(h3) {
    margin: 26px 0 8px;
    font-size: 14.5px;
    font-weight: 600;
    color: var(--nb-prose-heading);
}

.nb-prose :deep(p) {
    margin: 0 0 15px;
}

.nb-prose :deep(ul),
.nb-prose :deep(ol) {
    margin: 0 0 18px;
    padding-inline-start: 22px;
}

.nb-prose :deep(li) {
    margin-bottom: 8px;
    padding-inline-start: 4px;
}

.nb-prose :deep(li)::marker {
    color: var(--nb-accent);
}

.nb-prose :deep(strong) {
    font-weight: 600;
    color: var(--nb-prose-heading);
}

.nb-prose :deep(a) {
    color: var(--nb-accent);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.nb-prose :deep(hr) {
    margin: 32px 0;
    border: 0;
    border-top: 1px solid var(--nb-prose-rule);
}

.nb-prose :deep(code) {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 12.5px;
    padding: 1px 5px;
    border-radius: 5px;
    background: var(--nb-input-bg);
    border: 1px solid var(--nb-prose-rule);
}

/* Tables scroll inside their own box so the page never scrolls sideways. */
.nb-prose :deep(table) {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    margin: 0 0 20px;
    border-collapse: collapse;
    font-size: 13px;
}

.nb-prose :deep(thead) {
    background: var(--nb-input-bg);
}

.nb-prose :deep(th),
.nb-prose :deep(td) {
    padding: 9px 12px;
    text-align: start;
    vertical-align: top;
    border: 1px solid var(--nb-prose-rule);
    white-space: normal;
}

.nb-prose :deep(th) {
    font-weight: 600;
    font-size: 11px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--nb-prose-heading);
}

/*
  These three carry translated copy. In Arabic script the mono face has no
  glyphs, uppercase is a no-op, and tracking breaks the cursive joining — so
  they fall back to the shell's RTL stack with neutral spacing.
*/
[dir='rtl'] .nb-legal-badge,
[dir='rtl'] .nb-toc-label,
[dir='rtl'] .nb-legal-title {
    font-family: inherit;
    letter-spacing: normal;
    text-transform: none;
}

@media (max-width: 900px) {
    .nb-legal-card {
        padding: 28px 24px 34px;
    }

    .nb-legal-body {
        grid-template-columns: minmax(0, 1fr);
        gap: 26px;
    }

    .nb-toc {
        position: static;
        max-height: none;
        padding-bottom: 22px;
        border-bottom: 1px solid var(--nb-prose-rule);
    }

    .nb-prose {
        max-width: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .nb-back {
        transition: none;
    }

    .nb-back:hover {
        transform: none;
    }
}
</style>
