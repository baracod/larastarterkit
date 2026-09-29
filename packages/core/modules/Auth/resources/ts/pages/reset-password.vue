<script setup lang="ts">
import { AuthAPI } from '../api/Auth'
import AuthAccessPanel from '../components/AuthAccessPanel.vue'
import AuthPasswordField from '../components/AuthPasswordField.vue'
import PasswordStrengthMeter from '../components/PasswordStrengthMeter.vue'
import { useAuthForm } from '../composable/useAuthForm'
import { useAuthMailLanguage } from '@auth/composable/useAuthMailLanguage'
import AuthMailLanguageSelect from '@auth/components/AuthMailLanguageSelect.vue'

definePage({ meta: { layout: 'blank', public: true } })

const { t } = useI18n()
const route = useRoute()
const emailLocale = useAuthMailLanguage()
if (route.query.email_locale === 'fr' || route.query.email_locale === 'en')
  emailLocale.value = route.query.email_locale
const token = computed(() => typeof route.query.token === 'string' ? route.query.token : '')
const email = computed(() => typeof route.query.email === 'string' ? route.query.email : '')
const invalidLink = computed(() => !token.value || !email.value)
const expired = ref(false)
const done = ref(false)
const password = ref('')
const confirmation = ref('')
const { formRef, loading, errorMessage, passwordRules, required, beginSubmit, showError } = useAuthForm()
async function submit() {
  if (invalidLink.value || expired.value || !await beginSubmit())
    return
  try {
    await AuthAPI.validateCodeResetPasswordByToken({ emailLocale: emailLocale.value, token: token.value, email: email.value, newPassword: password.value, newPasswordConfirmation: confirmation.value })
    done.value = true
    password.value = ''
    confirmation.value = ''
  }
  catch (error: any) {
    if (error?.status === 400 || error?.status === 404 || (error?.status === 422 && (error?.data?.errors?.token || error?.data?.errors?.email)))
      expired.value = true
    else
      showError(error)
  }
  finally { loading.value = false }
}
</script>

<template>
  <AuthAccessPanel
    :title="t('Auth.access.resetTitle')"
    :description="t('Auth.access.resetDescription')"
    icon="mdi-lock-reset"
  >
    <VAlert
      v-if="done"
      type="success"
      variant="tonal"
      role="status"
      class="mb-5"
    >
      {{ t('Auth.access.passwordReset') }}
    </VAlert>
    <template v-else-if="invalidLink || expired">
      <VAlert
        type="warning"
        variant="tonal"
        role="alert"
        class="mb-5"
      >
        {{ t('Auth.access.invalidLink') }}
      </VAlert>
      <VBtn
        to="/auth/forgot-password"
        color="primary"
        block
      >
        {{ t('Auth.access.newLink') }}
      </VBtn>
    </template>
    <template v-else>
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
        ref="formRef"
        :disabled="loading"
        @submit.prevent="submit"
      >
        <AuthMailLanguageSelect v-model="emailLocale" />
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
          block
          type="submit"
          color="primary"
          size="large"
          :loading="loading"
          :disabled="loading"
          prepend-icon="mdi-check"
        >
          {{ t('Auth.access.resetAction') }}
        </VBtn>
      </VForm>
    </template>
    <RouterLink
      to="/auth/login"
      class="mt-4"
    >
      <VIcon
        icon="mdi-arrow-left"
        size="20"
      />{{ t('Auth.access.backToLogin') }}
    </RouterLink>
  </AuthAccessPanel>
</template>
