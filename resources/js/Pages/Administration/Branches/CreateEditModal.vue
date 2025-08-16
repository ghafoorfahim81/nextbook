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
import FloatingLabel from "@/Components/next/FloatingLabel.vue";
import NextSelect from "@/Components/next/NextSelect.vue";

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

const handleSubmit = async () => {
    if (isEditing.value) {
        form.patch(route('branches.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved')
                form.reset();
                closeModal()
            },
        })
    } else {
        form.post('/branches', {
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
        :title="isEditing ? 'Edit Branch' : 'Create Branch'"
        :confirmText="isEditing ? 'Update' : 'Create'"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        width="w-[800px] max-w-[800px]"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >


        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid col-span-2 gap-4 py-4">
                <div class="grid items-center grid-cols-2 gap-4">
                    <NextInput label="Name" v-model="form.name" :errors="errors?.name" type="text"/>
                    <NextInput label="Location" v-model="form.location" type="text"/>
                    <NextInput label="Sub Domain" v-model="form.sub_domain" type="text"/>
                    <NextSelect
                        v-model="form.parent_id"
                        :options="branches"
                        label-key="name"
                        value-key="id"
                        id="type"
                        floating-text="Parent"
                        :error="form.errors?.parent_id"
                    />
                    <NextTextarea
                        v-model="form.remark"
                        label="Remark"
                        placeholder="Enter your remark..."
                        :error="form.errors?.remark"
                    />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
