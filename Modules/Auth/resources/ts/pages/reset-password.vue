<script setup lang="ts">
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

import authV2ResetPasswordIllustration from '@images/pages/auth-v2-reset-password-illustration.png'
import { AuthAPI } from '@auth/api/Auth'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const form = ref({
  newPassword: '',
  confirmPassword: '',
})

const { t } = useI18n()

const route = useRoute()
const email = route.query.email as string
const loading = ref(false)

const router = useRouter()

const submitForm = () => {
  loading.value = true
  AuthAPI.validateCodeResetPasswordByToken({
    newPassword: form.value.newPassword,
    newPasswordConfirmation: form.value.confirmPassword,
    token: route.query.token as string,
    email,
  })

    .then(async () => {
      const { confirmDialog } = useDialog()

      confirmDialog({
        title: t('Auth.pages.resetPassword.dialog.resetPasswordSuccessTitle'),
        message: t('Auth.pages.resetPassword.dialog.resetPasswordSuccessMessage'),
        color: 'success',
        persistent: true,
        type: 'info',
      })

      await nextTick(() => {
        router.replace({ name: 'auth-login' })
      })
    })
    .catch(err => {
      useDialog().confirmDialog({
        title: t('Auth.pages.resetPassword.dialog.resetPasswordErrorTitle'),
        message: t('Auth.pages.resetPassword.dialog.resetPasswordErrorMessage'),
        color: 'error',
        persistent: true,
        type: 'alert',
      })
      console.error('Error resetting password:', err)
    }).finally(() => {
      loading.value = false
    })
}

console.log('Reset token:', route.query.token)

const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
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
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 pa-8">
        <div class="d-flex align-center justify-center w-100 h-100">
          <VImg
            max-width="700"
            :src="authV2ResetPasswordIllustration"
            class="auth-illustration"
          />
        </div>
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
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
            {{ t('Auth.pages.resetPassword.title') }} 🔒
          </h4>
          <p class="mb-0">
            <strong>{{ email }}</strong> <br>
            {{ t('Auth.pages.resetPassword.subtitle') }}
          </p>
        </VCardText>

        <VCardText>
          <VForm @submit.prevent="submitForm">
            <VRow>
              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.newPassword"
                  autofocus
                  :label="t('Auth.pages.resetPassword.newPassword')"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="password"
                  :append-inner-icon="isPasswordVisible ? 'bx-hide' : 'bx-show'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>

              <!-- Confirm Password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.confirmPassword"
                  :label="t('Auth.pages.resetPassword.confirmPassword')"
                  autocomplete="confirm-password"
                  placeholder="············"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="
                    isConfirmPasswordVisible ? 'bx-hide' : 'bx-show'
                  "
                  @click:append-inner="
                    isConfirmPasswordVisible = !isConfirmPasswordVisible
                  "
                />
              </VCol>

              <!-- Set password -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                >
                  {{ t('Auth.pages.resetPassword.action.resetPassword') }}
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
                  <span>{{ t('Auth.pages.resetPassword.backToLogin') }}</span>
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
@use "@core-scss/template/pages/page-auth";
</style>
