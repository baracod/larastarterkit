<script setup lang="ts">
import type { StarterSlotName, StarterSlots } from '@/extension/define'
import { resolveSlot } from '@/extension/runtime'

const props = withDefaults(defineProps<{ name: StarterSlotName; context?: Record<string, unknown> }>(), { context: () => ({}) })
const slots = inject<StarterSlots>('larastarterkit:slots', {})
const resolved = computed(() => resolveSlot(slots[props.name], props.context))
</script>

<template>
  <Component
    :is="resolved.replacement"
    v-if="resolved.replacement"
  />
  <template v-else>
    <slot />
    <Component
      :is="contribution.component"
      v-for="contribution in resolved.contributions"
      :key="contribution.key"
      v-bind="contribution.props"
    />
  </template>
</template>
