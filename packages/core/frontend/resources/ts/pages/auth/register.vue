<script setup lang="ts">
import AuthAccessPanel from '@auth/components/AuthAccessPanel.vue'
import { resolveRedirect } from '@/extension/runtime'

definePage({ meta: { layout: 'blank', unauthenticatedOnly: true } })

const { t } = useI18n()
</script>

<template>
  <AuthAccessPanel
    :title="t('Auth.access.accountTitle')"
    :description="t('Auth.access.accountDescription')"
    icon="mdi-account-key-outline"
  >
    <StarterSlot name="auth-register-end" />
    <VBtn
      :to="resolveRedirect('login')"
      color="primary"
      block
      prepend-icon="mdi-arrow-left"
    >
      {{ t('Auth.access.backToLogin') }}
    </VBtn>
  </AuthAccessPanel>
</template>
