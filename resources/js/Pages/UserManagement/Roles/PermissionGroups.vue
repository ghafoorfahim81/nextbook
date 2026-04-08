<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Checkbox from '@/Components/Checkbox.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';

const props = defineProps({
    permissions: {
        type: Array,
        default: () => [],
    },
    modelValue: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const search = ref('');

const actionOrder = {
    view_any: 0,
    view: 1,
    create: 2,
    update: 3,
    edit: 3,
    delete: 4,
    import: 5,
    export: 6,
    print: 7,
    approve: 8,
    reject: 9,
    email: 10,
};

const humanize = (value) => {
    if (!value) return '';

    return value
        .replace(/_/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const translatePermission = (name) => {
    const key = `permissions.${name}`;
    const translated = t(key);

    return translated === key ? humanize(name) : translated;
};

const resourceLabel = (resource) => humanize(resource);

const groupedPermissions = computed(() => {
    const groups = new Map();

    props.permissions.forEach((permission) => {
        const [resource = '', action = ''] = String(permission.name || '').split('.');
        const key = resource || 'other';

        if (!groups.has(key)) {
            groups.set(key, {
                resource: key,
                permissions: [],
            });
        }

        groups.get(key).permissions.push({
            ...permission,
            action,
            label: translatePermission(permission.name),
        });
    });

    return Array.from(groups.values())
        .sort((a, b) => resourceLabel(a.resource).localeCompare(resourceLabel(b.resource)))
        .map((group) => ({
            ...group,
            permissions: group.permissions.sort((a, b) => {
                const aOrder = actionOrder[a.action] ?? 100;
                const bOrder = actionOrder[b.action] ?? 100;

                if (aOrder !== bOrder) {
                    return aOrder - bOrder;
                }

                return a.label.localeCompare(b.label);
            }),
        }));
});

const allPermissionIds = computed(() => props.permissions.map((permission) => permission.id));

const filteredGroups = computed(() => {
    const q = search.value.trim().toLowerCase();

    if (!q) {
        return groupedPermissions.value;
    }

    return groupedPermissions.value
        .map((group) => {
            const resourceMatches = resourceLabel(group.resource).toLowerCase().includes(q) || group.resource.toLowerCase().includes(q);

            if (resourceMatches) {
                return group;
            }

            const permissions = group.permissions.filter((permission) => {
                return (
                    permission.label.toLowerCase().includes(q) ||
                    permission.name.toLowerCase().includes(q)
                );
            });

            return permissions.length ? { ...group, permissions } : null;
        })
        .filter(Boolean);
});

const updatePermissions = (nextPermissions) => {
    emit('update:modelValue', Array.from(new Set(nextPermissions)));
};

const togglePermission = (permissionId, checked) => {
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];

    if (checked) {
        updatePermissions([...current, permissionId]);
        return;
    }

    updatePermissions(current.filter((id) => id !== permissionId));
};

const visiblePermissionIds = (group) => group.permissions.map((permission) => permission.id);

const allVisibleSelected = (group) => {
    const ids = visiblePermissionIds(group);

    return ids.length > 0 && ids.every((id) => props.modelValue.includes(id));
};

const someVisibleSelected = (group) => {
    const ids = visiblePermissionIds(group);

    return ids.some((id) => props.modelValue.includes(id));
};

const toggleGroup = (group, checked) => {
    const ids = visiblePermissionIds(group);
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];

    if (checked) {
        updatePermissions([...current, ...ids]);
        return;
    }

    updatePermissions(current.filter((id) => !ids.includes(id)));
};

const selectAllPermissions = () => {
    updatePermissions(allPermissionIds.value);
};

const clearAllPermissions = () => {
    updatePermissions([]);
};
</script>

<template>
    <div class="space-y-3">
        <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Input
                v-model="search"
                type="text"
                class="w-full md:max-w-md border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-300 dark:bg-gray-800 dark:border-gray-700"
                :placeholder="t('general.search_placeholder', { name: t('user_mangements.permissions') }).toLowerCase()"
                autocomplete="off"
            />

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-md border border-primary/20 bg-primary/10 px-3 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary/15"
                    @click="selectAllPermissions"
                >
                    {{ t('general.select_all') }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
                    @click="clearAllPermissions"
                >
                    {{ t('general.clear') }}
                </button>
            </div>
        </div>

        <div v-if="filteredGroups.length === 0" class="text-sm text-muted-foreground px-4 py-6 text-center">
            {{ t('general.no_results', { name: t('user_mangements.permissions') }) }}
        </div>

        <div v-else class="max-h-[70vh] overflow-y-auto pr-1">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="group in filteredGroups"
                    :key="group.resource"
                    class="overflow-hidden border-border/70 bg-card/95 shadow-sm"
                >
                    <CardHeader class="border-b border-border/60 pb-3">
                        <div class="flex items-center justify-between gap-3">
                            <CardTitle class="text-base font-semibold">
                                {{ resourceLabel(group.resource) }}
                            </CardTitle>
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    :id="`resource-${group.resource}`"
                                    :checked="allVisibleSelected(group)"
                                    :indeterminate="someVisibleSelected(group) && !allVisibleSelected(group)"
                                    @update:checked="(checked) => toggleGroup(group, Boolean(checked))"
                                />
                                <label :for="`resource-${group.resource}`" class="text-xs font-medium text-muted-foreground cursor-pointer">
                                    {{ t('general.select_all') }}
                                </label>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-3 pt-4">
                        <div
                            v-for="permission in group.permissions"
                            :key="permission.id"
                            class="flex items-start gap-3 rounded-lg px-1 py-1.5 hover:bg-muted/50"
                        >
                            <Checkbox
                                :id="`permission-${permission.id}`"
                                :name="`permissions[]`"
                                :value="permission.id"
                                :checked="modelValue.includes(permission.id)"
                                @update:checked="(checked) => togglePermission(permission.id, Boolean(checked))"
                            />
                            <label
                                :for="`permission-${permission.id}`"
                                class="cursor-pointer text-sm leading-5 text-muted-foreground hover:text-foreground"
                                :title="permission.name"
                            >
                                {{ permission.label }}
                            </label>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
