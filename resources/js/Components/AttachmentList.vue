<!-- AttachmentList.vue — read-only list of saved attachments (for Show pages) -->
<template>
    <div v-if="items.length" class="flex flex-col space-y-2">
        <label v-if="label" class="text-sm font-medium">{{ label }}</label>
        <ul class="flex flex-wrap gap-2">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex items-center gap-2 rounded-md border border-input px-3 py-2 text-sm"
            >
                <a
                    :href="item.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center gap-2 truncate hover:underline"
                >
                    <img
                        v-if="isImage(item.mime_type)"
                        :src="item.url"
                        class="h-8 w-8 rounded object-cover"
                        alt=""
                    />
                    <FileText v-else class="h-5 w-5 shrink-0 text-muted-foreground" />
                    <span class="truncate max-w-[220px]">{{ item.original_name }}</span>
                </a>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { FileText } from 'lucide-vue-next';

defineProps({
    items: { type: Array, default: () => [] },
    label: { type: String, default: '' },
});

const isImage = (mime) => typeof mime === 'string' && mime.startsWith('image/');
</script>
