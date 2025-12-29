<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from "@/Components/next/NextInput.vue";
import NextSelect from "@/Components/next/NextSelect.vue";
import Checkbox from "@/Components/Checkbox.vue";
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Components/ui/toast/use-toast';
import { Input } from "@/Components/ui/input";
const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    permissions: Array,
    roles: Array, 
    branches: {type: Object, required: true},

});

console.log(props.branches);
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

// --- Permission Search and Humanization Logic ---

const permissionSearch = ref('');
const permissionSearchFocused = ref(false);

// This function will try to humanize the permission keys.
function humanizePermissionName(name) {
    // Example: 'account_transfers.create' -> 'Account Transfers Create'
    if (!name) return '';
    let readable = name.replace(/[_\.]/g, ' ');
    return readable
        .split(' ')
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

// You might want to group by group/module, advanced usage
function getPermissionGroup(name) {
    // Example: permission 'account_transfers.create'
    if (!name) return '';
    return name.includes('.') ? name.split('.')[0] : '';
}

// Filter permission list by search
const filteredPermissions = computed(() => {
    if (!permissionSearch.value) {
        return props.permissions;
    }
    const q = permissionSearch.value.toLowerCase();
    return props.permissions.filter((perm) =>
        perm.name.toLowerCase().includes(q)
    );
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
    <AppLayout :title="t('general.create', { name: t('user_mangements.user') })">
        <form @submit.prevent="handleSubmit()">
            <div class="mb-5 rounded-xl border p-4 shadow-sm   border-gray-200 relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: t('user_mangements.user') }) }}
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
                        :label="t('general.email')"
                        type="email"
                        :placeholder="t('general.enter', { text: t('general.email') })"
                        v-model="form.email"
                        :error="form.errors?.email"
                    />
                    <NextInput
                        :label="t('auth.password')"
                        type="password"
                        :placeholder="t('general.enter', { text: t('auth.password') })"
                        v-model="form.password"
                        :error="form.errors?.password"
                    />
                    <NextInput
                        :label="t('user_mangements.confirm_password')"
                        type="password"
                        :placeholder="t('general.enter', { text: t('user_mangements.confirm_password') })"
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
                        :floating-text="t('user_mangements.roles')"
                        :error="form.errors?.roles"
                        :searchable="true"
                        resource-type="roles"
                        multiple
                        :search-fields="['name']"
                    />  

                    <NextSelect
                        v-model="form.branch_id"
                        :options="branches.data"
                        label-key="name"
                        value-key="id"
                        id="branch_id"
                        :floating-text="t('admin.branch.branch')"
                        :error="form.errors?.branch_id"
                        :searchable="true"
                        resource-type="branches"
                        :search-fields="['name']"
                    />   
                    <!-- Additional Permissions -->
                    <div class="md:col-span-3">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700  mb-2">
                                {{ t('user_mangements.additional_permissions') }}
                            </label>
                            <p class="text-sm text-gray-600  mb-4">
                                {{ t('user_mangements.additional_permissions_description') }}
                            </p>
                        </div>
                        <!-- Permission search bar -->
                        <div class="mb-3">
                            <Input
                                v-model="permissionSearch"
                                @focus="permissionSearchFocused = true"
                                @blur="permissionSearchFocused = false"
                                type="text"
                                class="w-full p-2 rounded border border-gray-300    focus:outline-none focus:ring-2 focus:ring-primary transition"
                                :placeholder="t('general.search', { text: t('user_mangements.permissions') }) + '...'"
                            />
                        </div>
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4"
                        >
                            <div
                                v-for="permission in filteredPermissions"
                                :key="permission.id"
                                class="flex items-center space-x-2 rtl:space-x-reverse rounded hover:bg-gray-100  px-2 transition group"
                                :class="{
                                    'bg-gray-100': form.permissions.includes(permission.id)
                                }"
                            >
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
                                <!-- Human readable name for permission -->
                                <label
                                    :for="`permission-${permission.id}`"
                                    class="text-sm font-medium cursor-pointer "
                                    :class="form.permissions.includes(permission.id) ? 'text-primary' : 'text-gray-500 group-hover:text-primary-700 dark:hover:text-black'"
                                    :title="permission.name"
                                >
                                    {{ humanizePermissionName(permission.name) }}
                                </label>
                            </div>
                            <div v-if="filteredPermissions.length === 0" class="col-span-full py-4 text-center text-gray-400">
                                {{ t('general.not_found') }}
                            </div>
                        </div>
                        <div v-if="form.errors?.permissions" class="mt-2 text-sm text-red-600">
                            {{ form.errors.permissions }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white" :disabled="form.processing">
                    {{ form.processing ? t('general.creating', { name: t('user_mangements.user') }) : t('general.create') }}
                </button>
                <button type="button" class="btn btn-primary px-4 py-2 rounded-md bg-primary border text-white" @click="() => handleSubmit(true)">
                    {{ t('general.create') }} & {{ t('general.new') }}
                </button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="goBack">{{ t('general.cancel') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
