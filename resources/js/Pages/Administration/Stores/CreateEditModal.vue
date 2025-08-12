<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import ModalDialog from '@/Components/next/Dialog.vue'
import vSelect from 'vue-select'
import NextInput from "@/Components/next/NextInput.vue";
import NextTextarea from "@/Components/next/NextTextarea.vue";

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

const branches = computed(() => props.branches.data ?? props.branches)
console.log('this is branches', branches.value);
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
    address: '',
    branch_id: null,
})


watch(() => props.editingItem, (item) => {
    if (item) {
        form.name = item.name || ''
        form.address = item.address || ''
        form.branch_id = item.branch_id || null
    } else {
        form.reset() // ✅ reset when switching from edit to create
    }
}, { immediate: true })

const closeModal = () => {
    localDialogOpen.value = false
}

const handleSubmit = async () => {
    if (isEditing.value) {
        form.patch(route('stores.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved')
                form.reset();
                closeModal()
            },
        })
    } else {
        form.post('/stores', {
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
        :title="isEditing ? 'Edit Stores' : 'Create Stores'"
        :confirmText="isEditing ? 'Update' : 'Create'"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >

        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid gap-4 py-4">
                <!-- Name -->

                <NextInput label="Name" v-model="form.name" :error="form.errors?.name" placeholder="Enter name" />
                <NextTextarea label="Address" v-model="form.address" :error="form.errors?.address" placeholder="Enter address" />

            </div>
        </form>
    </ModalDialog>
</template>
