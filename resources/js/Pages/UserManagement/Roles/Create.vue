<script setup>
import AppLayout from '@/Layouts/Layout.vue';
import { useForm } from '@inertiajs/vue3';
import NextInput from "@/Components/next/NextInput.vue";
import SubmitButtons from "@/Components/SubmitButtons.vue";
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Components/ui/toast/use-toast';
import PermissionGroups from '@/Pages/UserManagement/Roles/PermissionGroups.vue';
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
        <form @submit.prevent="handleSubmitAction(false)">
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
                        </div>
                        <PermissionGroups v-model="form.permissions" :permissions="props.permissions" />
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
