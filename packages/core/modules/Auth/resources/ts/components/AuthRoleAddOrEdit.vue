<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RoleAPI } from '../api/Role'
import type { IRole } from '../types/entities'
import AuthRecordDialog from './AuthRecordDialog.vue'

const props = defineProps<{ modelValue: boolean; item?: IRole; readonly?: boolean }>()
const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { formatErrorMessagesForm } = useTranslater()

const form = ref<Partial<IRole>>({ name: '', display_name: '', description: '', order: 0, is_owner: 0, created_at: '', updated_at: '' })
const loading = ref(false)
const recordDialog = ref<InstanceType<typeof AuthRecordDialog> | null>(null)
const requiredField = (value: unknown) => (value !== null && value !== undefined && String(value).trim().length > 0) || t('Auth.access.required')

const errorMessage = ref<Record<string, string>>({})

watch(() => [props.item, props.modelValue] as const, ([newItem, open]) => {
  if (!open)
    return
  errorMessage.value = {}

  if (newItem)
    form.value = { ...newItem }
  else
    form.value = { name: '', display_name: '', description: '', order: 0, is_owner: 0, created_at: '', updated_at: '' }
}, { immediate: true })

onMounted(async () => {

})

const save = async () => {
  if (props.readonly || loading.value || !await recordDialog.value?.validate())
    return
  if (loading.value)
    return

  loading.value = true
  try {
    if (form.value.id)
      await RoleAPI.update(form.value.id, form.value)
    else
      await RoleAPI.create(form.value)

    emit('saved')
    emit('update:modelValue', false)
  }
  catch (error: any) {
    errorMessage.value = formatErrorMessagesForm(error?.data?.errors ?? {}, 'notification')
    if (!Object.keys(errorMessage.value).length)
      errorMessage.value = { general: t('Auth.access.requestError') }
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthRecordDialog
    ref="recordDialog"
    :model-value="modelValue"
    :readonly="props.readonly"
    :busy="loading"
    :errors="errorMessage"
    :title="props.readonly ? t('Auth.role.title') : `${item?.id ? t('action.edit') : t('action.add')} · ${t('Auth.role.title')}`"
    @update:model-value="emit('update:modelValue', $event)"
    @save="save"
  >
    <VRow
      cols="12"
      class="mb-2"
    >
      <CoreTextField
        v-model="form.name"
        :label="t('Auth.role.field.name')"
        required
        :rules="[requiredField]"
        :error-messages="errorMessage.name"
        :readonly="props.readonly"
      />
      <CoreTextField
        v-model="form.display_name"
        :label="t('Auth.role.field.displayName')"
        required
        :rules="[requiredField]"
        :error-messages="errorMessage.display_name"
        :readonly="props.readonly"
      />
      <CoreTextarea
        v-model="form.description"
        :label="t('Auth.role.field.description')"

        :error-messages="errorMessage.description"
        :readonly="props.readonly"
        xl="12"
      />
    </VRow>
  </AuthRecordDialog>
</template>
