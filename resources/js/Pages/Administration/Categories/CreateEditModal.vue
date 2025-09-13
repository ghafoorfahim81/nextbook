<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import ModalDialog from '@/Components/next/Dialog.vue'
import vSelect from 'vue-select'
import NextInput from "@/Components/next/NextInput.vue";
import FloatingLabel from "@/Components/next/FloatingLabel.vue";
import NextTextarea from "@/Components/next/NextTextarea.vue";
import NextSelect from "@/Components/next/NextSelect.vue";
import { useI18n } from 'vue-i18n';
const { t } = useI18n()
const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object, // ✅ this is passed from Index.vue
    categories: {
        type: Array,
        default: () => [],
    },
    errors: Object,
})

const emit = defineEmits(['update:isDialogOpen', 'saved'])

const categories = computed(() => props.categories.data ?? props.categories)

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
    remark: '',
    parent_id: null,
})


watch(() => props.editingItem, (item) => {
    if (item) {
        form.name = item.name || ''
        form.remark = item.remark || ''
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
    if (isEditing.value) {
        form.patch(route('categories.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved')
                form.reset();
                closeModal()
            },
        })
    } else {
        form.post('/categories', {
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
            :title="isEditing ? t('general.edit', { name: t('admin.category.category') }) : t('general.create', { name: t('admin.category.category') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >

        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid gap-4 py-4">
                <!-- Name -->

                <NextInput :label="t('general.name')" :placeholder="t('general.enter', { text: t('general.name') })" v-model="form.name" :error="form.errors.name" />

                <NextSelect
                        v-model="form.parent_id"
                        :options="categories"
                        label-key="name"
                        @update:modelValue="(value) => handleParentSelectChange(value)"
                        value-key="id"
                        id="parent"
                        :floating-text="t('admin.shared.parent')"
                        :error="form.errors?.parent_id"
                        :searchable="true"
                        resource-type="categories"
                        :search-fields="['name']"
                    />
                    
                <!-- <div class="relative z-100 w-full group dark:bg-slate-50 dark:text-slate-500">
                    <div>
                        <v-select
                            :options="categories"
                            v-model="form.parent_id"
                            :reduce="category => category.id"
                            label="name"
                            class="col-span-3"
                        />
                        <FloatingLabel :id="'type'" :label="`${t('admin.category.parent')}`"/>
                    </div>
                    <span v-if="form.errors?.parent_id" class="text-red-500 text-sm">
                    {{ form.errors.parent_id }}
                  </span>
                </div> -->
                <NextTextarea
                    v-model="form.remark"
                    :label="t('general.remarks')"
                    :placeholder="t('general.enter', { name: t('general.remarks') })"
                />
            </div>
        </form>
    </ModalDialog>
</template>
