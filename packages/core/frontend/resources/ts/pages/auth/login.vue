<script setup lang="ts">
import AuthMailLanguageSelect from '@auth/components/AuthMailLanguageSelect.vue'
import { useAuthMailLanguage } from '@auth/composable/useAuthMailLanguage'

import { AuthAPI } from '@auth/api/Auth'
import AuthAccessPanel from '@auth/components/AuthAccessPanel.vue'
import AuthPasswordField from '@auth/components/AuthPasswordField.vue'
import { useAuthForm } from '@auth/composable/useAuthForm'
import { useAuthStore } from '@auth/stores'
import type { IUser } from '@auth/types/entities'

const emailLocale = useAuthMailLanguage()

definePage({ meta: { layout: 'blank', unauthenticatedOnly: true } })

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { formRef, loading, errorMessage, emailRules, required, beginSubmit, showError } = useAuthForm()
const credentials = ref({ email: '', password: '' })
const rememberMe = ref(false)

const destination = computed(() => {
  const target = route.query.to

  return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') && !target.includes('\\') && !target.startsWith('/auth/login') ? target : '/'
})

async function login() {
  if (!await beginSubmit())
    return
  try {
    const res = await AuthAPI.login({ email_locale: emailLocale.value, ...credentials.value, email: credentials.value.email.trim(), remember_me: rememberMe.value })
    if (!res.data)
      throw new Error('Empty login response')
    const { user, abilityRules, permissions, roles } = res.data

    useCookie<IUser>('userData').value = user
    useCookie('accessToken').value = null

    const { hydrate } = useAuthStore()

    await hydrate({
      user,
      roles,
      permissions: permissions ?? [],
      abilityRules,
    })

    await nextTick(() => {
      router.replace(destination.value)
    })
  }
  catch (error: any) { showError(error) }
  finally { loading.value = false }
}
</script>

<template>
  <AuthAccessPanel
    :title="t('Auth.access.loginTitle')"
    :description="t('Auth.access.loginDescription')"
    icon="mdi-login-variant"
  >
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
      validate-on="blur"
      :disabled="loading"
      @submit.prevent="login"
    >
      <AuthMailLanguageSelect v-model="emailLocale" />
      <AppTextField
        v-model="credentials.email"
        :label="t('Auth.access.email')"
        type="email"
        autocomplete="username"
        inputmode="email"
        autocapitalize="none"
        :spellcheck="false"
        prepend-inner-icon="mdi-email-outline"
        :rules="emailRules"
      />
      <AuthPasswordField
        v-model="credentials.password"
        :label="t('Auth.access.password')"
        autocomplete="current-password"
        :rules="[required, (value: string) => value.length >= 8 || t('Auth.security.validation.minLength')]"
      />
      <div class="auth-access-links">
        <VCheckbox
          v-model="rememberMe"
          :label="t('Auth.access.remember')"
          hide-details
          density="compact"
        />
        <RouterLink to="/auth/forgot-password">
          {{ t('Auth.access.forgotLink') }}
        </RouterLink>
      </div>
      <VBtn
        block
        type="submit"
        color="primary"
        size="large"
        :loading="loading"
        :disabled="loading"
        prepend-icon="mdi-login"
      >
        {{ t('Auth.access.loginAction') }}
      </VBtn>
    </VForm>
    <p class="text-caption text-medium-emphasis mt-5 mb-0">
      {{ t('Auth.access.authorizedOnly') }}
    </p>
  </AuthAccessPanel>
</template>
