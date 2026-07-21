<template>
    <label
        :for="id"
        class="absolute start-3 -top-2 z-20 rounded bg-background px-1 text-xs text-muted-foreground"
    >
        {{ label }}
        <span v-if="required" class="text-red-500 ms-0.5">*</span>
    </label>
</template>

<script>
export default {
    props: {
        label: { type: String, default: '' },
        id: { type: String, default: '' },
        required: { type: Boolean, default: false },
    },
}
</script>

<style>
</style>
