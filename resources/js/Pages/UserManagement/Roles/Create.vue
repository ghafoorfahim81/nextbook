<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from "@/Components/next/NextInput.vue";
import Checkbox from "@/Components/Checkbox.vue";
import SubmitButtons from "@/Components/SubmitButtons.vue";
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Components/ui/toast/use-toast';
import { Input } from "@/Components/ui/input";
const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    permissions: Array,
});

const form = useForm({
    name: '',
    permissions: [],
});

const submitAction = ref(null);
const createLoading = computed(() => form.processing && submitAction.value === 'create');
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new');

const handleSubmitAction = (createAndNew = false) => {
    submitAction.value = createAndNew ? 'create_and_new' : 'create';
    handleSubmit(createAndNew);
};

const search = ref('');

// Searchable permissions
const filteredPermissions = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.permissions;
    return props.permissions.filter(permission => {
        const translated = translatePermission(permission.name).toLowerCase();
        return (
            translated.includes(q) ||
            permission.name.toLowerCase().includes(q)
        );
    });
});

const humanizePermission = (name) => {
    if (!name) return '';
    const parts = name.split('.');
    const resource = (parts[0] || '').replace(/_/g, ' ');
    const action = (parts[1] || '').replace(/_/g, ' ');
    const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
    if (!action) {
        return cap(resource);
    }
    return `${cap(resource)} - ${cap(action)}`;
};

function translatePermission(name) {
    const key = `permissions.${name}`;
    const translated = t(key);
    if (translated === key) {
        return humanizePermission(name);
    }
    return translated;
}

const handleSubmit = (createAndNew = false) => {
    const payload = createAndNew ? { create_and_new: true } : {};
    form.transform(data => ({ ...data, ...payload })).post('/roles', {
        onSuccess: () => {
            if (createAndNew) {
                form.reset('name', 'permissions');
            }
            toast({
                title: t('general.success'),
                description: t('general.create_success', { name: t('user_mangements.role') }),
                variant: 'success',
                class: 'bg-green-600 text-white',
            });
        },
    });
};

const goBack = () => {
    $inertia.visit(route('roles.index'));
};
</script>

<template>
    <AppLayout :title="t('general.create', { name: t('user_mangements.role') })">
        <form @submit.prevent="handleSubmitAction">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.create', { name: t('user_mangements.role') }) }}
                </div>
                <ModuleHelpButton module="user_management" />
                <div class="grid grid-cols-1 gap-4 mt-3">
                    <NextInput
                        :label="t('general.name')"
                        :placeholder="t('general.enter', { text: t('general.name') })"
                        v-model="form.name"
                        :error="form.errors?.name"
                    />

                    <!-- Permissions -->
                    <div>
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium  mb-2">
                                    {{ t('user_mangements.permissions') }}
                                </label>
                                <p class="text-sm">
                                    {{ t('user_mangements.permissions_description') }}
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Checkbox
                                    id="check-all"
                                    :checked="form.permissions.length === filteredPermissions.length && filteredPermissions.length > 0"
                                    :indeterminate="form.permissions.length > 0 && form.permissions.length < filteredPermissions.length"
                                    @change="(e) => {
                                        if (e.target.checked) {
                                            form.permissions = filteredPermissions.map(p => p.id);
                                        } else {
                                            form.permissions = form.permissions.filter(id => !filteredPermissions.map(p => p.id).includes(id));
                                        }
                                    }"
                                />
                                <label for="check-all" class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                                    {{ t('general.select_all') }}
                                </label>
                            </div>
                        </div>

                        <!-- Search box -->
                        <div class="mb-3">
                            <Input
                                v-model="search"
                                type="text"
                                class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-300 dark:bg-gray-800 dark:border-gray-700"
                                :placeholder="t('general.search_placeholder', { name: t('user_mangements.permissions') }).toLowerCase()"
                                autocomplete="off"
                                /> 
                        </div>

                        <div v-if="filteredPermissions.length === 0" class="text-sm text-muted-foreground px-4 py-6 text-center">
                            {{ t('general.no_results', { name: t('user_mangements.permissions') }) }}
                        </div>
                        <div
                            v-else
                            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                        >
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
                                <label :for="`permission-${permission.id}`" class="text-sm text-gray-400 dark:text-gray-300 cursor-pointer">
                                    {{ translatePermission(permission.name) }}
                                </label>
                            </div>
                        </div>
                        <div v-if="form.errors?.permissions" class="mt-2 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.permissions }}
                        </div>
                    </div>
                </div>
            </div>

            <SubmitButtons
                :create-label="t('general.create')"
                :create-and-new-label="t('general.create_and_new')"
                :cancel-label="t('general.cancel')"
                :creating-label="t('general.creating', { name: t('user_mangements.role') })"
                :create-loading="createLoading"
                :create-and-new-loading="createAndNewLoading"
                @create-and-new="handleSubmitAction(true)"
                @cancel="goBack"
            />
        </form>
    </AppLayout>
</template>

