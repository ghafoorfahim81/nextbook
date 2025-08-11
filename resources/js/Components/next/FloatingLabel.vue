<template>
    <label :for="id" class="absolute text-xs text-gray-500 dark:text-gray-400 duration-300 transform
             -translate-y-3 scale-68 top-1 z-10 origin-[0] bg-white dark:bg-gray-900 px-2 peer-focus:px-2
              peer-focus:text-blue-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100
               peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-1
               peer-focus:scale-75 peer-focus:-translate-y-3 start-1 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto">{{
            label
        }}</label>
</template>

<script>
export default {
    props: ['label','id'],

}
</script>

<style>

</style>

