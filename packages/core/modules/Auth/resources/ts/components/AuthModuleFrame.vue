<script setup lang="ts">
import { useAbility } from '@casl/vue'
import CoreFilterPanel from '@/components/CoreFilterPanel.vue'
import menu from '../menuItems.json'
import '../styles/auth-module.scss'

withDefaults(defineProps<{ filterCount?: number }>(), { filterCount: 0 })

const emit = defineEmits<{ reset: [] }>()
const route = useRoute()
const { t } = useI18n()
const ability = useAbility()
const links = computed(() => menu.filter(item => ability.can(item.action, item.subject)))
const current = computed(() => menu.find(item => route.path.startsWith(`/auth/${item.to.name.replace('auth-', '')}`)))
</script>

<template>
  <section class="auth-module">
    <nav
      class="auth-module-nav"
      :aria-label="t('Auth.design.navigation')"
    >
      <RouterLink
        v-for="item in links"
        :key="item.to.name"
        :to="`/auth/${item.to.name.replace('auth-', '')}`"
        :aria-current="item === current ? 'page' : undefined"
      >
        <VIcon
          :icon="item.icon.icon"
          size="20"
        />{{ t(item.title) }}
      </RouterLink>
    </nav>
    <header class="auth-module-heading">
      <VAvatar
        color="primary"
        variant="tonal"
        rounded="lg"
        size="48"
      >
        <VIcon :icon="current?.icon.icon ?? 'mdi-shield-account-outline'" />
      </VAvatar>
      <div class="auth-module-heading-text">
        <h1>{{ t(current?.title ?? 'Auth.design.title') }}</h1>
        <p>{{ t(current ? `Auth.design.${current.subject}Hint` : 'Auth.design.overviewHint') }}</p>
      </div>
      <div class="auth-module-actions">
        <slot name="actions" />
      </div>
    </header>
    <CoreFilterPanel
      v-if="$slots.filters"
      class="mb-5"
      :active-count="filterCount"
      @reset="emit('reset')"
    >
      <slot name="filters" />
    </CoreFilterPanel>
    <slot />
  </section>
</template>
