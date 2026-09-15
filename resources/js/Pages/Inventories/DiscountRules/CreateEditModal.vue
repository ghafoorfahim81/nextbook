<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import NextSelect from '@/Components/next/NextSelect.vue';
import NextDate from '@/Components/next/NextDatePicker.vue';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object,
    options: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:isDialogOpen', 'saved']);
const { t } = useI18n();

const open = ref(props.isDialogOpen);
const isEditing = computed(() => Boolean(props.editingItem?.id));

const blank = {
    name: '',
    scope: 'item',
    scope_id: null,
    discount_type: 'percentage',
    value: '',
    customer_group_id: null,
    min_quantity: '',
    starts_at: '',
    ends_at: '',
    priority: 0,
    is_active: true,
    show_on_invoice: true,
};

const form = useForm({ ...blank });

// The target list follows the scope: naming an item, a category or a brand
// draws from a different table, and "all items" needs no target at all.
const targetOptions = computed(() => ({
    item: props.options.items,
    category: props.options.categories,
    brand: props.options.brands,
}[form.scope] || []));

const needsTarget = computed(() => form.scope !== 'all');

const targetLabel = computed(() => ({
    item: t('item.item'),
    category: t('admin.category.category'),
    brand: t('admin.brand.brand'),
}[form.scope] || t('discount_rule.target')));

watch(() => props.isDialogOpen, (value) => { open.value = value; });

watch(() => props.editingItem, (item) => {
    form.clearErrors();
    form.defaults({ ...blank, ...(item || {}) }).reset();
}, { immediate: true });

watch(open, (value) => emit('update:isDialogOpen', value));

// Switching to "all items" clears a target that no longer means anything.
watch(() => form.scope, () => { if (! needsTarget.value) form.scope_id = null; });

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); open.value = false; form.reset(); },
    };

    isEditing.value
        ? form.patch(route('discount-rules.update', props.editingItem.id), options)
        : form.post(route('discount-rules.store'), options);
};
</script>

<template>
    <ModalDialog
        :open="open"
        :title="isEditing ? t('discount_rule.edit') : t('discount_rule.new')"
        :confirm-text="isEditing ? t('general.update') : t('general.create')"
        :submitting="form.processing"
        @update:open="open = $event"
        @confirm="submit"
    >
        <form class="grid gap-4 py-2" @submit.prevent="submit">
            <NextInput
                is-required
                :label="t('general.name')"
                v-model="form.name"
                :error="form.errors.name"
                :hint="t('discount_rule.name_hint')"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <NextSelect
                    :floating-text="t('discount_rule.applies_to')"
                    v-model="form.scope"
                    :options="options.scopes"
                    label-key="name"
                    value-key="id"
                    :reduce="option => option.id"
                    :clearable="false"
                    :error="form.errors.scope"
                />
                <NextSelect
                    v-if="needsTarget"
                    :floating-text="targetLabel"
                    v-model="form.scope_id"
                    :options="targetOptions"
                    label-key="name"
                    value-key="id"
                    :reduce="option => option.id"
                    :searchable="true"
                    :error="form.errors.scope_id"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <NextSelect
                    :floating-text="t('general.discount_type')"
                    v-model="form.discount_type"
                    :options="options.discountTypes"
                    label-key="name"
                    value-key="id"
                    :reduce="option => option.id"
                    :clearable="false"
                    :error="form.errors.discount_type"
                />
                <NextInput
                    is-required
                    type="number"
                    step="any"
                    inputmode="decimal"
                    :label="t('general.discount')"
                    v-model="form.value"
                    :error="form.errors.value"
                />
            </div>

            <NextSelect
                :floating-text="t('discount_rule.customer_group')"
                v-model="form.customer_group_id"
                :options="options.customerGroups"
                label-key="name"
                value-key="id"
                :reduce="option => option.id"
                :error="form.errors.customer_group_id"
            />
            <p class="-mt-2 text-xs text-muted-foreground">{{ t('discount_rule.customer_group_hint') }}</p>

            <div class="grid gap-4 sm:grid-cols-3">
                <NextInput
                    type="number"
                    step="any"
                    inputmode="decimal"
                    :label="t('discount_rule.min_quantity')"
                    v-model="form.min_quantity"
                    :error="form.errors.min_quantity"
                />
                <NextDate
                    :label="t('discount_rule.starts_at')"
                    v-model="form.starts_at"
                    :lock-future-dates="false"
                    :error="form.errors.starts_at"
                />
                <NextDate
                    :label="t('discount_rule.ends_at')"
                    v-model="form.ends_at"
                    :lock-future-dates="false"
                    :error="form.errors.ends_at"
                />
            </div>

            <NextInput
                type="number"
                :label="t('discount_rule.priority')"
                v-model="form.priority"
                :error="form.errors.priority"
                :hint="t('discount_rule.priority_hint')"
            />

            <div class="flex flex-wrap items-center gap-6 pt-1">
                <div class="flex items-center gap-2">
                    <Checkbox id="rule-active" :checked="form.is_active" @update:checked="form.is_active = $event" />
                    <Label for="rule-active" class="cursor-pointer font-normal">{{ t('general.is_active') }}</Label>
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="rule-invoice" :checked="form.show_on_invoice" @update:checked="form.show_on_invoice = $event" />
                    <Label for="rule-invoice" class="cursor-pointer font-normal">{{ t('discount_rule.show_on_invoice') }}</Label>
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
