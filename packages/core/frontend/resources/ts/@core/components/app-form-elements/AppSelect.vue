<script lang="ts" setup>
import type { VSelect } from 'vuetify/components/VSelect'

defineOptions({
  name: 'AppSelect',
  inheritAttrs: false,
})

const slots = defineSlots<InstanceType<typeof VSelect>['$slots']>()

const elementId = computed(() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id || attrs.label

  return _elementIdToken ? `app-select-${_elementIdToken}-${Math.random().toString(36).slice(2, 7)}` : undefined
})

const label = computed(() => useAttrs().label as string | undefined)
const slotNames = computed(() => Object.keys(slots) as Array<keyof InstanceType<typeof VSelect>['$slots']>)
</script>

<template>
  <div
    class="app-select flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2"
      style="line-height: 15px;"
      :text="label"
    />
    <VSelect
      v-bind="{
        ...$attrs,
        class: null,
        label: undefined,
        variant: 'outlined',
        id: elementId,
        menuProps: { contentClass: ['app-inner-list', 'app-select__content', 'v-select__content', $attrs.multiple !== undefined ? 'v-list-select-multiple' : ''] },
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
    </VSelect>
  </div>
</template>
