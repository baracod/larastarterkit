<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { AdminSettingAPI } from '../api/AdminSetting'
import type { IAdminSetting } from '../types/entities'

const props = defineProps<{ modelValue: boolean; item?: IAdminSetting; readonly: boolean }>()
const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { formatErrorMessagesForm } = useTranslater()

const form = ref<IAdminSetting>({ type: 'system', module: '', user_id: 0, key: '', value: '', value_type: '', label: '', description: '', input_type: '', options: '', default_value: '', is_public: 0 })
const loading = ref(false)

const errorMessage = ref<Record<string, string>>({})

watch(() => props.item, newItem => {
  if (newItem)
    form.value = { ...newItem }
  else
    form.value = { type: 'system', module: '', user_id: 0, key: '', value: '', value_type: '', label: '', description: '', input_type: '', options: '', default_value: '', is_public: 0 }
}, { immediate: true })

onMounted(async () => {

})

const save = async () => {
  loading.value = true
  try {
    if (form.value.id)
      await AdminSettingAPI.update(form.value.id, form.value)
    else
      await AdminSettingAPI.create(form.value)

    emit('saved')
    emit('update:modelValue', false)
  }
  catch (error: any) {
    errorMessage.value = formatErrorMessagesForm(error.data.errors, 'Admin.adminSetting')
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="70%"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle v-if="props.readonly">
        {{ t('Admin.adminSetting.title') }}
      </VCardTitle>
      <VCardTitle v-else>
        {{ props.item?.id ? t('action.edit') : t('action.add') }}  {{ t('Admin.adminSetting.title') }}
      </VCardTitle>
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow
            cols="12"
            class="mb-2"
          >
            <CoreTextarea
              v-model="form.type"
              :label="t('Admin.adminSetting.field.type')"
              required
              :error-messages="errorMessage.type"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.module"
              :label="t('Admin.adminSetting.field.module')"
              required
              :error-messages="errorMessage.module"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.user_id"
              type="number"
              :label="t('Admin.adminSetting.field.userId')"
              required
              :error-messages="errorMessage.user_id"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.key"
              :label="t('Admin.adminSetting.field.key')"
              required
              :error-messages="errorMessage.key"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.value"
              :label="t('Admin.adminSetting.field.value')"
              required
              :error-messages="errorMessage.value"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.value_type"
              :label="t('Admin.adminSetting.field.valueType')"
              required
              :error-messages="errorMessage.value_type"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.label"
              :label="t('Admin.adminSetting.field.label')"
              required
              :error-messages="errorMessage.label"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.description"
              :label="t('Admin.adminSetting.field.description')"
              required
              :error-messages="errorMessage.description"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.input_type"
              :label="t('Admin.adminSetting.field.inputType')"
              required
              :error-messages="errorMessage.input_type"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.options"
              :label="t('Admin.adminSetting.field.options')"
              required
              :error-messages="errorMessage.options"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.default_value"
              :label="t('Admin.adminSetting.field.defaultValue')"
              required
              :error-messages="errorMessage.default_value"
              :readonly="readonly"
            />
            <VCheckbox
              v-model="form.is_public"
              :label="t('Admin.adminSetting.field.isPublic')"
              required
              :error-messages="errorMessage.is_public"
              :readonly="readonly"
            />
          </VRow>
        </VForm>
      </VCardText>
      <VCardActions>
        <VBtn
          type="submit"
          :loading="loading"
          @click="save"
        >
          {{ item?.id ? t('action.edit') : t('action.add') }}
        </VBtn>
        <VBtn
          variant="outlined"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ t('action.cancel') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
