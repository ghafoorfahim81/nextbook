<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import NextInput from '@/Components/next/NextInput.vue'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const page = usePage()
const owner = page.props.owner?.data || page.props.owner

const form = useForm({
  name: owner?.name || '',
  father_name: owner?.father_name || '',
  nic: owner?.nic || '',
  email: owner?.email || '',
  address: owner?.address || '',
  phone_number: owner?.phone_number || '',
  ownership_percentage: owner?.ownership_percentage ?? 100,
  is_active: owner?.is_active ?? true,
})

function submit() {
  form.patch(`/owners/${owner.id}`)
}
</script>

<template>
  <AppLayout :title="t('general.edit', { name: 'Owner' })">
    <form @submit.prevent="submit()">
      <div class="mb-5 rounded-xl border p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.edit', { name: 'Owner' }) }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextInput :label="t('general.name')" v-model="form.name" :error="form.errors?.name" />
          <NextInput :label="t('admin.shared.father_name')" v-model="form.father_name" :error="form.errors?.father_name" />
          <NextInput label="NIC" v-model="form.nic" :error="form.errors?.nic" />
          <NextInput :label="t('admin.shared.email')" v-model="form.email" type="email" :error="form.errors?.email"/>
          <NextInput :label="t('admin.shared.phone')" v-model="form.phone_number" type="text" :error="form.errors?.phone_number"/>
          <NextInput :label="t('general.address')" v-model="form.address" type="text" :error="form.errors?.address"/>
          <NextInput :label="t('admin.shared.percentage')" v-model="form.ownership_percentage" type="number" :error="form.errors?.ownership_percentage"/>
          <div class="col-span-1 flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">{{ t('general.active') }}</label>
            <input type="checkbox" v-model="form.is_active" />
          </div>
        </div>
      </div>
      <div class="mt-4 flex gap-2">
        <button type="submit" class="btn btn-primary px-4 py-2 rounded-md bg-primary text-white">{{ t('general.update') }}</button>
        <button type="button" class="btn px-4 py-2 rounded-md border" @click="() => $inertia.visit('/owners')">{{ t('general.cancel') }}</button>
      </div>
    </form>
  </AppLayout>
</template>


