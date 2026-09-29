<script setup lang="ts">
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@auth/stores'
import useNavigationStore from '@/stores'

definePage({ meta: { public: true } })

const { t } = useI18n()
const auth = useAuthStore()
const navigation = useNavigationStore()
const modules = computed(() => navigation.modules.filter(module => auth.hasRole('administrator') || auth.can(module.action, module.subject)))
</script>

<template>
  <VCard class="pa-6">
    <VCardTitle>{{ themeConfig.app.title }}</VCardTitle>
    <VCardText>{{ t('starter.welcome') }}</VCardText>
    <AppModules
      v-if="auth.isAuthenticated"
      :modules="modules"
    />
    <VBtn
      v-else
      :to="{ name: 'auth-login' }"
    >
      {{ t('action.login') }}
    </VBtn>
  </VCard>
</template>
