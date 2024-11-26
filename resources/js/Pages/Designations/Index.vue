<template>
    <div class="p-4">
      <!-- Search Bar -->
      <div class="flex justify-between items-center mb-4">
        <input
          type="text"
          v-model="filters.search"
          placeholder="Search designations..."
          class="input"
          @input="debouncedSearch"
        />
        <button class="button">Add Designation</button>
      </div>

      <!-- Data Table -->
      <Table>
        <TableHeader>
          <TableRow>
            <TableCell><strong>#</strong></TableCell>
            <TableCell><strong>Title</strong></TableCell>
            <TableCell><strong>Description</strong></TableCell>
            <TableCell><strong>Actions</strong></TableCell>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="(designation, index) in designations.data" :key="designation.id">
            <TableCell>{{ index + 1 + (designations.current_page - 1) * designations.per_page }}</TableCell>
            <TableCell>{{ designation.title }}</TableCell>
            <TableCell>{{ designation.description }}</TableCell>
            <TableCell>
              <button @click="editDesignation(designation)" class="text-blue-500">Edit</button>
              <button @click="deleteDesignation(designation.id)" class="text-red-500">Delete</button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <!-- Pagination -->
      <Pagination
        :current-page="designations.current_page"
        :total-pages="designations.last_page"
        @page-change="goToPage"
      />
    </div>
  </template>

  <script>
  import { reactive } from "vue";
  import { debounce } from "lodash";
  import { Inertia } from "@inertiajs/inertia";
  import { Table, TableHeader, TableBody, TableRow, TableCell, Pagination } from "@shadcn/vue";

  export default {
    components: { Table, TableHeader, TableBody, TableRow, TableCell, Pagination },
    props: {
      designations: Object,
      filters: Object,
    },
    setup(props) {
      const filters = reactive({ ...props.filters });

      const debouncedSearch = debounce(() => {
        Inertia.get(route("designations.index"), filters, { preserveState: true });
      }, 300);

      const goToPage = (page) => {
        Inertia.get(route("designations.index"), { ...filters, page }, { preserveState: true });
      };

      return {
        filters,
        debouncedSearch,
        goToPage,
      };
    },
    methods: {
      editDesignation(designation) {
        // Handle edit action
        console.log("Edit designation", designation);
      },
      deleteDesignation(id) {
        // Handle delete action
        console.log("Delete designation", id);
      },
    },
  };
  </script>
