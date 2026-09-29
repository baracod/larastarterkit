<script lang="ts" setup>
import type { VAutocomplete } from 'vuetify/components/VAutocomplete'

defineOptions({
  name: 'AppAutocomplete',
  inheritAttrs: false,
})

const slots = defineSlots<InstanceType<typeof VAutocomplete>['$slots']>()

// const { class: _class, label, variant: _, ...restAttrs } = useAttrs()

const elementId = computed(() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id || attrs.label

  return _elementIdToken ? `app-autocomplete-${_elementIdToken}-${Math.random().toString(36).slice(2, 7)}` : undefined
})

const label = computed(() => useAttrs().label as string | undefined)
const slotNames = computed(() => Object.keys(slots) as Array<keyof InstanceType<typeof VAutocomplete>['$slots']>)
</script>

<template>
  <div
    class="app-autocomplete flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2"
      style="line-height: 15px;"
      :text="label"
    />
    <VAutocomplete
      v-bind="{
        ...$attrs,
        class: null,
        label: undefined,
        id: elementId,
        variant: 'outlined',
        menuProps: {
          contentClass: [
            'app-inner-list',
            'app-autocomplete__content',
            'v-autocomplete__content',
          ],
        },
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
    </VAutocomplete>
  </div>
</template>
