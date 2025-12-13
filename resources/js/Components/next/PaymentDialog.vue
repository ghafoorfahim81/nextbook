<script setup>
  import { computed, ref, reactive, watch } from 'vue'
  import { useForm, router } from '@inertiajs/vue3'
  import { Input } from '@/Components/ui/input'
  import { Textarea } from '@/Components/ui/textarea'
  import { Label } from '@/Components/ui/label'
  import ModalDialog from '@/Components/next/Dialog.vue'
  import vSelect from 'vue-select'
  import NextInput from "@/Components/next/NextInput.vue";
  import FloatingLabel from "@/Components/next/FloatingLabel.vue";
  import NextTextarea from "@/Components/next/NextTextarea.vue";
  import NextSelect from "@/Components/next/NextSelect.vue";
  import { useI18n } from 'vue-i18n';
  const { t } = useI18n()
  const props = defineProps({
      isDialogOpen: Boolean,
      editingItem: Object, // ✅ this is passed from Index.vue
      accounts: {
        type: Array,
        default: () => [],
      },
      errors: Object,
  })
  
  const emit = defineEmits(['update:isDialogOpen', 'saved'])
  
  const accounts = computed(() => props.accounts ?? props.accounts)
  
  const localDialogOpen = ref(props.isDialogOpen)
  
  watch(() => props.isDialogOpen, (val) => {
      localDialogOpen.value = val
  })
  
  watch(() => localDialogOpen.value, (val) => {
      emit('update:isDialogOpen', val)
  })
  
  const isEditing = computed(() => !!props.editingItem?.id)
  
  const form = useForm({
      name: '',
      remark: '',
      parent_id: null,
  })
  
  
  watch(() => props.editingItem, (item) => {
      if (item) {
          form.name = item.name || ''
          form.remark = item.remark || ''
          form.parent_id = item.parent_id || null
      } else {
          form.reset() // ✅ reset when switching from edit to create
      }
  }, { immediate: true })
  
  const closeModal = () => {
      localDialogOpen.value = false
  }
  
  const handleParentSelectChange = (value) => {
      form.parent_id = value
  }
  
  const handleSubmit = async () => {
      if (isEditing.value) {
          form.patch(route('categories.update', props.editingItem.id), {
              onSuccess: () => {
                  emit('saved')
                  form.reset();
                  closeModal()
              },
          })
      } else {
          form.post('/categories', {
              onSuccess: () => {
                  emit('saved')
                  form.reset();
                  closeModal()
              },
          })
      }
  }
  
  
  </script>
  
  <template>
      <ModalDialog
          :open="localDialogOpen"
          :title="isEditing ? t('general.edit', { name: t('admin.category.category') }) : t('general.create', { name: t('admin.category.category') })"
          :confirmText="isEditing ? t('general.update') : t('general.create')"
          :cancel-text="t('general.close')"
          @update:open="localDialogOpen = $event; emit('update:isDialogOpen', $event)"
          :closeable="true"
          @confirm="handleSubmit"
          @cancel="closeModal"
      >
  
          <form @submit.prevent="handleSubmit" id="modalForm">
              <div class="grid gap-4 py-4"> 
                   <NextSelect
                      v-model="form.account_id"
                      :options="accounts"
                      label-key="name"
                      @update:modelValue="(value) => handleAccountSelectChange(value)"
                      value-key="id"
                      id="account"
                      :floating-text="t('admin.shared.account')"
                      :error="form.errors?.account_id"
                      :searchable="true"
                      resource-type="accounts"
                      :search-fields="['name']"
                      /> 
                  
              </div>
          </form>
      </ModalDialog>
  </template>
  