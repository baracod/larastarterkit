<script setup lang="ts">
import { AuthAPI } from '../api/Auth'
import AuthAccessPanel from '../components/AuthAccessPanel.vue'
import { useAuthForm } from '../composable/useAuthForm'
import { useAuthMailLanguage } from '@auth/composable/useAuthMailLanguage'
import AuthMailLanguageSelect from '@auth/components/AuthMailLanguageSelect.vue'

const emailLocale = useAuthMailLanguage()

definePage({ meta: { layout: 'blank', public: true } })

const { t } = useI18n()
const email = ref('')
const sent = ref(false)
const { formRef, loading, errorMessage, emailRules, beginSubmit, showError } = useAuthForm()
async function submit() {
  if (!await beginSubmit())
    return
  try {
    await AuthAPI.forgottenPassword({ email_locale: emailLocale.value, email: email.value.trim() })
    sent.value = true
  }
  catch (error: any) {
    if (error?.status === 404 || error?.status === 422)
      sent.value = true
    else
      showError(error)
  }
  finally { loading.value = false }
}
</script>

<template>
  <AuthAccessPanel
    :title="t('Auth.access.forgotTitle')"
    :description="t('Auth.access.forgotDescription')"
    icon="mdi-email-lock-outline"
  >
    <VAlert
      v-if="sent"
      type="success"
      variant="tonal"
      role="status"
      class="mb-5"
    >
      {{ t('Auth.access.emailSent') }}
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
      v-if="!sent"
      ref="formRef"
      :disabled="loading"
      @submit.prevent="submit"
    >
      <AuthMailLanguageSelect v-model="emailLocale" />
      <AppTextField
        v-model="email"
        :label="t('Auth.access.email')"
        type="email"
        autocomplete="email"
        inputmode="email"
        autocapitalize="none"
        :spellcheck="false"
        prepend-inner-icon="mdi-email-outline"
        :rules="emailRules"
      />
      <VBtn
        type="submit"
        color="primary"
        size="large"
        block
        :loading="loading"
        :disabled="loading"
        prepend-icon="mdi-email-fast-outline"
      >
        {{ t('Auth.access.sendLink') }}
      </VBtn>
    </VForm>
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
