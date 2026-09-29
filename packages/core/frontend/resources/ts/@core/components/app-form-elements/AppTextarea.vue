<script lang="ts" setup>
import type { VTextarea } from 'vuetify/components/VTextarea'

defineOptions({
  name: 'AppTextarea',
  inheritAttrs: false,
})

const slots = defineSlots<InstanceType<typeof VTextarea>['$slots']>()

// const { class: _class, label, variant: _, ...restAttrs } = useAttrs()

const elementId = computed (() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id || attrs.label

  return _elementIdToken ? `app-textarea-${_elementIdToken}-${Math.random().toString(36).slice(2, 7)}` : undefined
})

const label = computed(() => useAttrs().label as string | undefined)
const slotNames = computed(() => Object.keys(slots) as Array<keyof InstanceType<typeof VTextarea>['$slots']>)
</script>

<template>
  <div
    class="app-textarea flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2"
      :text="label"
    />
    <VTextarea
      v-bind="{
        ...$attrs,
        class: null,
        label: undefined,
        variant: 'outlined',
        id: elementId,
      }"
    >
      <template
        v-for="name in slotNames"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="(slotProps || {}) as any"
        />
      </template>
    </VTextarea>
  </div>
</template>
