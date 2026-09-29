<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { PermissionAPI } from '../api/Permission'
import type { IPermission } from '../types/entities'
import AuthRecordDialog from './AuthRecordDialog.vue'

const props = defineProps<{ modelValue: boolean; item?: IPermission; readonly?: boolean }>()
const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { formatErrorMessagesForm } = useTranslater()

const form = ref<Partial<IPermission>>({ key: '', action: '', subject: '', description: '', table_name: '', always_allow: 0, is_public: 0, created_at: '', updated_at: '' })
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
    form.value = { key: '', action: '', subject: '', description: '', table_name: '', always_allow: 0, is_public: 0, created_at: '', updated_at: '' }
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
      await PermissionAPI.update(form.value.id, form.value)
    else
      await PermissionAPI.create(form.value)

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
    :title="props.readonly ? t('Auth.permission.title') : `${item?.id ? t('action.edit') : t('action.add')} · ${t('Auth.permission.title')}`"
    @update:model-value="emit('update:modelValue', $event)"
    @save="save"
  >
    <VRow
      cols="12"
      class="mb-2"
    >
      <CoreTextField
        v-model="form.key"
        :label="t('Auth.permission.field.key')"
        required
        :rules="[requiredField]"
        :error-messages="errorMessage.key"
        :readonly="props.readonly"
      />
      <CoreTextField
        v-model="form.action"
        :label="t('Auth.permission.field.action')"
        required
        :rules="[requiredField]"
        :error-messages="errorMessage.action"
        :readonly="props.readonly"
      />
      <CoreTextField
        v-model="form.subject"
        :label="t('Auth.permission.field.subject')"
        required
        :rules="[requiredField]"
        :error-messages="errorMessage.subject"
        :readonly="props.readonly"
      />
      <CoreTextField
        v-model="form.description"
        :label="t('Auth.permission.field.description')"

        :error-messages="errorMessage.description"
        :readonly="props.readonly"
      />
      <CoreTextField
        v-model="form.table_name"
        :label="t('Auth.permission.field.tableName')"

        :error-messages="errorMessage.table_name"
        :readonly="props.readonly"
      />
      <VCheckbox
        v-model="form.always_allow"
        :label="t('Auth.permission.field.alwaysAllow')"
        required
        :error-messages="errorMessage.always_allow"
        :readonly="props.readonly"
      />
      <VCheckbox
        v-model="form.is_public"
        :label="t('Auth.permission.field.isPublic')"
        required
        :error-messages="errorMessage.is_public"
        :readonly="props.readonly"
      />
    </VRow>
  </AuthRecordDialog>
</template>
