<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from "@/Components/ui/label/index.js";
import ModalDialog from "@/Components/next/Dialog.vue";
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    isDialogOpen: Boolean,
    categories: {
        type: Array,
        default: () => [],
    },
    errors: Object,
});

const categories = computed(() => props.categories.data);
console.log('this is department', props.categories);
const emit = defineEmits(['update:isDialogOpen', 'saved']);

// ✅ Local reactive state to track dialog open status
const localDialogOpen = ref(props.isDialogOpen);

// ✅ Watch for changes in the prop and update the local state
watch(() => props.isDialogOpen, (newValue) => {
    localDialogOpen.value = newValue;
});

// ✅ When closing the modal, emit the event to the parent
const closeModal = () => {
    localDialogOpen.value = false;
    emit('update:isDialogOpen', false);
    // form.reset();
};


const isEditing = computed(() => !!props.department);

const form = reactive({
    name: '',
    remark: '',
    parent_id: null,
});

if (isEditing.value) {
    form.name = props.department.name;
    form.remark = props.department.remark;
    form.parent_id = props.department.parent_id;
}


function submit() {
    router.post('/users', form)
}

const handleSubmit = async () => {
    if (isEditing.value) {
        await router.patch(route('categories.update', props.department.id));
    } else {
        await router.post(route('categories.store'));
    }
    emit('saved');
    // closeModal(); // ✅ Properly close the modal
};
</script>

<template>
    <ModalDialog
        :open="localDialogOpen"
        :title="isEditing ? 'Edit Department' : 'Create Department'"
        :confirmText="isEditing ? 'Update' : 'Create'"
        :closeable="true"
        @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit">
            <div class="grid gap-4 py-4">
                <div class="grid items-center grid-cols-4 gap-4">
                    <Label for="name" class="text-nowrap">
                        Name
                    </Label>
                    <Input id="name" autocomplete="false" v-model="form.name" placeholder="Enter name" class="col-span-3" />
                    <div v-if="errors?.name">{{ errors.name }}</div>
                </div>
                <div class="grid items-center grid-cols-4 gap-4">
                    <Label for="parent" class="text-nowrap">
                        Parent
                    </Label>
                    <v-select :options="categories" label="name" class="col-span-3"></v-select>
                </div>

                <div class="grid items-center grid-cols-4 gap-4">
                    <Label for="remark" class="text-nowrap">
                        Remark
                    </Label>
                    <Textarea id="remark" v-model="form.remark" rows="3" class="col-span-3" />
                </div>
            </div>
        </form>
    </ModalDialog>
</template>
