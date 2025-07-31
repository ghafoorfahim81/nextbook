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
const localDialogOpen = ref(props.isDialogOpen)

watch(() => props.isDialogOpen, (val) => {0
    localDialogOpen.value = val
})

watch(() => localDialogOpen.value, (val) => {
    emit('update:isDialogOpen', val)
})

const isEditing = computed(() => !!props.editingItem?.id)

const form = useForm({
    name: '',
    code: '',
    symbol: '',
    format: '',
    remark: '',
    exchange_rate: '',
    flag: '',
    branch_id: null,
})


watch(() => props.editingItem, (item) => {
    if (item) {
        form.name = item.name || ''
        form.remark = item.remark || ''
        form.code = item.code || ''
        form.symbol = item.symbol || ''
        form.exchange_rate = item.exchange_rate || ''
        form.flag = item.flag || ''
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
        form.patch(route('currencies.update', props.editingItem.id), {
            onSuccess: () => {
                emit('saved')
                form.reset();
                closeModal()
            },
        })
    } else {
        form.post('/currencies', {
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
        :title="isEditing ? 'Edit Currency' : 'Create Currency'"
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
                    <NextInput label="code" v-model="form.code" type="text"/>
                    <NextInput label="Symbol" v-model="form.symbol" type="text"/>
                    <NextInput label="Format" v-model="form.format" type="text"/>
                    <NextInput label="Exchange Rate" v-model="form.exchange_rate" type="number"/>
                    <NextInput label="Flag" v-model="form.flag" type="text"/>
                    <div>
                        <Label for="branch_id" class="text-nowrap">Branch</Label>
                        <v-select
                            :options="branches"
                            v-model="form.branch_id"
                            :reduce="branch => branch.id"
                            label="name"
                            class="col-span-3"
                        />
                    </div>

                </div>
            </div>
        </form>
    </ModalDialog>
</template>
