<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import ModalDialog from '@/Components/next/Dialog.vue' 
import NextInput from "@/Components/next/NextInput.vue"; 
import { useI18n } from 'vue-i18n';
const { t } = useI18n()
const props = defineProps({
    isDialogOpen: Boolean,
    editingItem: Object, // ✅ this is passed from Index.vue
    branches: {
        type: Array,
        default: () => [],
    },

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
            onError: () => {
                console.log('error', form.errors);
            },
        })
    }
}


</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? t('general.edit', { name: t('admin.currency.currency') }) : t('general.create', { name: t('admin.currency.currency') })"
        :confirmText="isEditing ? t('general.update') : t('general.create')"
        :cancel-text="t('general.close')"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        :closeable="true"
        width="w-[800px] max-w-[800px]"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit" id="modalForm">
            <div class="grid col-span-2 gap-4 py-4">
                <div class="grid items-center grid-cols-2 gap-4">
                    <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name"/>
                    <NextInput :label="t('admin.currency.code')" v-model="form.code" :error="form.errors?.code"/>
                    <NextInput :label="t('admin.shared.symbol')" v-model="form.symbol" :error="form.errors?.symbol"/>
                    <NextInput :label="t('admin.currency.format')" v-model="form.format" :error="form.errors?.format"/>
                    <NextInput :label="t('admin.currency.exchange_rate')" v-model="form.exchange_rate" type="number" :error="form.errors?.exchange_rate"/>
                    <NextInput :label="t('admin.currency.flag')" v-model="form.flag" :error="form.errors?.flag"/>
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
