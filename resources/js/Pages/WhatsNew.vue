<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useI18n } from 'vue-i18n'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
    Rocket,
    FilePenLine,
    CheckCircle2,
    Undo2,
    MessageSquareWarning,
    Palette,
    PackageSearch,
    Link2,
    ArrowRight,
    ShoppingCart,
    ShoppingBag,
    Receipt,
    Landmark,
    BookText,
    ArrowLeftRight,
    Wallet,
    HandCoins,
    Repeat,
} from 'lucide-vue-next'

const { t } = useI18n()

const flowSteps = [
    { icon: FilePenLine, key: 'flow_step1', color: 'text-slate-500 bg-slate-500/10' },
    { icon: CheckCircle2, key: 'flow_step2', color: 'text-emerald-500 bg-emerald-500/10' },
    { icon: Undo2, key: 'flow_step3', color: 'text-red-500 bg-red-500/10' },
]

const features = [
    { icon: MessageSquareWarning, key: 'feature1' },
    { icon: Palette, key: 'feature2' },
    { icon: PackageSearch, key: 'feature3' },
    { icon: Link2, key: 'feature4' },
]

const beforeAfter = [1, 2, 3, 4]

const modules = [
    { icon: ShoppingCart, name: 'Sales' },
    { icon: ShoppingBag, name: 'Purchases' },
    { icon: Receipt, name: 'Receipts' },
    { icon: Wallet, name: 'Payments' },
    { icon: BookText, name: 'Journal Entries' },
    { icon: ArrowLeftRight, name: 'Account Transfers' },
    { icon: HandCoins, name: 'Expenses' },
    { icon: Repeat, name: 'Item Transfers' },
    { icon: Landmark, name: 'Owner Drawings' },
]
</script>

<template>
    <AppLayout :title="t('whats_new.page_title')">
        <div class="mx-auto space-y-10 px-4 py-8 sm:px-6 lg:px-8">
            <!-- Hero -->
            <div class="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-primary/15 via-nextbook-lavender-100 to-background p-8 dark:from-primary/20 dark:via-background dark:to-background sm:p-12">
                <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-primary/20 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-20 -left-10 h-56 w-56 rounded-full bg-nextbook-yellow-400/20 blur-3xl"></div>

                <div class="relative flex flex-col items-start gap-4">
                    <Badge class="gap-1.5 bg-primary/15 text-primary hover:bg-primary/15">
                        <Rocket class="h-3.5 w-3.5" />
                        {{ t('whats_new.eyebrow') }}
                    </Badge>
                    <h1 class="max-w-2xl text-3xl font-bold leading-tight text-foreground sm:text-4xl">
                        {{ t('whats_new.hero_title') }}
                    </h1>
                    <p class="max-w-2xl text-base text-muted-foreground sm:text-lg">
                        {{ t('whats_new.hero_subtitle') }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <Link :href="route('sales.index')">
                            <Button class="gap-1.5">
                                {{ t('whats_new.cta_explore') }}
                                <ArrowRight class="h-4 w-4 rtl:rotate-180" />
                            </Button>
                        </Link>
                        <Link :href="route('dashboard')">
                            <Button variant="outline">{{ t('whats_new.cta_back') }}</Button>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Flow -->
            <div>
                <h2 class="text-xl font-semibold text-foreground">{{ t('whats_new.flow_title') }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('whats_new.flow_subtitle') }}</p>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <Card v-for="(step, index) in flowSteps" :key="step.key" class="relative">
                        <CardContent class="flex flex-col gap-3 p-5">
                            <div class="flex items-center justify-between">
                                <div :class="['flex h-10 w-10 items-center justify-center rounded-lg', step.color]">
                                    <component :is="step.icon" class="h-5 w-5" />
                                </div>
                                <span class="text-xs font-medium text-muted-foreground">0{{ index + 1 }}</span>
                            </div>
                            <h3 class="font-semibold text-foreground">{{ t(`whats_new.${step.key}_title`) }}</h3>
                            <p class="text-sm text-muted-foreground">{{ t(`whats_new.${step.key}_desc`) }}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Before / After -->
            <div>
                <h2 class="text-xl font-semibold text-foreground">{{ t('whats_new.before_title') }}</h2>
                <div class="mt-6 space-y-3">
                    <div
                        v-for="n in beforeAfter"
                        :key="n"
                        class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2"
                    >
                        <div class="flex items-start gap-3 rounded-lg bg-muted/60 p-3">
                            <Badge variant="outline" class="mt-0.5 shrink-0 border-red-400/50 text-red-500">
                                {{ t('whats_new.before_label') }}
                            </Badge>
                            <p class="text-sm text-muted-foreground">{{ t(`whats_new.before_${n}`) }}</p>
                        </div>
                        <div class="flex items-start gap-3 rounded-lg bg-emerald-500/5 p-3">
                            <Badge class="mt-0.5 shrink-0 bg-emerald-500/15 text-emerald-600 hover:bg-emerald-500/15">
                                {{ t('whats_new.after_label') }}
                            </Badge>
                            <p class="text-sm text-foreground">{{ t(`whats_new.after_${n}`) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature grid -->
            <div>
                <h2 class="text-xl font-semibold text-foreground">{{ t('whats_new.features_title') }}</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <Card v-for="feature in features" :key="feature.key">
                        <CardContent class="flex gap-4 p-5">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <component :is="feature.icon" class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-foreground">{{ t(`whats_new.${feature.key}_title`) }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">{{ t(`whats_new.${feature.key}_desc`) }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Modules -->
            <div>
                <h2 class="text-xl font-semibold text-foreground">{{ t('whats_new.modules_title') }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('whats_new.modules_subtitle') }}</p>
                <div class="mt-5 flex flex-wrap gap-2.5">
                    <span
                        v-for="mod in modules"
                        :key="mod.name"
                        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-1.5 text-sm text-foreground"
                    >
                        <component :is="mod.icon" class="h-4 w-4 text-primary" />
                        {{ mod.name }}
                    </span>
                </div>
            </div>

            <p class="border-t border-border pt-6 text-center text-sm text-muted-foreground">
                {{ t('whats_new.footer_note') }}
            </p>
        </div>
    </AppLayout>
</template>
