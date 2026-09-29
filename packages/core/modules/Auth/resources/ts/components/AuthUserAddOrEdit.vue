<script setup lang="ts">
import { useAuthForm } from '../composable/useAuthForm'
import { RoleAPI } from '../api/Role'
import { UserAPI } from '../api/User'
import type { IRole, IUser } from '../types/entities'
import AuthRecordDialog from './AuthRecordDialog.vue'
import AuthPasswordField from './AuthPasswordField.vue'
import { useAuthMailLanguage } from '@auth/composable/useAuthMailLanguage'
import AuthMailLanguageSelect from '@auth/components/AuthMailLanguageSelect.vue'
import { useAuthStore } from '@auth/stores'

const props = defineProps<{ modelValue: boolean; item?: IUser; readonly?: boolean }>()

const emit = defineEmits(['update:modelValue', 'saved'])

const emailLocale = useAuthMailLanguage()

const { t } = useI18n()
const { formatErrorMessagesForm } = useTranslater()
const { notify } = useNotify()
const { passwordRules, emailRules } = useAuthForm()
const form = ref<Partial<IUser>>({ name: '', username: '', email: '', additional_info: '', avatar: '', email_verified_at: '', password: '', remember_token: '', active: 0, created_at: '', updated_at: '' })
const auth = useAuthStore()
const canManageRoles = computed(() => auth.hasRole('administrator'))
const loading = ref(false)
const recordDialog = ref<InstanceType<typeof AuthRecordDialog> | null>(null)
const requiredField = (value: unknown) => (value !== null && value !== undefined && String(value).trim().length > 0) || t('Auth.access.required')

const errorMessage = ref<Record<string, string>>({})

const availableRoles = ref<IRole[]>([])
const selectedRoleIds = ref<number[]>([])

const fetchRoles = async () => {
  if (!canManageRoles.value)
    return
  try {
    availableRoles.value = await RoleAPI.getAll()
  }
  catch (error) {
    console.error('Failed to fetch roles', error)
  }
}

watch(() => [props.item, props.modelValue] as const, ([newItem, open]) => {
  if (!open)
    return
  errorMessage.value = {}

  if (newItem) {
    form.value = { ...newItem }
    selectedRoleIds.value = newItem.roles?.map(r => r.id) ?? []
  }
  else {
    form.value = { name: '', username: '', email: '', additional_info: '', avatar: '', email_verified_at: '', password: '', remember_token: '', active: 0, created_at: '', updated_at: '' }
    selectedRoleIds.value = []
  }
}, { immediate: true })

onMounted(async () => {
  await fetchRoles()
})

const save = async () => {
  if (props.readonly || loading.value || !await recordDialog.value?.validate())
    return
  if (loading.value)
    return

  loading.value = true
  try {
    let savedUser: IUser
    const payload = { ...form.value }
    if (form.value.id)
      delete payload.password
    if (form.value.id)
      savedUser = await UserAPI.update(form.value.id, payload)

    else
      savedUser = await UserAPI.create({ ...payload, email_locale: emailLocale.value })

    const userId = savedUser?.id ?? form.value.id
    if (userId && canManageRoles.value) {
      form.value.id = userId
      await UserAPI.assignRoles(userId, selectedRoleIds.value)
    }

    emit('saved')
    emit('update:modelValue', false)
  }
  catch (error: any) {
    notify({
      type: 'error',
      message: error.data?.message || '',
    })
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
    :title="props.readonly ? t('Auth.user.title') : `${item?.id ? t('action.edit') : t('action.add')} · ${t('Auth.user.title')}`"
    @update:model-value="emit('update:modelValue', $event)"
    @save="save"
  >
    <AuthMailLanguageSelect
      v-if="!form.id && !props.readonly"
      v-model="emailLocale"
    />
    <VRow
      cols="12"
      class="mb-2"
    >
      <CoreTextField
        v-model="form.name"
        :label="t('Auth.user.field.name')"
        required
        :rules="[requiredField]"
        :error-messages="errorMessage.name"
        :readonly="props.readonly"
      />
      <CoreTextField
        v-model="form.username"
        :label="t('Auth.user.field.username')"
        required
        :rules="[requiredField]"
        :error-messages="errorMessage.username"
        :readonly="props.readonly"
      />
      <CoreTextField
        v-model="form.email"
        :label="t('Auth.user.field.email')"
        required
        type="email"
        :rules="emailRules"
        :error-messages="errorMessage.email"
        :readonly="props.readonly"
      />
      <VCol
        v-if="!props.readonly && !form.id"
        cols="12"
      >
        <AuthPasswordField
          :model-value="form.password ?? ''"
          :label="t('Auth.user.field.password')"
          :rules="passwordRules"
          :hint="t('Auth.design.newPasswordHint')"
          persistent-hint
          :error-messages="errorMessage.password"
          @update:model-value="form.password = $event"
        />
      </VCol>

      <CoreTextarea
        v-model="form.additional_info"
        :label="t('Auth.user.field.additionalInfo')"
        :error-messages="errorMessage.additional_info"
        :readonly="props.readonly"
      />
      <VCheckbox
        v-model="form.active"
        :label="t('Auth.user.field.active')"
        required
        :error-messages="errorMessage.active"
        :readonly="props.readonly"
      />

      <VCol
        v-if="canManageRoles"
        cols="12"
      >
        <VSelect
          v-model="selectedRoleIds"
          :items="availableRoles"
          item-title="display_name"
          item-value="id"
          :label="t('Auth.role.titlePlural')"
          multiple
          chips
          closable-chips
          :readonly="props.readonly"
          :error-messages="errorMessage.roles"
        />
      </VCol>
    </VRow>
  </AuthRecordDialog>
</template>
