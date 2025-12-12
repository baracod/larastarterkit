<script setup lang="ts">
import { defineEmits, defineProps, onMounted, ref, watch } from 'vue'
import { RoleAPI } from '../api/Role'
import type { IRole } from '../types/entities'

const props = defineProps<{ modelValue: boolean; item?: IRole; readonly: boolean }>()
const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { formatErrorMessages } = useTranslater()

const form = ref<IRole>({ id: null, name: '', display_name: '', description: '', order: 0, is_owner: 0, created_at: '', updated_at: '' })
const loading = ref(false)

const errorMessage = ref<Record<string, string>>({})

watch(() => props.item, newItem => {
  if (newItem)
    form.value = { ...newItem }
  else
    form.value = { id: null, name: '', display_name: '', description: '', order: 0, is_owner: 0, created_at: '', updated_at: '' }
}, { immediate: true })

onMounted(async () => {

})

const save = async () => {
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
        {{ t('Auth.role.title') }}
      </VCardTitle>
      <VCardTitle v-else>
        {{ item?.id ? t('action.edit') : t('action.add') }}  {{ t('Auth.role.title') }}
      </VCardTitle>

      <VCardText>
        <VForm @submit.prevent="save">
          <VRow
            cols="12"
            class="mb-2"
          >
            <CoreTextField
              v-model="form.name"
              :label="t('Auth.role.field.name')"
              required
              :error-messages="errorMessage.name"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.display_name"
              :label="t('Auth.role.field.displayName')"
              required
              :error-messages="errorMessage.display_name"
              :readonly="readonly"
            />
            <CoreTextarea
              v-model="form.description"
              :label="t('Auth.role.field.description')"
              required
              :error-messages="errorMessage.description"
              :readonly="readonly"
              xl="12"
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
