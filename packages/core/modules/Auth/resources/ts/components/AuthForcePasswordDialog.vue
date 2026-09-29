<script setup lang="ts">
import AuthRecordDialog from './AuthRecordDialog.vue'
import { useAuthMailLanguage } from '@auth/composable/useAuthMailLanguage'
import AuthMailLanguageSelect from '@auth/components/AuthMailLanguageSelect.vue'
import { UserAPI } from '@auth/api/User'
import type { IUser } from '@auth/types/entities'

const props = defineProps<{
  modelValue: boolean
  user: IUser | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'done'): void
}>()

const emailLocale = useAuthMailLanguage()

const { t } = useI18n({ useScope: 'global' })
const { notify } = useNotify()

const isDialogVisible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const formRef = ref()
const loading = ref(false)
const errors = ref<Record<string, string>>({})

watch(isDialogVisible, val => {
  if (!val)
    errors.value = {}
})

const submit = async () => {
  if (loading.value || !props.user?.id || !await formRef.value?.validate())
    return

  errors.value = {}
  loading.value = true
  try {
    await UserAPI.forceChangePassword({
      emailLocale: emailLocale.value,
      userId: props.user.id,
    })

    notify({ type: 'success', message: t('Auth.user.forceReset.success') })
    isDialogVisible.value = false
    emit('done')
  }
  catch (error: any) {
    errors.value = { general: error?.data?.message || t('Auth.user.forceReset.error') }
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthRecordDialog
    ref="formRef"
    v-model="isDialogVisible"
    :title="t('Auth.user.forceReset.title')"
    :busy="loading"
    :save-label="t('Auth.accountMail.send')"
    :errors="errors"
    @save="submit"
  >
    <p
      v-if="user"
      class="mb-4 text-medium-emphasis"
    >
      {{ t('Auth.user.forceReset.subtitle', { name: user.name, email: user.email }) }}
    </p>

    <AuthMailLanguageSelect v-model="emailLocale" />
  </AuthRecordDialog>
</template>
