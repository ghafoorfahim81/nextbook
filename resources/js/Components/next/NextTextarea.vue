<template>
    <div class="relative">
        <label :for="name"
        class="absolute text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-3 scale-68 top-1 z-10 origin-[0] bg-white
        dark:bg-gray-900 px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100
        peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-1 peer-focus:scale-75 peer-focus:-translate-y-3
         start-1 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto"

        >{{ label }}</label>

        <textarea
            id="message"
            rows="4"
            :name="name"
            :placeholder="placeholder??t('general.enter', { text: label })"
            :value="modelValue"
            @input="e => emit('update:modelValue', e.target.value)"
            :readonly="readonly ?? false"
            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background
             placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring
             focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        />
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: String, default: '' },
    name: String,
    label: String,
    readonly: Boolean,
    placeholder: String,
});
const emit = defineEmits(['update:modelValue']);
</script>
