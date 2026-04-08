<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from "@/Components/next/NextInput.vue";
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Components/ui/toast/use-toast';
import PermissionGroups from '@/Pages/UserManagement/Roles/PermissionGroups.vue';
const { t } = useI18n();
const { toast } = useToast();

const props = defineProps({
    role: Object,
    permissions: Array,
});

const role = props.role?.data ?? props.role ?? {};
const form = useForm({
    name: role?.name || '',
    permissions: role?.permissions?.map(p => p.id) || [],
});

const handleSubmit = () => {
    form.patch(route('roles.update', role.id), {
        onSuccess: () => {
            toast({
                title: t('general.success'),
                description: t('general.update_success', { name: t('user_mangements.role') }),
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
    <AppLayout :title="t('general.edit', { name: t('user_mangements.role') })">
        <form @submit.prevent="handleSubmit()">
            <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
                <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
                    {{ t('general.edit', { name: t('user_mangements.role') }) }}
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
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ t('user_mangements.permissions') }}
                                </label>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ t('user_mangements.permissions_description') }}
                                </p>
                            </div>
                        </div>
                        <PermissionGroups v-model="form.permissions" :permissions="props.permissions" />
                        <div v-if="form.errors?.permissions" class="mt-2 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.permissions }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white" :disabled="form.processing">
                    {{ form.processing ? t('general.updating', { name: t('user_mangements.role') }) : t('general.update') }}
                </button>
                <button type="button" class="btn px-4 py-2 rounded-md border" @click="goBack">{{ t('general.cancel') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
