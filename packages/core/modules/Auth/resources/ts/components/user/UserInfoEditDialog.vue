<script setup lang="ts">
import { UserAPI } from '../../api/User'
import type { IBioEditable } from '../../types/entities'
import { optimizeImage } from '../../utils/imageOptimizer'
import { useAuthForm } from '../../composable/useAuthForm'
import { useAuthStore } from '../../stores'
import AuthRecordDialog from '../AuthRecordDialog.vue'

const props = defineProps<{ userData: IBioEditable; isDialogVisible: boolean }>()
const emit = defineEmits<{ 'update:isDialogVisible': [value: boolean]; profileUpdated: [] }>()
const { t } = useI18n()
const { formatErrorMessagesForm } = useTranslater()
const { emailRules, required } = useAuthForm()
const authStore = useAuthStore()
const dialog = ref<InstanceType<typeof AuthRecordDialog> | null>(null)
const form = ref({ name: '', username: '', email: '', additional_info: '' })
const avatarFile = ref<File | File[] | null>(null)
const preview = ref<string | null>(null)
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const initials = computed(() => form.value.name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase())

const fileRules = [(value: File | File[] | null) => {
  const file = Array.isArray(value) ? value[0] : value

  return !file || ['image/jpeg', 'image/png', 'image/gif'].includes(file.type) || t('Auth.editProfile.imageFormat')
}]

function clearPreview() {
  if (preview.value)
    URL.revokeObjectURL(preview.value)
  preview.value = null
}
watch(avatarFile, file => {
  clearPreview()

  const selectedFile = Array.isArray(file) ? file[0] : file
  if (selectedFile)
    preview.value = URL.createObjectURL(selectedFile)
})
watch(() => [props.isDialogVisible, props.userData.id] as const, ([open]) => {
  if (!open)
    return
  const user = props.userData

  form.value = { name: user.name ?? '', username: user.username ?? '', email: user.email ?? '', additional_info: typeof user.additional_info === 'string' ? user.additional_info : user.additional_info ? JSON.stringify(user.additional_info) : '' }
  avatarFile.value = null
  errors.value = {}
}, { immediate: true })
onBeforeUnmount(clearPreview)

async function save() {
  if (!props.userData.id || saving.value || !await dialog.value?.validate() || saving.value)
    return
  saving.value = true
  errors.value = {}
  try {
    const selectedFile = Array.isArray(avatarFile.value) ? avatarFile.value[0] : avatarFile.value
    const file = selectedFile ? await optimizeImage(selectedFile, 400, 0.85) : undefined
    if (file && file.size > 2 * 1024 * 1024) {
      errors.value = { general: t('Auth.editProfile.imageSize') }

      return
    }
    const updatedUser = await UserAPI.updateProfile(Number(props.userData.id), { ...form.value, name: form.value.name.trim(), username: form.value.username.trim(), email: form.value.email.trim() }, file)

    authStore.updateCurrentUser(updatedUser)
    emit('profileUpdated')
    emit('update:isDialogVisible', false)
  }
  catch (error: any) {
    errors.value = formatErrorMessagesForm(error?.data?.errors ?? {}, 'Auth.user')
    errors.value.general = t('Auth.access.requestError')
  }
  finally { saving.value = false }
}
</script>

<template>
  <AuthRecordDialog
    ref="dialog"
    :model-value="isDialogVisible"
    :title="t('Auth.editProfile.title')"
    :busy="saving"
    :errors="errors"
    @update:model-value="emit('update:isDialogVisible', $event)"
    @save="save"
  >
    <p class="text-medium-emphasis mb-5">
      {{ t('Auth.editProfile.description') }}
    </p>
    <VRow>
      <VCol
        cols="12"
        class="d-flex align-center flex-wrap gap-4"
      >
        <VAvatar
          size="80"
          color="primary"
          :image="preview || props.userData.avatar || undefined"
        >
          <span v-if="!preview && !props.userData.avatar">{{ initials }}</span>
        </VAvatar>
        <VFileInput
          v-model="avatarFile"
          :label="t('Auth.user.field.avatar')"
          accept="image/jpeg,image/png,image/gif"
          prepend-icon=""
          prepend-inner-icon="mdi-camera-outline"
          :rules="fileRules"
          :hint="t('Auth.editProfile.imageHelp')"
          persistent-hint
          :error-messages="errors.avatar_file"
          class="flex-grow-1"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model="form.name"
          :label="t('Auth.user.field.name')"
          :rules="[required]"
          maxlength="255"
          autocomplete="name"
          :error-messages="errors.name"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          v-model="form.username"
          :label="t('Auth.user.field.username')"
          :rules="[required]"
          maxlength="255"
          autocomplete="username"
          :error-messages="errors.username"
        />
      </VCol>
      <VCol cols="12">
        <AppTextField
          v-model="form.email"
          :label="t('Auth.user.field.email')"
          :rules="emailRules"
          type="email"
          maxlength="255"
          autocomplete="email"
          :error-messages="errors.email"
        />
      </VCol>
      <VCol cols="12">
        <AppTextarea
          v-model="form.additional_info"
          :label="t('Auth.user.field.additionalInfo')"
          :error-messages="errors.additional_info"
          rows="3"
        />
      </VCol>
    </VRow>
  </AuthRecordDialog>
</template>
