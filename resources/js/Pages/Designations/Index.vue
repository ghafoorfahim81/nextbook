<script setup>
import Layout from '@/Layouts/Layout.vue';
import { Link} from '@inertiajs/vue3';
defineProps({
  designations: Array
});
</script>

<template>
    <Head title="Designation" />

    <Layout title="| Designation">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Designation
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <table class="table-auto w-full mt-4 border-collapse border border-gray-300">
                        <thead>
                          <tr>
                            <th class="border border-gray-300 px-4 py-2">Name</th>
                            <th class="border border-gray-300 px-4 py-2">remark</th>
                            <th class="border border-gray-300 px-4 py-2">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="designation in designations" :key="designation.id">
                            <td class="border border-gray-300 px-4 py-2">{{ designation.name }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ designation.remark }}</td>
                            <td class="border border-gray-300 px-4 py-2">
                              <Link :href="`/designations/${designation.id}/edit`" class="text-blue-500">Edit</Link> |
                              <form :action="`/designations/${designation.id}`" method="POST" class="inline">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="text-red-500">Delete</button>
                              </form>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                </div>
            </div>
        </div>
    </Layout>
</template>
