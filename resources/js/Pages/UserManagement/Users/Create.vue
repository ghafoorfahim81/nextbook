<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from "@/Components/next/NextInput.vue";
import NextSelect from "@/Components/next/NextSelect.vue";
import Checkbox from "@/Components/Checkbox.vue";
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Components/ui/toast/use-toast';

const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    permissions: Array,
    roles: Array,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    branch_id: null,
    company_id: null,
    roles: [],
    permissions: [],
});

const handleSubmit = (createAndNew = false) => {
    const payload = createAndNew ? { create_and_new: true } : {};
    form.transform(data => ({ ...data, ...payload })).post('/users', {
        onSuccess: () => {
            if (createAndNew) {
                form.reset('name', 'email', 'password', 'password_confirmation', 'branch_id', 'company_id', 'roles', 'permissions');
            } else {
                $inertia.visit(route('users.index'));
            }
            toast({
                title: t('general.success'),
                description: t('general.create_success', { name: 'User' }),
                variant: 'success',
                class: 'bg-green-600 text-white',
            });
        },
    });
};

const goBack = () => {
    $inertia.visit(route('users.index'));
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: 'User' })">
        <form @submit.prevent="handleSubmit()">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: 'User' }) }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <!-- Basic Information -->
                    <NextInput
                        :label="t('general.name')"
                        :placeholder="t('general.enter', { text: t('general.name') })"
                        v-model="form.name"
                        :error="form.errors?.name"
                    />
                    <NextInput
                        label="Email"
                        type="email"
                        :placeholder="t('general.enter', { text: 'Email' })"
                        v-model="form.email"
                        :error="form.errors?.email"
                    />
                    <NextInput
                        label="Password"
                        type="password"
                        :placeholder="t('general.enter', { text: 'Password' })"
                        v-model="form.password"
                        :error="form.errors?.password"
                    />
                    <NextInput
                        label="Confirm Password"
                        type="password"
                        :placeholder="t('general.enter', { text: 'Confirm Password' })"
                        v-model="form.password_confirmation"
                        :error="form.errors?.password_confirmation"
                    /> 

                    <!-- Roles --> 
                        <NextSelect
                            v-model="form.roles"
                            :options="roles"
                            label-key="name"
                            value-key="id"
                            id="roles"
                            :floating-text="'Roles'"
                            :error="form.errors?.roles"
                            :searchable="true"
                            :multiple="true"
                            resource-type="roles"
                            :search-fields="['name']"
                        /> 

                    <!-- Additional Permissions -->
                    <div class="md:col-span-3">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Additional Permissions
                            </label>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                Select additional permissions that this user should have beyond their assigned roles.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div v-for="permission in permissions" :key="permission.id" class="flex items-center space-x-2">
                                <Checkbox
                                    :id="`permission-${permission.id}`"
                                    :name="`permissions[]`"
                                    :value="permission.id"
                                    :checked="form.permissions.includes(permission.id)"
                                    @change="(e) => {
                                        if (e.target.checked) {
                                            if (!form.permissions.includes(permission.id)) {
                                                form.permissions.push(permission.id);
                                            }
                                        } else {
                                            const index = form.permissions.indexOf(permission.id);
                                            if (index > -1) {
                                                form.permissions.splice(index, 1);
                                            }
                                        }
                                    }"
                                />
                                <label :for="`permission-${permission.id}`" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                    {{ permission.name }}
                                </label>
                            </div>
                        </div>
                        <div v-if="form.errors?.permissions" class="mt-2 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.permissions }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white" :disabled="form.processing">
                    {{ form.processing ? 'Creating...' : t('general.create') }}
                </button>
                <button type="button" class="btn btn-primary px-4 py-2 rounded-md bg-primary border text-white" @click="() => handleSubmit(true)">
                    {{ t('general.create') }} & {{ t('general.new') }}
                </button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="goBack">{{ t('general.cancel') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
