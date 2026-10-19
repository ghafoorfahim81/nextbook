<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ModalDialog from '@/Components/next/Dialog.vue';
import NextInput from '@/Components/next/NextInput.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({ isDialogOpen: Boolean, editingItem: Object });
const emit = defineEmits(['update:isDialogOpen', 'saved']);
const { t } = useI18n();
const open = ref(props.isDialogOpen);
const isEditing = computed(() => Boolean(props.editingItem?.id));
const form = useForm({ name: '', days: 0, type: '' });

watch(() => props.isDialogOpen, (value) => { open.value = value; });
watch(() => props.editingItem, (item) => {
    form.reset();
    if (item) form.defaults(item).reset();
}, { immediate: true });
watch(open, (value) => emit('update:isDialogOpen', value));

const submit = () => {
    const options = { onSuccess: () => { emit('saved'); open.value = false; form.reset(); } };
    isEditing.value
        ? form.patch(route('payment-terms.update', props.editingItem.id), options)
        : form.post(route('payment-terms.store'), options);
};
</script>

<template>
    <ModalDialog :open="open" :title="isEditing ? t('general.edit', { name: t('admin.payment_term.payment_term') }) : t('general.new', { name: t('admin.payment_term.payment_term') })" :confirm-text="isEditing ? t('general.update') : t('general.save')" :submitting="form.processing" @update:open="open = $event" @confirm="submit">
        <form class="grid gap-4 py-4" @submit.prevent="submit">
            <NextInput is-required :label="t('admin.payment_term.name')" v-model="form.name" :error="form.errors.name" />
            <NextInput is-required type="number" min="0" :label="t('admin.payment_term.days')" v-model="form.days" :error="form.errors.days" />
            <NextInput is-required :label="t('admin.payment_term.type')" v-model="form.type" :error="form.errors.type" />
        </form>
    </ModalDialog>
</template>
