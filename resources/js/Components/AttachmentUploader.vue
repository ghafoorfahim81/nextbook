<!-- AttachmentUploader.vue — reusable multi-file attachment input (new + existing) -->
<template>
    <div class="flex flex-col space-y-2">
        <label v-if="label" class="mb-1 text-sm font-medium">{{ label }}</label>

        <!-- Drop / browse zone -->
        <label
            :for="id"
            class="flex cursor-pointer flex-col items-center justify-center rounded-md border border-dashed border-input bg-background px-4 py-6 text-center text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
        >
            <Upload class="mb-2 h-5 w-5" />
            <span>{{ t('general.attachment_upload_hint') }}</span>
            <input
                :id="id"
                ref="inputRef"
                type="file"
                multiple
                class="hidden"
                :accept="accept"
                @change="onFileChange"
            />
        </label>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

        <!-- Existing (already saved) attachments -->
        <ul v-if="existing.length" class="flex flex-col gap-2">
            <li
                v-for="item in existing"
                :key="item.id"
                class="flex items-center justify-between rounded-md border border-input px-3 py-2 text-sm"
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
                    <span class="truncate">{{ item.original_name }}</span>
                </a>
                <button
                    type="button"
                    class="ml-2 shrink-0 text-red-500 hover:text-red-700"
                    @click="$emit('remove-existing', item.id)"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </li>
        </ul>

        <!-- Newly selected (pending upload) files -->
        <ul v-if="modelValue.length" class="flex flex-col gap-2">
            <li
                v-for="(file, index) in modelValue"
                :key="index"
                class="flex items-center justify-between rounded-md border border-input bg-muted/40 px-3 py-2 text-sm"
            >
                <span class="flex items-center gap-2 truncate">
                    <img
                        v-if="previews[index]"
                        :src="previews[index]"
                        class="h-8 w-8 rounded object-cover"
                        alt=""
                    />
                    <FileText v-else class="h-5 w-5 shrink-0 text-muted-foreground" />
                    <span class="truncate">{{ file.name }}</span>
                </span>
                <button
                    type="button"
                    class="ml-2 shrink-0 text-red-500 hover:text-red-700"
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
import { Upload, Trash2, FileText } from 'lucide-vue-next';

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

const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'];

const isImage = (mime) => typeof mime === 'string' && mime.startsWith('image/');

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

const onFileChange = (event) => {
    const incoming = Array.from(event.target.files || []);
    const accepted = [];

    incoming.forEach((file) => {
        const ext = file.name.split('.').pop()?.toLowerCase();
        if (!allowedExtensions.includes(ext)) {
            toast.error(t('general.attachment_invalid_type', { name: file.name }), { class: 'bg-red-600' });
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

    // Reset native input so the same file can be re-selected after removal.
    if (inputRef.value) inputRef.value.value = '';
};

const removeNew = (index) => {
    const next = [...props.modelValue];
    next.splice(index, 1);
    emit('update:modelValue', next);
};
</script>
