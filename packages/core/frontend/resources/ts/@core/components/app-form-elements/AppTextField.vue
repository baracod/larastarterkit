<script lang="ts" setup>
import type { VTextField } from 'vuetify/components/VTextField'

defineOptions({
  name: 'AppTextField',
  inheritAttrs: false,
})

const slots = defineSlots<InstanceType<typeof VTextField>['$slots']>()

const elementId = computed(() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id || attrs.label

  return _elementIdToken ? `app-text-field-${_elementIdToken}-${Math.random().toString(36).slice(2, 7)}` : undefined
})

const label = computed(() => useAttrs().label as string | undefined)
const slotNames = computed(() => Object.keys(slots) as Array<keyof InstanceType<typeof VTextField>['$slots']>)
</script>

<template>
  <div
    class="app-text-field flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2 text-wrap"
      style="line-height: 15px;"
      :text="label"
    />
    <VTextField
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
    </VTextField>
  </div>
</template>
