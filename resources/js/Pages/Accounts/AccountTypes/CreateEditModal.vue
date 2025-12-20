<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3' 
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from "@/Components/next/NextInput.vue";
import NextTextarea from "@/Components/next/NextTextarea.vue";
import { useI18n } from 'vue-i18n';

const { t } = useI18n()

const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object, // ✅ this is passed from Index.vue
    errors: Object,
})

const emit = defineEmits(['update:isDialogOpen', 'saved'])

const localDialogOpen = ref(props.isDialogOpen)

watch(() => props.isDialogOpen, (val) => {
    localDialogOpen.value = val
})

watch(() => localDialogOpen.value, (val) => {
    emit('update:isDialogOpen', val)
})

// const isEditing = computed(() => !!props.editingItem?.id)
const isEditing = computed(() => props.editingItem && props.editingItem.id);

const form = useForm({
    name: '',
    remark: '',
})


watch(() => props.editingItem, (item) => {
    if (item) {
        form.name = item.name || ''
        form.remark = item.remark || ''
    } else {
        form.reset() // when switching from edit to create
    }
}, { immediate: true })


const closeModal = () => {
    localDialogOpen.value = false;
    emit('saved');
}

const handleSubmit = async () => { 
    if (isEditing.value) {
        form.patch(route('account-types.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved')
                form.reset();
                closeModal()
            },
        })
    } else {
        form.post('/account-types', {
            onSuccess: () => {
                emit('saved')
                form.reset();
                closeModal()
            },
        })
    }
}


</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('account.account_type') }) : t('general.create', { name: t('account.account_type') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >

        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid gap-4 py-4">
                <!-- Name -->
                <NextInput :label="t('general.name')" id="name" v-model="form.name" :placeholder="t('general.enter', { text: t('general.name') })" :error="form.errors.name" />
                <NextTextarea :label="t('general.remark')" v-model="form.remark" :placeholder="t('general.enter', { text: t('general.remark') })" />
            </div> 
        </form>
    </ModalDialog>
</template>
