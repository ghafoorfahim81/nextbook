<script setup>
/**
 * A brand carries a dozen fields — legal name, registration number, contacts,
 * address — and the list can only show four of them without becoming
 * unreadable. This is where the rest of them live.
 */
import AppLayout from '@/Layouts/Layout.vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import FormPageToolbar from '@/Components/FormPageToolbar.vue';
import { Button } from '@/Components/ui/button';
import { SquarePen, Building2, Globe, Mail, Phone, MapPin } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';

const props = defineProps({
    brand: Object,
});

const { t } = useI18n();
const { can } = useAuth();

const brand = computed(() => props.brand?.data ?? props.brand ?? {});

// Only fields with something in them are rendered: a detail page full of
// dashes tells the reader nothing and hides the fields that are filled.
const details = computed(() => [
    { label: t('admin.brand.legal_name'), value: brand.value.legal_name },
    { label: t('admin.brand.registration_number'), value: brand.value.registration_number },
    { label: t('admin.brand.industry'), value: brand.value.industry },
    { label: t('admin.brand.type'), value: brand.value.type },
    { label: t('general.email'), value: brand.value.email, icon: Mail },
    { label: t('general.phone'), value: brand.value.phone, icon: Phone },
    { label: t('admin.brand.website'), value: brand.value.website, icon: Globe, href: brand.value.website },
    { label: t('admin.brand.address'), value: brand.value.address, icon: MapPin },
    { label: t('admin.brand.city'), value: brand.value.city },
    { label: t('admin.brand.country'), value: brand.value.country },
].filter((row) => row.value !== null && row.value !== undefined && String(row.value).trim() !== ''));

const audit = computed(() => [
    { label: t('general.created_by'), value: brand.value.created_by?.name },
    { label: t('general.updated_by'), value: brand.value.updated_by?.name },
].filter((row) => row.value));

const normalizedHref = (url) => (/^https?:\/\//i.test(url) ? url : `https://${url}`);

const editBrand = () => router.visit(route('brands.index', { edit: brand.value.id }));
</script>

<template>
    <AppLayout :title="brand.name">
        <FormPageToolbar back-route="brands.index" module="brands" />

        <div class="space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <img
                        v-if="brand.logo"
                        :src="brand.logo"
                        :alt="brand.name"
                        class="h-14 w-14 rounded-lg border border-border object-contain bg-background"
                    />
                    <div v-else class="flex h-14 w-14 items-center justify-center rounded-lg border border-border bg-muted">
                        <Building2 class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold">{{ brand.name }}</h1>
                        <p v-if="brand.legal_name" class="text-sm text-muted-foreground">{{ brand.legal_name }}</p>
                    </div>
                </div>

                <Button v-if="can('brands.update')" variant="outline" size="sm" class="gap-1.5" @click="editBrand">
                    <SquarePen class="h-3.5 w-3.5" />
                    {{ t('datatable.edit') }}
                </Button>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-muted-foreground">{{ t('general.details') }}</h2>

                <dl v-if="details.length" class="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="row in details" :key="row.label">
                        <dt class="text-xs text-muted-foreground">{{ row.label }}</dt>
                        <dd class="mt-0.5 flex items-center gap-1.5 text-sm">
                            <component :is="row.icon" v-if="row.icon" class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            <a
                                v-if="row.href"
                                :href="normalizedHref(row.href)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-primary hover:underline"
                            >{{ row.value }}</a>
                            <span v-else>{{ row.value }}</span>
                        </dd>
                    </div>
                </dl>

                <p v-else class="text-sm text-muted-foreground">{{ t('general.no_results') }}</p>
            </div>

            <div v-if="audit.length" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div v-for="row in audit" :key="row.label">
                        <dt class="text-xs text-muted-foreground">{{ row.label }}</dt>
                        <dd class="mt-0.5 text-sm">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
