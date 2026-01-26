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
import { useLazyProps } from '@/composables/useLazyProps'

const { t, locale } = useI18n();
const { toast } = useToast();

const props = defineProps({
    user: Object,
    permissions: Array,
    roles: {
        type: Array,
        required: false,
        default: () => [],
    },
});
const user = props.user?.data;

useLazyProps(props, ['roles'])

// Add a loading state as in many create pages
const loading = ref(false);
const form = useForm({
    name: user?.name || '',
    email: user?.email || '',
    password: '',
    password_confirmation: '',
    roles: user?.roles?.map(r => r.id) || [],
    permissions: user?.permissions || [],
    // If you have remarks or other extra fields, add here if present in create
});

// --- Permission Search Logic (same as Create.vue) ---
const permissionSearch = ref('');
const permissionSearchFocused = ref(false);

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
// For translation keys (from create) for roles/permissions label/desc
const ROLES_LABEL = computed(() => t('user_mangements.roles'));
const PERMS_LABEL = computed(() => t('user_mangements.additional_permissions'));
const PERMS_DESC = computed(() => t('user_mangements.additional_permissions_description'));

const handleSubmit = () => {
    loading.value = true;
    form.patch(route('users.update', user.id), {
        onSuccess: () => {
            toast({
                title: t('general.success'),
                description: t('general.update_success', { name: t('user_mangements.user') }),
                variant: 'success',
                class: 'bg-green-600 text-white',
            });
            $inertia.visit(route('users.index'));
        },
        onError: () => {
            toast({
                title: t('general.error'),
                description: t('general.update_error_message'),
                variant: 'destructive',
                class: 'bg-red-600 text-white',
            });
        },
        onFinish: () => {
            loading.value = false;
        }
    });
};

const goBack = () => {
    $inertia.visit(route('users.index'));
};
</script>

<template>
    <AppLayout :title="t('general.edit', { name: t('user_mangements.user') })">
        <form @submit.prevent="handleSubmit()">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('user_mangements.user') }) }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                    <!-- Basic Information -->
                    <NextInput
                        :label="t('general.name')"
                        :placeholder="t('general.enter', { text: t('general.name') })"
                        v-model="form.name"
                        :error="form.errors?.name"
                        autocomplete="off"
                    />
                    <NextInput
                        :label="t('general.email')"
                        type="email"
                        :placeholder="t('general.enter', { text: t('general.email') })"
                        v-model="form.email"
                        :error="form.errors?.email"
                        autocomplete="off"
                    /> 
                     <NextInput
                        :label="t('auth.password')"
                        type="password"
                        :placeholder="t('general.enter', { text: t('auth.password') })"
                        v-model="form.password"
                        :error="form.errors?.password"
                        autocomplete="new-password"
                    />
                    <NextInput
                        :label="t('user_mangements.confirm_password')"
                        type="password"
                        :placeholder="t('user_mangements.confirm_password')"
                        v-model="form.password_confirmation"
                        :error="form.errors?.password_confirmation"
                        autocomplete="new-password"
                    />
                    <!-- Roles -->
                    <NextSelect
                        v-model="form.roles"
                        :options="roles"
                        label-key="name"
                        value-key="id"
                        id="roles"
                        :floating-text="ROLES_LABEL"
                        :error="form.errors?.roles"
                        :searchable="true" 
                        resource-type="roles"
                        multiple
                        :search-fields="['name']"
                        :placeholder="t('general.select') + ' ' + ROLES_LABEL"
                    />

                    <!-- Additional Permissions -->
                    <div class="md:col-span-3">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-400 mb-2">
                                {{ PERMS_LABEL }}
                            </label>
                            <p class="text-sm dark:text-gray-400 mb-4">
                                {{ PERMS_DESC }}
                            </p>
                        </div>
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
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div v-for="permission in filteredPermissions" :key="permission.id" class="flex items-center space-x-2">
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
                                <label :for="`permission-${permission.id}`" class="text-sm text-gray-500 group-hover:text-primary-700 dark:hover:text-black cursor-pointer">
                                    {{ humanizePermissionName(permission.name) }}
                                </label>
                            </div>
                            <div v-if="filteredPermissions.length === 0" class="text-gray-400 text-sm col-span-full text-center py-2">
                                {{ t('general.no_results_found') }}
                            </div>
                        </div>
                        <div v-if="form.errors?.permissions" class="mt-2 text-sm text-red-600">
                            {{ form.errors.permissions }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button
                    type="submit"
                    class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white"
                    :disabled="form.processing || loading"
                >
                    <span v-if="form.processing || loading" class="animate-spin inline-block mr-2 w-4 h-4 align-[-.125em] border-2 border-t-transparent border-white rounded-full"></span>
                    {{ (form.processing || loading) ? t('general.updating', { name: t('user_mangements.user') }) : t('general.update') }}
                </button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="goBack">
                    {{ t('general.cancel') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
