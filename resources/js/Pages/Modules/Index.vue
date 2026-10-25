<script setup>
/**
 * The module list: a card per business area this user may open, its icon in
 * the module's own colour, in a bare frame (the layout's launcher mode, without sidebar, search or
 * switchers). An icon leads to the module's first page, where the full layout
 * and that module's menu take over. Same list and rules as the sidebar
 * (lib/appModules.js): permissions, modules the company switched off, menus
 * the user hid.
 */
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { ArrowRight } from 'lucide-vue-next'
import AppLayout from '@/Layouts/Layout.vue'
import { useAuth } from '@/composables/useAuth'
import { buildAppModules, moduleTone, plannedModules, urlUnder, visibleModules } from '@/lib/appModules'

const { t } = useI18n()
const { can } = useAuth()
const page = usePage()

const allowedMenus = computed(() => {
    const menus = page.props.user_preferences?.appearance?.sidebar_menus
    return Array.isArray(menus) ? new Set(menus) : null
})

const modules = computed(() => visibleModules(buildAppModules(t), {
    can,
    isOff: (url) => (page.props.modules?.disabled_paths ?? []).some((prefix) => urlUnder(url, prefix)),
    allowedMenus: allowedMenus.value,
}))

const tone = moduleTone
const planned = computed(() => plannedModules(t))

// What a card lists under its name: the module's first few pages.
const preview = (module) => module.groups
    .flatMap((group) => (group.items ? group.items : [group]))
    .slice(0, 4)
    .map((link) => link.title)
</script>

<template>
    <Head :title="t('sidebar.all_modules')" />
    <AppLayout launcher>
        <div class="w-full px-4 py-6 sm:px-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ t('sidebar.all_modules') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('sidebar.modules_hint') }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                <Link
                    v-for="module in modules"
                    :key="module.id"
                    :href="module.firstUrl"
                    class="group flex flex-col rounded-2xl border border-border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50"
                >
                    <div class="flex items-start gap-4">
                        <span
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-sm transition group-hover:scale-105"
                            :class="tone(module.id)"
                        >
                            <component :is="module.icon" class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-foreground">{{ module.title }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">{{ module.description }}</p>
                        </div>
                        <ArrowRight class="mt-1 size-4 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100 rtl:rotate-180" />
                    </div>
                    <ul class="mt-4 flex flex-wrap gap-1.5">
                        <li v-for="title in preview(module)" :key="title" class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                            {{ title }}
                        </li>
                    </ul>
                </Link>

                <!-- Planned modules: shown, not opened. -->
                <div
                    v-for="module in planned"
                    :key="module.id"
                    class="flex cursor-default flex-col rounded-2xl border border-dashed border-border bg-card/50 p-5"
                    :aria-disabled="true"
                >
                    <div class="flex items-start gap-4">
                        <span
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white opacity-60 shadow-sm grayscale-[40%]"
                            :class="tone(module.id)"
                        >
                            <component :is="module.icon" class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-foreground/80">{{ module.title }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">{{ module.description }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                            {{ t('sidebar.coming_soon') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
