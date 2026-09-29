<script lang="ts" setup>
import type { VFileInput } from 'vuetify/components/VFileInput'

defineOptions({
  name: 'AppFileInput',
  inheritAttrs: false,
})

const slots = defineSlots<InstanceType<typeof VFileInput>['$slots']>()

const elementId = computed(() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id || attrs.label

  return _elementIdToken ? `app-file-input-${_elementIdToken}-${Math.random().toString(36).slice(2, 7)}` : undefined
})

const label = computed(() => useAttrs().label as string | undefined)
const slotNames = computed(() => Object.keys(slots) as Array<keyof InstanceType<typeof VFileInput>['$slots']>)
</script>

<template>
  <div
    class="app-file-input flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2 text-wrap"
      style="line-height: 15px;"
      :text="label"
    />
    <VFileInput
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
    </VFileInput>
  </div>
</template>
