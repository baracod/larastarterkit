<script setup lang="ts">
import CoreJourneyNav from './CoreJourneyNav.vue'
import CoreFilterPanel from './CoreFilterPanel.vue'

withDefaults(defineProps<{
  title: string
  subtitle: string
  activeFilterCount?: number
}>(), { activeFilterCount: 0 })

const emit = defineEmits<{ resetFilters: [] }>()
</script>

<template>
  <section class="workflow-page">
    <CoreJourneyNav />
    <header class="workflow-page__heading">
      <div><h1>{{ title }}</h1><p>{{ subtitle }}</p></div>
      <div class="workflow-page__actions">
        <slot name="actions" />
      </div>
    </header>
    <slot name="summary" />
    <VCard
      class="workflow-page__body"
      variant="flat"
      border
    >
      <CoreFilterPanel
        v-if="$slots.filters"
        :active-count="activeFilterCount"
        @reset="emit('resetFilters')"
      >
        <slot name="filters" />
      </CoreFilterPanel>
      <slot />
    </VCard>
    <slot name="dialogs" />
  </section>
</template>

<style scoped>
.workflow-page { max-width: 1560px; margin-inline: auto; }
.workflow-page__heading { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; margin-block: 8px 24px; }
h1 { font-size: clamp(24px, 2.2vw, 32px); line-height: 1.25; letter-spacing: -.035em; font-weight: 700; }
p { margin: 8px 0 0; color: rgba(var(--v-theme-on-surface), .65); max-width: 680px; line-height: 1.65; }
.workflow-page__actions { display: flex; flex-wrap: wrap; gap: 10px; }
.workflow-page__body { border-radius: 16px; overflow: hidden; }
.workflow-page :deep(th) { background: rgba(var(--v-theme-on-surface), .025); color: rgba(var(--v-theme-on-surface), .6); font-size: 11px !important; letter-spacing: .04em; }
.workflow-page :deep(td) { padding-block: 16px !important; font-size: 13px; }
.workflow-page :deep(code) { font-weight: 650; overflow-wrap: anywhere; }
.workflow-page :deep(.v-btn:focus-visible) { outline: 3px solid rgba(var(--v-theme-primary), .45); outline-offset: 3px; }
</style>
