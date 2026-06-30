<!-- AttachmentUploader.vue — reusable multi-file attachment input (new + existing) -->
<template>
    <div class="flex flex-col space-y-3">
        <label v-if="label" class="text-sm font-medium">{{ label }}</label>

        <!-- Drag & drop / browse zone -->
        <div
            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors"
            :class="isDragging
                ? 'border-primary bg-primary/5'
                : 'border-input bg-background hover:border-primary'"
            role="button"
            tabindex="0"
            @click="inputRef?.click()"
            @keydown.enter.prevent="inputRef?.click()"
            @keydown.space.prevent="inputRef?.click()"
            @dragenter.prevent="isDragging = true"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="onDragLeave"
            @drop.prevent="onDrop"
        >
            <div class="relative mb-3">
                <FileUp class="h-10 w-10 text-muted-foreground" />
                <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-foreground text-background">
                    <Upload class="h-3 w-3" />
                </span>
            </div>
            <div class="text-sm text-muted-foreground">
                <span>{{ t('general.attachment_drop_here') }}</span><br>
                <span class="font-medium text-primary underline">{{ t('general.attachment_click_browse') }}</span>
            </div>
            <input
                :id="id"
                ref="inputRef"
                type="file"
                multiple
                class="hidden"
                :accept="accept"
                @change="onFileChange"
            />
        </div>

        <div class="flex items-center justify-between text-xs text-muted-foreground">
            <span>{{ t('general.attachment_supported_files') }}: .pdf, .doc, .docx, .xls, .xlsx, .jpg, .png, .webp</span>
            <span>{{ t('general.attachment_max_size', { size: maxSizeMb }) }}</span>
        </div>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

        <!-- Existing (already saved) attachments -->
        <ul v-if="existing.length" class="flex flex-col gap-2">
            <li
                v-for="item in existing"
                :key="item.id"
                class="flex items-center gap-3 rounded-xl border border-input px-3 py-2.5"
            >
                <a :href="item.url" target="_blank" rel="noopener noreferrer" class="shrink-0">
                    <img
                        v-if="isImage(item.mime_type)"
                        :src="item.url"
                        class="h-9 w-9 rounded object-cover"
                        alt=""
                    />
                    <span v-else class="flex h-9 w-9 items-center justify-center rounded bg-muted">
                        <FileText class="h-5 w-5 text-muted-foreground" />
                    </span>
                </a>
                <div class="min-w-0 flex-1">
                    <a
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block truncate text-sm font-medium hover:underline"
                    >
                        {{ item.original_name }}
                    </a>
                    <p class="text-xs text-muted-foreground">{{ formatSize(item.size) }}</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-muted-foreground hover:text-red-600"
                    @click="$emit('remove-existing', item.id)"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </li>
        </ul>

        <!-- Newly selected (pending) files -->
        <ul v-if="modelValue.length" class="flex flex-col gap-2">
            <li
                v-for="(file, index) in modelValue"
                :key="index"
                class="flex items-center gap-3 rounded-xl border border-input px-3 py-2.5"
            >
                <img
                    v-if="previews[index]"
                    :src="previews[index]"
                    class="h-9 w-9 shrink-0 rounded object-cover"
                    alt=""
                />
                <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-muted">
                    <FileText class="h-5 w-5 text-muted-foreground" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ file.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatSize(file.size) }}</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-muted-foreground hover:text-red-600"
                    @click="removeNew(index)"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Upload, FileUp, Trash2, FileText } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps({
    id: { type: String, default: 'attachments' },
    label: { type: String, default: '' },
    modelValue: { type: Array, default: () => [] },
    existing: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    accept: { type: String, default: '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp' },
    maxSizeMb: { type: Number, default: 10 },
});

const emit = defineEmits(['update:modelValue', 'remove-existing']);

const inputRef = ref(null);
const previews = ref([]);
const isDragging = ref(false);

const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'];

const isImage = (mime) => typeof mime === 'string' && mime.startsWith('image/');

const formatSize = (bytes) => {
    const size = Number(bytes);
    if (!size || size < 0) return '0 KB';
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const buildPreviews = (files) => {
    previews.value = [];
    files.forEach((file, index) => {
        if (file.type && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => { previews.value[index] = e.target.result; };
            reader.readAsDataURL(file);
        } else {
            previews.value[index] = null;
        }
    });
};

watch(() => props.modelValue, (files) => buildPreviews(files), { immediate: true });

const addFiles = (fileList) => {
    const incoming = Array.from(fileList || []);
    const accepted = [];

    incoming.forEach((file) => {
        const ext = file.name.split('.').pop()?.toLowerCase();
        if (!allowedExtensions.includes(ext)) {
            toast.error(t('general.attachment_invalid_type', {   extensions: allowedExtensions.join(', ') }), { class: 'bg-red-600' });
            return;
        }
        if (file.size > props.maxSizeMb * 1024 * 1024) {
            toast.error(t('general.attachment_too_large', { name: file.name, size: props.maxSizeMb }), { class: 'bg-red-600' });
            return;
        }
        accepted.push(file);
    });

    if (accepted.length) {
        emit('update:modelValue', [...props.modelValue, ...accepted]);
    }
};

const onFileChange = (event) => {
    addFiles(event.target.files);
    // Reset native input so the same file can be re-selected after removal.
    if (inputRef.value) inputRef.value.value = '';
};

const onDrop = (event) => {
    isDragging.value = false;
    addFiles(event.dataTransfer?.files);
};

const onDragLeave = (event) => {
    // Only clear when the cursor actually leaves the zone, not a child element.
    if (!event.currentTarget.contains(event.relatedTarget)) {
        isDragging.value = false;
    }
};

const removeNew = (index) => {
    const next = [...props.modelValue];
    next.splice(index, 1);
    emit('update:modelValue', next);
};
</script>
