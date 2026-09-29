<script setup lang="ts" generic="T extends string | number | boolean">
export interface SegmentedItem<V> { value: V; label?: string; icon?: string; title?: string; testId?: string }

const props = defineProps<{ items: SegmentedItem<T>[]; label?: string; size?: 'small' | 'default'; iconOnly?: boolean }>()
const model = defineModel<T>({ required: true })

// Arrow keys move the selection like a native radio group.
function move(event: KeyboardEvent, index: number) {
  const step = event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 0
  if (!step)
    return
  event.preventDefault()

  const next = (index + step + props.items.length) % props.items.length

  model.value = props.items[next].value;
  ((event.currentTarget as HTMLElement).parentElement?.children[next] as HTMLElement | undefined)?.focus()
}
</script>

<template>
  <div
    class="cms-segmented"
    :class="[`is-${size || 'default'}`, { 'is-icon-only': iconOnly }]"
    role="radiogroup"
    :aria-label="label"
  >
    <button
      v-for="(item, index) in items"
      :key="String(item.value)"
      type="button"
      role="radio"
      class="cms-segmented__item"
      :class="{ 'is-active': item.value === model }"
      :aria-checked="item.value === model"
      :aria-label="iconOnly ? (item.title || item.label) : undefined"
      :title="item.title || (iconOnly ? item.label : undefined)"
      :tabindex="item.value === model ? 0 : -1"
      :data-test="item.testId"
      @click="model = item.value"
      @keydown="move($event, index)"
    >
      <VIcon
        v-if="item.icon"
        :icon="item.icon"
        :size="size === 'small' ? 15 : 17"
      />
      <span
        v-if="item.label && !iconOnly"
        class="cms-segmented__label"
      >{{ item.label }}</span>
    </button>
  </div>
</template>

<style scoped>
.cms-segmented { display: inline-flex; flex: none; align-items: stretch; gap: 2px; padding: 3px; border-radius: 10px; background: rgba(var(--v-theme-on-surface), .06); }
.cms-segmented__item { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 32px; padding: 0 12px; border-radius: 7px; color: rgba(var(--v-theme-on-surface), .7); font-size: .875rem; font-weight: 500; line-height: 1; white-space: nowrap; transition: background .15s, color .15s, box-shadow .15s; }
.is-small .cms-segmented__item { min-height: 26px; padding: 0 9px; font-size: .8125rem; }
.is-icon-only .cms-segmented__item { min-width: 34px; padding: 0 8px; }
.is-small.is-icon-only .cms-segmented__item { min-width: 28px; padding: 0 6px; }
.cms-segmented__item:hover { color: rgb(var(--v-theme-on-surface)); }
.cms-segmented__item.is-active { background: rgb(var(--v-theme-surface)); color: rgb(var(--v-theme-primary)); box-shadow: 0 1px 3px rgba(0, 0, 0, .14); }
.cms-segmented__item:focus-visible { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: 1px; }
</style>
