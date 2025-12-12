<script setup lang="ts">
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

import authV2ForgotPasswordIllustration from '@images/pages/auth-v2-forgot-password-illustration.png'
import { AuthAPI } from '@auth/api/Auth'

const email = ref('')
const { t } = useI18n()
const loading = ref(false)

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const submitForm = () => {
  loading.value = true
  AuthAPI.forgottenPassword({ email: email.value })
    .then(() => {
      useDialog().confirmDialog({
        title: t('Auth.pages.forgotPassword.dialog.resetEmailSentTitle'),
        message: t('Auth.pages.forgotPassword.dialog.resetEmailSentMessage', { email: email.value }),
        color: 'success',
        persistent: true,
        type: 'info',
      })
    })
    .catch(err => {
      useDialog().confirmDialog({
        title: t('Auth.pages.forgotPassword.dialog.resetEmailErrorTitle'),
        message: t('Auth.pages.forgotPassword.dialog.resetEmailErrorMessage'),
        color: 'error',
        persistent: true,
        type: 'alert',
      })
      console.error('Error sending password reset instructions:', err)
    }).finally(() => {
      loading.value = false
    })
}
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-2">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    class="auth-wrapper bg-surface"
    no-gutters
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 pa-8">
        <div class="d-flex align-center justify-center w-100 h-100">
          <VImg
            max-width="700"
            :src="authV2ForgotPasswordIllustration"
            class="auth-illustration"
          />
        </div>
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-6"
        :loading="loading"
        :disabled="loading"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            {{ t('Auth.pages.forgotPassword.title') }} 🔒
          </h4>
          <p class="mb-0">
            {{ t('Auth.pages.forgotPassword.subtitle') }}
          </p>
        </VCardText>

        <VCardText>
          <VForm @submit.prevent="submitForm">
            <VRow>
              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="email"
                  autofocus
                  :label="t('Auth.pages.forgotPassword.email')"
                  type="email"
                  placeholder="johndoe@email.com"
                />
              </VCol>

              <!-- Reset link -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                >
                  {{ t('Auth.pages.forgotPassword.action.sendResetLink') }}
                </VBtn>
              </VCol>

              <!-- back to login -->
              <VCol cols="12">
                <RouterLink
                  class="d-flex align-center justify-center"
                  :to="{ name: 'auth-login' }"
                >
                  <VIcon
                    icon="bx-chevron-left"
                    size="20"
                    class="me-1 flip-in-rtl"
                  />
                  <span>{{ t('Auth.pages.forgotPassword.backToLogin') }}</span>
                </RouterLink>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth.scss";
</style>
