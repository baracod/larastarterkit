<script setup lang="ts">
import AuthAccessPanel from '@auth/components/AuthAccessPanel.vue'

definePage({ meta: { layout: 'blank', unauthenticatedOnly: true } })

const { t } = useI18n()
</script>

<template>
  <AuthAccessPanel
    :title="t('Auth.access.accountTitle')"
    :description="t('Auth.access.accountDescription')"
    icon="mdi-account-key-outline"
  >
    <VBtn
      to="/auth/login"
      color="primary"
      block
      prepend-icon="mdi-arrow-left"
    >
      {{ t('Auth.access.backToLogin') }}
    </VBtn>
  </AuthAccessPanel>
</template>
