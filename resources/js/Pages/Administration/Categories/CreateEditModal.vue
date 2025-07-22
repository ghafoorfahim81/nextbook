<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import ModalDialog from '@/Components/next/Dialog.vue'
import vSelect from 'vue-select'

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
const submitting = ref(false)
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
    submitting.value = true
    if (isEditing.value) {
        form.patch(route('categories.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved')
                form.reset();
                submitting.value = false
                closeModal()
            },
        })
    } else {
        form.post('/categories', {
            onSuccess: () => {
                emit('saved')
                form.reset();
                submitting.value = false
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
        :closeable="true"
        :submitting="submitting"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid gap-4 py-4">
                <!-- Name -->
                <div class="grid items-center grid-cols-4 gap-4">
                    <Label for="name" class="text-nowrap">Name</Label>
                    <Input id="name" v-model="form.name" placeholder="Enter name" class="col-span-3" />
                    <div v-if="form.errors.name" class="text-red-500 text-sm col-span-4">
                        {{ form.errors.name }}
                    </div>

                </div>

                <!-- Parent -->
                <div class="grid items-center grid-cols-4 gap-4">
                    <Label for="parent_id" class="text-nowrap">Parent</Label>
                    <v-select
                        :options="categories"
                        v-model="form.parent_id"
                        :reduce="category => category.id"
                        label="name"
                        class="col-span-3"
                    />
                </div>

                <!-- Remark -->
                <div class="grid items-center grid-cols-4 gap-4">
                    <Label for="remark" class="text-nowrap">Remark</Label>
                    <Textarea id="remark" v-model="form.remark" rows="3" class="col-span-3" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
