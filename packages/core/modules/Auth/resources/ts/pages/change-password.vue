<script setup lang="ts">
import { UserAPI } from '../api/User'
import AuthAccessPanel from '../components/AuthAccessPanel.vue'
import AuthPasswordField from '../components/AuthPasswordField.vue'
import PasswordStrengthMeter from '../components/PasswordStrengthMeter.vue'
import { useAuthForm } from '../composable/useAuthForm'
import { useAuthStore } from '../stores'
import { useAuthMailLanguage } from '@auth/composable/useAuthMailLanguage'
import AuthMailLanguageSelect from '@auth/components/AuthMailLanguageSelect.vue'

const emailLocale = useAuthMailLanguage()

definePage({ meta: { layout: 'blank', authenticatedOnly: true } })

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()
const isForced = computed(() => !!authStore.user?.mustChangePassword)

const { formRef, loading, errorMessage, passwordRules, required, beginSubmit, showError } = useAuthForm()
const currentPassword = ref('')
const password = ref('')
const confirmation = ref('')
const done = ref(false)
async function submit() {
  if (!authStore.user || !await beginSubmit())
    return
  try {
    await UserAPI.changePassword({ emailLocale: emailLocale.value, userId: authStore.user.id, currentPassword: currentPassword.value, newPassword: password.value, newPasswordConfirmation: confirmation.value })
    authStore.markPasswordChanged()
    done.value = true
    currentPassword.value = ''
    password.value = ''
    confirmation.value = ''
  }
  catch (error: any) {
    if (error?.status === 422 || error?.status === 400)
      errorMessage.value = error?.data?.message || t('Auth.security.feedback.genericError')
    else
      showError(error)
  }
  finally { loading.value = false }
}
</script>

<template>
  <AuthAccessPanel
    :title="t('Auth.access.changeTitle')"
    :description="t(isForced ? 'Auth.access.changeRequired' : 'Auth.access.changeDescription')"
    icon="mdi-shield-key-outline"
  >
    <RouterLink
      to="/auth/forgot-password"
      class="d-inline-block mb-4"
    >
      {{ t('Auth.access.forgotLink') }}
    </RouterLink>
    <VAlert
      v-if="done"
      type="success"
      variant="tonal"
      role="status"
      class="mb-5"
    >
      {{ t('Auth.security.feedback.passwordUpdated') }}
    </VAlert>
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      role="alert"
      class="mb-5"
    >
      {{ errorMessage }}
    </VAlert>
    <VForm
      v-if="!done"
      ref="formRef"
      :disabled="loading"
      @submit.prevent="submit"
    >
      <AuthMailLanguageSelect v-model="emailLocale" />
      <AuthPasswordField
        v-model="currentPassword"
        :label="t('Auth.security.fields.currentPassword')"
        autocomplete="current-password"
        :rules="[required]"
      />
      <AuthPasswordField
        v-model="password"
        :label="t('Auth.security.fields.newPassword')"
        :rules="passwordRules"
      />
      <AuthPasswordField
        v-model="confirmation"
        :label="t('Auth.security.fields.confirmPassword')"
        :rules="[required, (value: string) => value === password || t('Auth.security.validation.confirmationMismatch')]"
      />
      <PasswordStrengthMeter
        :password="password"
        :confirm-password="confirmation"
      />
      <VBtn
        type="submit"
        color="primary"
        size="large"
        block
        :loading="loading"
        :disabled="loading"
        prepend-icon="mdi-check"
      >
        {{ t('Auth.access.changeAction') }}
      </VBtn>
    </VForm>
    <VBtn
      v-if="done"
      color="primary"
      block
      @click="router.replace('/')"
    >
      {{ t('Auth.access.continue') }}
    </VBtn>
    <RouterLink
      v-else-if="!isForced"
      to="/"
      class="mt-4"
    >
      <VIcon
        icon="mdi-arrow-left"
        size="20"
      />{{ t('Auth.access.backToApp') }}
    </RouterLink>
  </AuthAccessPanel>
</template>
