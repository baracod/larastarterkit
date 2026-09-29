<script setup lang="ts">
import { useAbility } from '@casl/vue'
import AuthModuleFrame from '../components/AuthModuleFrame.vue'
import menu from '../menuItems.json'

definePage({ meta: { action: 'access', subject: 'auth' } })

const { t } = useI18n()
const ability = useAbility()
const links = computed(() => menu.filter(item => ability.can(item.action, item.subject)))
</script>

<template>
  <AuthModuleFrame>
    <VRow>
      <VCol
        v-for="item in links"
        :key="item.to.name"
        cols="12"
        md="4"
      >
        <VCard
          :to="item.to"
          border
          class="h-100 pa-5"
        >
          <VIcon
            :icon="item.icon.icon"
            color="primary"
            size="32"
            class="mb-4"
          />
          <h2 class="text-h6">
            {{ t(item.title) }}
          </h2>
          <p class="text-medium-emphasis mt-3 mb-0">
            {{ t(`Auth.design.${item.subject}Hint`) }}
          </p>
        </VCard>
      </VCol>
    </VRow>
  </AuthModuleFrame>
</template>
