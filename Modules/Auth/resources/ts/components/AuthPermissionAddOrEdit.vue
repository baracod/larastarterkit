<script setup lang="ts">
import { defineEmits, defineProps, onMounted, ref, watch } from 'vue'
import { VBtn, VCard, VCardActions, VCardText, VCardTitle, VDialog, VForm, VRow } from 'vuetify/components'
import { PermissionAPI } from '../api/Permission'
import type { IPermission } from '../types/entities'

const props = defineProps<{ modelValue: boolean; item?: IPermission; readonly: boolean }>()
const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { formatErrorMessages } = useTranslater()

const form = ref<IPermission>({ id: null, key: '', action: '', subject: '', description: '', table_name: '', always_allow: 0, is_public: 0, created_at: '', updated_at: '' })
const loading = ref(false)

const errorMessage = ref<Record<string, string>>({})

watch(() => props.item, newItem => {
  if (newItem)
    form.value = { ...newItem }
  else
    form.value = { id: null, key: '', action: '', subject: '', description: '', table_name: '', always_allow: 0, is_public: 0, created_at: '', updated_at: '' }
}, { immediate: true })

onMounted(async () => {

})

const save = async () => {
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
    errorMessage.value = formatErrorMessages(error.data.errors, 'notification')
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
      <VCardTitle v-if="readonly">
        {{ t('Auth.permission.title') }}
      </VCardTitle>
      <VCardTitle v-else>
        {{ item ? t('action.edit') : t('action.add') }}  {{ t('Auth.permission.title') }}
      </VCardTitle>
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow
            cols="12"
            class="mb-2"
          >
            <CoreTextField
              v-model="form.key"
              :label="t('Auth.permission.field.key')"
              required
              :error-messages="errorMessage.key"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.action"
              :label="t('Auth.permission.field.action')"
              required
              :error-messages="errorMessage.action"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.subject"
              :label="t('Auth.permission.field.subject')"
              required
              :error-messages="errorMessage.subject"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.description"
              :label="t('Auth.permission.field.description')"
              required
              :error-messages="errorMessage.description"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.table_name"
              :label="t('Auth.permission.field.tableName')"
              required
              :error-messages="errorMessage.table_name"
              :readonly="readonly"
            />
            <VCheckbox
              v-model="form.always_allow"
              :label="t('Auth.permission.field.alwaysAllow')"
              required
              :error-messages="errorMessage.always_allow"
              :readonly="readonly"
            />
            <VCheckbox
              v-model="form.is_public"
              :label="t('Auth.permission.field.isPublic')"
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
