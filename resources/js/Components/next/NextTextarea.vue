<template>
    <div class="relative">
        <label :for="name" class="absolute text-sm text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-3 scale-68 top-1 z-10 origin-[0] bg-white dark:bg-gray-900 px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-1 peer-focus:scale-75 peer-focus:-translate-y-3 start-1 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto">{{ label }}</label>
        <textarea
            id="message"
            rows="4"
            :name="name"
            :value="value"
            @input="updateValue"
            :readonly="readonly ?? false"
            class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"
        />
    </div>
</template>

<script setup>
defineProps({
    name: String,
    value: String,
    label: String,
    readonly: Boolean
});

const emit = defineEmits(['update:value']);

const updateValue = (event) => {
    emit('update:value', event.target.value);
};
</script>
