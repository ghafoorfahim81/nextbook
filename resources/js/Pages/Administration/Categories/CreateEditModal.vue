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
        :title="isEditing ? 'Edit Category' : 'Create Category'"
        :confirmText="isEditing ? 'Update' : 'Create'"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >

        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid gap-4 py-4">
                <!-- Name -->

                <NextInput label="Name" v-model="form.name" :error="form.errors.name" />

                <div class="relative z-100 w-full group dark:bg-slate-50 dark:text-slate-500">
                    <div>
                        <v-select
                            :options="categories"
                            v-model="form.parent_id"
                            :reduce="category => category.id"
                            label="name"
                            class="col-span-3"
                        />
                        <FloatingLabel :id="'type'" :label="`Parent`"/>
                    </div>
                    <span v-if="form.errors?.parent_id" class="text-red-500 text-sm">
                    {{ form.errors.parent_id }}
                  </span>
                </div>
                <NextTextarea
                    v-model="form.remark"
                    label="Description"
                    placeholder="Enter product description"
                />
            </div>
        </form>
    </ModalDialog>
</template>
