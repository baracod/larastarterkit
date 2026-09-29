<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue?: boolean
  activeCount?: number
  collapsible?: boolean
  title?: string
  description?: string
  showReset?: boolean
}>(), {
  modelValue: true,
  activeCount: 0,
  collapsible: false,
  title: undefined,
  description: undefined,
  showReset: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'reset': []
}>()

const slots = useSlots()
const { t } = useI18n()

const expanded = computed({
  get: () => !props.collapsible || props.modelValue,
  set: value => emit('update:modelValue', value),
})

const accessibleTitle = computed(() => props.title || t('workflow.filtersTitle'))
</script>

<template>
  <section
    class="core-filter-panel"
    role="search"
    :aria-label="accessibleTitle"
  >
    <header class="core-filter-panel__header">
      <div class="core-filter-panel__heading">
        <VAvatar
          color="primary"
          variant="tonal"
          rounded="lg"
          size="38"
          aria-hidden="true"
        >
          <VIcon
            icon="mdi-tune-variant"
            size="20"
          />
        </VAvatar>
        <div>
          <div class="core-filter-panel__title">
            {{ accessibleTitle }}
            <VChip
              v-if="activeCount > 0"
              color="primary"
              variant="tonal"
              size="x-small"
              :aria-label="t('workflow.filtersActive', { count: activeCount })"
            >
              {{ activeCount }}
            </VChip>
          </div>
          <p class="core-filter-panel__description">
            {{ description || t('workflow.filtersHint') }}
          </p>
        </div>
      </div>

      <div class="core-filter-panel__actions">
        <slot name="actions" />
        <VBtn
          v-if="showReset || activeCount > 0"
          variant="text"
          color="error"
          size="small"
          prepend-icon="mdi-filter-off-outline"
          :aria-label="t('workflow.filtersReset')"
          @click="emit('reset')"
        >
          {{ t('workflow.filtersReset') }}
        </VBtn>
        <VBtn
          v-if="collapsible && slots.advanced"
          variant="tonal"
          color="primary"
          size="small"
          :prepend-icon="expanded ? 'mdi-chevron-up' : 'mdi-filter-variant'"
          :aria-expanded="expanded"
          @click="expanded = !expanded"
        >
          {{ expanded ? t('workflow.filtersHide') : t('workflow.filtersShow') }}
        </VBtn>
      </div>
    </header>

    <div class="core-filter-panel__primary">
      <slot />
    </div>

    <VExpandTransition v-if="slots.advanced">
      <div
        v-show="expanded"
        class="core-filter-panel__advanced"
      >
        <slot name="advanced" />
      </div>
    </VExpandTransition>
  </section>
</template>

<style scoped>
.core-filter-panel {
  padding: 20px 24px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), .1);
  background:
    radial-gradient(circle at 100% 0, rgba(var(--v-theme-primary), .08), transparent 34%),
    rgb(var(--v-theme-surface));
}

.core-filter-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-block-end: 18px;
}

.core-filter-panel__heading,
.core-filter-panel__actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.core-filter-panel__actions { flex-wrap: wrap; justify-content: flex-end; }
.core-filter-panel__title { display: flex; align-items: center; gap: 8px; color: rgb(var(--v-theme-on-surface)); font-size: .95rem; font-weight: 700; }
.core-filter-panel__description { margin: 2px 0 0; color: rgba(var(--v-theme-on-surface), .62); font-size: .78rem; line-height: 1.4; }
.core-filter-panel__primary,
.core-filter-panel__advanced { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 14px; align-items: center; }
.core-filter-panel__advanced { padding-block-start: 16px; margin-block-start: 16px; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .08); }
.core-filter-panel :deep(.v-input) { min-width: 0; }
.core-filter-panel :deep(.v-field) { border-radius: 10px; background: rgb(var(--v-theme-surface)); }
.core-filter-panel :deep(.v-btn:focus-visible) { outline: 3px solid rgba(var(--v-theme-primary), .38); outline-offset: 2px; }
.core-filter-panel__primary :deep(> .v-col),
.core-filter-panel__advanced :deep(> .v-col) { flex: 1 1 auto !important; width: 100% !important; max-width: none !important; padding: 0 !important; }

@media (max-width: 600px) {
  .core-filter-panel { padding: 16px; }
  .core-filter-panel__header { align-items: flex-start; flex-direction: column; }
  .core-filter-panel__actions { justify-content: flex-start; width: 100%; }
  .core-filter-panel__actions :deep(.v-btn) { flex: 1; }
}
</style>
