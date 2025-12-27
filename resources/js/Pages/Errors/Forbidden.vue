<script setup>
const props = defineProps({
  status: {
    type: Number,
    default: 403,
  },
  message: {
    type: String,
    default: 'This action is unauthorized.',
  },
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
    <div class="max-w-md w-full bg-white dark:bg-gray-800 shadow-md rounded-lg p-8 text-center">
      <div class="text-6xl font-bold text-gray-900 dark:text-gray-100 mb-2">
        {{ status }}
      </div>
      <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
        Forbidden
      </h1>
      <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">
        {{ message }}
      </p>
      <a
        href="/dashboard"
        class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-gray-100 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
      >
        Go to dashboard
      </a>
    </div>
  </div>
</template>


