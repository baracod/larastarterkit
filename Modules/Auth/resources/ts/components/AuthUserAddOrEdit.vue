<script setup lang="ts">
import { defineEmits, defineProps, onMounted, ref, watch } from 'vue'
import { UserAPI } from '../api/User'
import type { IUser } from '../types/entities'

const props = defineProps<{ modelValue: boolean; item?: IUser; readonly: boolean }>()
const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { formatErrorMessages } = useTranslater()
const { notify } = useNotify()
const form = ref<IUser>({ id: null, name: '', username: '', email: '', additional_info: '', avatar: '', email_verified_at: '', password: '', remember_token: '', active: 0, created_at: '', updated_at: '' })
const loading = ref(false)

const errorMessage = ref<Record<string, string>>({})

watch(() => props.item, newItem => {
  if (newItem)
    form.value = { ...newItem }
  else
    form.value = { id: null, name: '', username: '', email: '', additional_info: '', avatar: '', email_verified_at: '', password: '', remember_token: '', active: 0, created_at: '', updated_at: '' }
}, { immediate: true })

onMounted(async () => {

})

const save = async () => {
  loading.value = true
  try {
    if (form.value.id)
      await UserAPI.update(form.value.id, form.value)
    else
      await UserAPI.create(form.value)

    emit('saved')
    emit('update:modelValue', false)
  }
  catch (error: any) {
    notify({
      type: 'error',
      message: error.data.message || '',
    })
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
    <VCard
      :loading="loading"
      :disabled="loading"
    >
      <VCardTitle v-if="readonly">
        {{ t('Auth.permission.title') }}
      </VCardTitle>
      <VCardTitle v-else>
        {{ item.id ? t('action.edit') : t('action.add') }}  {{ t('Auth.user.title') }}
      </VCardTitle>
      <VCardText>
        <VForm @submit.prevent="save">
          <VRow
            cols="12"
            class="mb-2"
          >
            <CoreTextField
              v-model="form.name"
              :label="t('Auth.user.field.name')"
              required
              :error-messages="errorMessage.name"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.username"
              :label="t('Auth.user.field.username')"
              required
              :error-messages="errorMessage.username"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.email"
              :label="t('Auth.user.field.email')"
              required
              :error-messages="errorMessage.email"
              :readonly="readonly"
            />
            <CoreTextField
              v-model="form.password"
              :label="t('Auth.user.field.password')"
              required
              :error-messages="errorMessage.password"
              :readonly="readonly"
            />

            <CoreTextarea
              v-model="form.additional_info"
              :label="t('Auth.user.field.additionalInfo')"
              required
              :error-messages="errorMessage.additional_info"
              :readonly="readonly"
            />
            <VCheckbox
              v-model="form.active"
              :label="t('Auth.user.field.active')"
              required
              :error-messages="errorMessage.active"
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
