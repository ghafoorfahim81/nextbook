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
    KeyRound,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';

const props = defineProps({
    open: Boolean,
    roleId: String,
});

const emit = defineEmits(['update:open']);
const { t } = useI18n();

const role = ref(null);
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
        role.value = null;
        loading.value = false;
    }
};

watch(
    () => [props.open, props.roleId],
    async ([isOpen, roleId]) => {
        if (!isOpen || !roleId) {
            return;
        }

        loading.value = true;

        try {
            const { data } = await axios.get(`/roles/${roleId}`);
            role.value = data?.data ?? data ?? null;
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
                    <ShieldCheck class="h-6 w-6" />
                    <span>
                        {{ t('user_mangements.role') }}
                        <span v-if="role">- {{ role.name }}</span>
                    </span>
                </DialogTitle>
                <DialogDescription v-if="role?.slug">
                    {{ role.slug }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="py-8 text-center text-sm text-muted-foreground">
                {{ t('general.loading') || 'Loading' }}...
            </div>

            <div v-else-if="role" class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border p-4">
                        <div class="mb-2 text-xs text-muted-foreground">{{ t('general.name') }}</div>
                        <div class="text-sm font-medium">{{ role.name || '-' }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="mb-2 text-xs text-muted-foreground">Slug</div>
                        <div class="text-sm font-medium">{{ role.slug || '-' }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="mb-2 text-xs text-muted-foreground">Guard</div>
                        <div class="text-sm font-medium">{{ role.guard_name || '-' }}</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="mb-2 text-xs text-muted-foreground">{{ t('general.total') || 'Total' }}</div>
                        <div class="flex items-center gap-4 text-sm font-medium">
                            <span>{{ role.permissions_count || role.permissions?.length || 0 }} {{ t('user_mangements.permissions') }}</span>
                            <span>{{ role.users_count || role.users?.length || 0 }} {{ t('user_mangements.users') }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[1.4fr,1fr]">
                    <div class="rounded-lg border p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-600">
                            <KeyRound class="h-4 w-4" />
                            {{ t('user_mangements.permissions') }}
                        </div>

                        <div
                            v-if="role.permissions?.length"
                            class="flex max-h-64 flex-wrap gap-2 overflow-y-auto"
                        >
                            <Badge
                                v-for="permission in role.permissions"
                                :key="permission.id"
                                variant="outline"
                                class="text-xs"
                            >
                                {{ translatePermission(permission.name) }}
                            </Badge>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>

                    <div class="rounded-lg border p-4">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-violet-600">
                            <Users class="h-4 w-4" />
                            {{ t('user_mangements.users') }}
                        </div>

                        <div v-if="role.users?.length" class="space-y-3">
                            <div
                                v-for="user in role.users"
                                :key="user.id"
                                class="rounded-md border border-dashed p-3"
                            >
                                <div class="text-sm font-medium">{{ user.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">-</div>
                    </div>
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
