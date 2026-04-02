<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Building2,
    GitBranch,
    KeyRound,
    Mail,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';

const props = defineProps({
    open: Boolean,
    userId: String,
});

const emit = defineEmits(['update:open']);
const { t } = useI18n();

const user = ref(null);
const loading = ref(false);

const humanizePermission = (name) => {
    if (!name) return '';

    return name
        .replace(/[_\.]/g, ' ')
        .split(' ')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const translatePermission = (name) => {
    const key = `permissions.${name}`;
    const translated = t(key);

    return translated === key ? humanizePermission(name) : translated;
};

const handleOpenChange = (nextOpen) => {
    emit('update:open', nextOpen);

    if (!nextOpen) {
        user.value = null;
        loading.value = false;
    }
};

watch(
    () => [props.open, props.userId],
    async ([isOpen, userId]) => {
        if (!isOpen || !userId) {
            return;
        }

        loading.value = true;

        try {
            const { data } = await axios.get(`/users/${userId}`);
            user.value = data?.data ?? data ?? null;
        } finally {
            loading.value = false;
        }
    }
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-3 text-xl text-violet-600">
                    <UserRound class="h-6 w-6" />
                    <span>
                        {{ t('user_mangements.user') }}
                        <span v-if="user">- {{ user.name }}</span>
                    </span>
                </DialogTitle>
                <DialogDescription v-if="user?.email">
                    {{ user.email }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="py-8 text-center text-sm text-muted-foreground">
                {{ t('general.loading') || 'Loading' }}...
            </div>

            <div v-else-if="user" class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border p-4">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <UserRound class="h-3.5 w-3.5" />
                            {{ t('general.name') }}
                        </div>
                        <div class="text-sm font-medium">{{ user.name || '-' }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <Mail class="h-3.5 w-3.5" />
                            {{ t('general.email') }}
                        </div>
                        <div class="text-sm font-medium break-all">{{ user.email || '-' }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <Building2 class="h-3.5 w-3.5" />
                            {{ t('admin.company.company') || 'Company' }}
                        </div>
                        <div class="text-sm font-medium">{{ user.company?.name || '-' }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <GitBranch class="h-3.5 w-3.5" />
                            {{ t('admin.branch.branch') || 'Branch' }}
                        </div>
                        <div class="text-sm font-medium">{{ user.branch?.name || '-' }}</div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg border p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-600">
                            <ShieldCheck class="h-4 w-4" />
                            {{ t('user_mangements.roles') }}
                        </div>

                        <div v-if="user.roles?.length" class="flex flex-wrap gap-2">
                            <Badge
                                v-for="role in user.roles"
                                :key="role.id"
                                variant="secondary"
                                class="bg-violet-100 text-violet-700"
                            >
                                {{ role.name }}
                            </Badge>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-600">
                            <KeyRound class="h-4 w-4" />
                            {{ t('user_mangements.additional_permissions') }}
                        </div>

                        <div v-if="user.direct_permission_details?.length" class="flex flex-wrap gap-2">
                            <Badge
                                v-for="permission in user.direct_permission_details"
                                :key="permission.id"
                                variant="outline"
                                class="text-xs"
                            >
                                {{ translatePermission(permission.name) }}
                            </Badge>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>
                </div>

                <div class="rounded-lg border p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-sm font-semibold text-violet-600">
                            <KeyRound class="h-4 w-4" />
                            {{ t('user_mangements.permissions') }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ user.effective_permissions?.length || 0 }}
                        </div>
                    </div>

                    <div
                        v-if="user.effective_permissions?.length"
                        class="flex max-h-64 flex-wrap gap-2 overflow-y-auto"
                    >
                        <Badge
                            v-for="permission in user.effective_permissions"
                            :key="permission.id"
                            variant="outline"
                            class="text-xs"
                        >
                            {{ translatePermission(permission.name) }}
                        </Badge>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">-</div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleOpenChange(false)">
                    {{ t('general.close') || 'Close' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
