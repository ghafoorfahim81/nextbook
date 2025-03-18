<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {Label} from "@/Components/ui/label/index.js";
import ModalDialog from "@/Components/next/Dialog.vue";

const props = defineProps({
    isDialogOpen: Boolean,
    departments: {
        type: Array,
        default: () => [],
    },
    department: {
        type: Object,
        default: null,
    },
})

const isEditing = computed(() => !!props.department)

const form = useForm({
    name: '',
    remark: '',
    parent_id: null,
})

if (isEditing.value) {
    form.name = props.department.name
    form.remark = props.department.remark
    form.parent_id = props.department.parent_id
}
const emit = defineEmits(["update:open", "confirm", "cancel"]);

const handleSubmit = async () => {
    if (isEditing.value) {
        await form.patch(route('departments.update', props.department.id))
    } else {
        await form.post(route('departments.store'))
    }
    emit('saved')
    emit('close')
}

const closeModal = () => {
    emit('close')
    form.reset()
}
</script>

<template>
    <ModalDialog
        :open="props.isDialogOpen"
        :title="isEditing ? 'Edit Department' : 'Create Department'"
        :confirmText="isEditing ? 'Update' : 'Create'"
        cancelText="No, Cancel"
        showConfirm
        showCancel
        :closeable="true"
        @update:open="props.isDialogOpen = $event"
        @confirm="handleSubmit"
        @cancel="closeModal"
    >
        <form @submit.prevent="handleSubmit">
                        <!-- Name -->
                        <div class="grid gap-4 py-4">
                            <div class="grid items-center grid-cols-4 gap-4">
                                <Label for="name" class="text-nowrap">
                                    Name
                                </Label>
                                <Input id="name" v-model="form.name" placeholder="Enter name" class="col-span-3" />
                            </div>
                            <div class="grid items-center grid-cols-4 gap-4">
                                <Label for="parent" class="text-nowrap">
                                    Parent
                                </Label>
                                <v-select :options="[1,2,3]" class="col-span-3"></v-select>
                            </div>

                        <!-- Remark -->
                            <div class="grid items-center grid-cols-4 gap-4">
                                <Label for="remark" class="text-nowrap">
                                    Remark
                                </Label>
                                <Textarea id="remark" v-model="form.remark" rows="3" class="col-span-3" />
                            </div>
                        </div>
                    </form>
    </ModalDialog>

<!--    <Dialog-->
<!--        :open="props.open"-->
<!--    @openChange="(val) => { if (!val) closeModal() }"-->
<!--    >-->
<!--    <DialogContent>-->
<!--        <DialogHeader>-->
<!--            <DialogTitle>{{ isEditing ? 'Edit Department' : 'Create Department' }}</DialogTitle>-->
<!--        </DialogHeader>-->
<!--        <form @submit.prevent="handleSubmit">-->
<!--            &lt;!&ndash; Name &ndash;&gt;-->
<!--            <div class="grid gap-4 py-4">-->
<!--                <div class="grid items-center grid-cols-4 gap-4">-->
<!--                    <Label for="name" class="text-nowrap">-->
<!--                        Name-->
<!--                    </Label>-->
<!--                    <Input id="name" v-model="form.name" placeholder="Enter name" class="col-span-3" />-->
<!--                </div>-->
<!--                <div class="grid items-center grid-cols-4 gap-4">-->
<!--                    <Label for="parent" class="text-nowrap">-->
<!--                        Parent-->
<!--                    </Label>-->
<!--                    <v-select :options="[1,2,3]" class="col-span-3"></v-select>-->
<!--                </div>-->

<!--            &lt;!&ndash; Remark &ndash;&gt;-->
<!--                <div class="grid items-center grid-cols-4 gap-4">-->
<!--                    <Label for="remark" class="text-nowrap">-->
<!--                        Remark-->
<!--                    </Label>-->
<!--                    <Textarea id="remark" v-model="form.remark" rows="3" class="col-span-3" />-->
<!--                </div>-->

<!--            &lt;!&ndash; Parent Department &ndash;&gt;-->

<!--            </div>-->

<!--            <DialogFooter>-->
<!--                <Button variant="outline" type="button" @click="closeModal">-->
<!--                    Cancel-->
<!--                </Button>-->
<!--                <Button type="submit">-->
<!--                    {{ isEditing ? 'Update' : 'Create' }}-->
<!--                </Button>-->
<!--            </DialogFooter>-->
<!--        </form>-->
<!--    </DialogContent>-->
<!--    </Dialog>-->
</template>
