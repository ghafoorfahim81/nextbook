<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import {Label} from "@/Components/ui/label/index.js";
import vSelect from 'vue-select'

const props = defineProps({
    // "open" determines if the parent wants the modal visible
    open: Boolean,
    // For demonstration: an array or list of departments
    departments: {
        type: Array,
        default: () => [],
    },
    // If editing a single department
    department: {
        type: Object,
        default: null,
    },
})

// Emit "close" or "saved" so the parent can respond
const emit = defineEmits(['close', 'saved'])

// If you have a separate "department" to edit, decide if we are editing or creating
const isEditing = computed(() => !!props.department)

// Example Inertia form
const form = useForm({
    name: '',
    remark: '',
    parent_id: null,
})

// If editing, initialize form
if (isEditing.value) {
    form.name = props.department.name
    form.remark = props.department.remark
    form.parent_id = props.department.parent_id
}

const handleSubmit = async () => {
    // Submit logic
    if (isEditing.value) {
        await form.patch(route('departments.update', props.department.id))
    } else {
        await form.post(route('departments.store'))
    }
    emit('saved')
    // The child does NOT set local isOpen; it just notifies parent to close
    emit('close')
}

const closeModal = () => {
    emit('close')
    form.reset()
}
</script>

<template>
    <Dialog
        :open="props.open"
    @openChange="(val) => { if (!val) closeModal() }"
    >
    <DialogContent>
        <DialogHeader>
            <DialogTitle>{{ isEditing ? 'Edit Department' : 'Create Department' }}</DialogTitle>
        </DialogHeader>
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
<!--                    <Select v-model="form.parent_id" id="parent" >-->
<!--                        <SelectTrigger class="col-span-3">-->
<!--                            <SelectValue placeholder="Select parent department" />-->
<!--                        </SelectTrigger>-->
<!--                        <SelectContent>-->
<!--                            &lt;!&ndash; Example usage: no parent option &ndash;&gt;-->
<!--                            <SelectItem :value="null">No Parent</SelectItem>-->
<!--                            <SelectItem-->
<!--                                v-for="dep in props.departments"-->
<!--                                :key="dep.id"-->
<!--                                :value="dep.id"-->
<!--                            >-->
<!--                                {{ dep.name }}-->
<!--                            </SelectItem>-->
<!--                        </SelectContent>-->
<!--                    </Select>-->
                </div>

            <!-- Remark -->
                <div class="grid items-center grid-cols-4 gap-4">
                    <Label for="remark" class="text-nowrap">
                        Remark
                    </Label>
                    <Textarea id="remark" v-model="form.remark" rows="3" class="col-span-3" />
                </div>

            <!-- Parent Department -->

            </div>

            <DialogFooter>
                <Button variant="outline" type="button" @click="closeModal">
                    Cancel
                </Button>
                <Button type="submit">
                    {{ isEditing ? 'Update' : 'Create' }}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
    </Dialog>
</template>
