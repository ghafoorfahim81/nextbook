<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import ModalDialog from '@/Components/next/Dialog.vue'
import NextInput from "@/Components/next/NextInput.vue";
import NextTextarea from "@/Components/next/NextTextarea.vue";
import NextSelect from "@/Components/next/NextSelect.vue";
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner'
const { t } = useI18n()
const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object, // ✅ this is passed from Index.vue
    branches: {
        type: Array,
        default: () => [],
    },
    errors: Object,
})

const emit = defineEmits(['update:isDialogOpen', 'saved'])
const loading = ref(false)
const branches = computed(() => {
    const data = props.branches.data ?? props.branches
    return data
})
const localDialogOpen = ref(props.isDialogOpen)

watch(() => props.isDialogOpen, (val) => {
    localDialogOpen.value = val
})

watch(() => localDialogOpen.value, (val) => {
    emit('update:isDialogOpen', val)
})

const isEditing = computed(() => !!props.editingItem?.id)

const form = useForm({
    name: '',
    location: '',
    sub_domain: '',
    remark: '',
    parent_id: null,
})


watch(() => props.editingItem, (item) => {
    if (item) {
        form.name = item.name || ''
        form.remark = item.remark || ''
        form.location = item.location || ''
        form.sub_domain = item.sub_domain || ''
        form.parent_id = item.parent_id || null
    } else {
        form.reset() // ✅ reset when switching from edit to create
    }
}, { immediate: true })

const closeModal = () => {
    localDialogOpen.value = false
}

const handleParentSelectChange = (value) => {
    form.parent_id = value
}

const handleSubmit = async () => {
    const isEdit = isEditing.value;
    const action = isEdit
        ? () => form.patch(route('branches.update', props.editingItem.id), submitOptions)
        : () => form.post('/branches', submitOptions);

    const submitOptions = {
        onSuccess: () => {
            emit('saved');
            form.reset();
            closeModal();
            toast.success(
                t('general.success'),
                {
                    description: t(
                        isEdit ? 'general.update_success' : 'general.create_success',
                        { name: t('admin.branch.branch') }
                    ),
                    class: 'bg-green-600',
                }
            );
        },
        onError: () => console.error('error', form.errors),
    };

    await action();
};

 


</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit') + ' ' + t('admin.branch.branch') : t('general.create') + ' ' + t('admin.branch.branch')"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        width="w-[95vw] max-w-[95vw] sm:w-[800px] sm:max-w-[800px]"
        @confirm="handleSubmit"
        @cancel="closeModal" 
        :submitting="form.processing"
        :cancel-text="t('general.close')"
    >


        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid col-span-2 gap-4 py-4">
                <div class="grid items-center grid-cols-2 gap-4">
                    <NextInput :label="t('general.name')" :placeholder="t('general.enter', { text: t('general.name') })" v-model="form.name" :error="form.errors?.name"/>
                    <NextInput :label="t('admin.branch.location')" :placeholder="t('general.enter', { text: t('admin.branch.location') })" v-model="form.location" :error="form.errors?.location"/>
                    <NextInput :label="t('admin.branch.sub_domain')" :placeholder="t('general.enter', { text: t('admin.branch.sub_domain') })" v-model="form.sub_domain" :error="form.errors?.sub_domain"/>
                    <NextSelect
                        v-model="form.parent_id"
                        :options="branches"
                        label-key="name"
                        @update:modelValue="(value) => handleParentSelectChange(value)"
                        value-key="id"
                        id="parent"
                        :floating-text="t('admin.shared.parent')"
                        :error="form.errors?.parent_id"
                        :searchable="true"
                        resource-type="branches"
                        :search-fields="['name', 'location']"
                    />
                    <NextTextarea
                        v-model="form.remark"
                        :label="t('admin.shared.remark')"
                        :placeholder="t('general.enter', { text: t('admin.shared.remark') })"
                        :error="form.errors?.remark"
                    />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
