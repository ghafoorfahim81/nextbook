<template>
    <label
        :for="id"
        class="absolute start-3 -top-2 z-20 rounded bg-background px-1 text-xs text-muted-foreground"
    >
        {{ label }}
    </label>
</template>

<script>
export default {
    props: ['label', 'id'],
}
</script>

<style>
</style>

