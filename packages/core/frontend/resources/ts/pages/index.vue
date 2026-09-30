<script setup lang="ts">
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@auth/stores'
import useNavigationStore from '@/stores'

definePage({ meta: { public: true } })

const { t, te } = useI18n()
const auth = useAuthStore()
const navigation = useNavigationStore()
const modules = computed(() => navigation.modules.filter(module => auth.hasRole('administrator') || auth.can(module.action, module.subject)))
const moduleLabel = (title: string) => te(`navigation.moduleLabels.${title}`) ? t(`navigation.moduleLabels.${title}`) : title
</script>

<template>
  <VCard class="pa-6">
    <VCardTitle>{{ themeConfig.app.title }}</VCardTitle>
    <VCardText>{{ t('starter.welcome') }}</VCardText>
    <VCardText v-if="auth.isAuthenticated">
      <VRow v-if="modules.length">
        <VCol
          v-for="module in modules"
          :key="module.title"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <VCard
            :to="module.to"
            variant="outlined"
            class="text-center pa-4 h-100"
          >
            <VAvatar
              variant="tonal"
              color="primary"
              size="56"
            >
              <VIcon
                size="32"
                :icon="module.icon"
              />
            </VAvatar>
            <h6 class="text-h6 mt-3 mb-0">
              {{ moduleLabel(module.title) }}
            </h6>
          </VCard>
        </VCol>
      </VRow>
      <p
        v-else
        class="mb-0"
      >
        {{ t('navigation.noModules') }}
      </p>
    </VCardText>
    <VBtn
      v-else
      :to="{ name: 'auth-login' }"
    >
      {{ t('action.login') }}
    </VBtn>
  </VCard>
</template>
