<script lang="ts" setup>
defineOptions({
  name: 'CoreDisplayText',
  inheritAttrs: false, // On veut contrôler ce qui est passé
})

const elementId = computed(() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id || attrs.label

  return _elementIdToken ? `core-display-text-${_elementIdToken}-${Math.random().toString(36).slice(2, 7)}` : undefined
})

const label = computed(() => useAttrs().label as string | undefined)
const value = computed(() => useAttrs().value as string | undefined)
const inline = computed(() => useAttrs().inline as boolean | undefined)
</script>

<template>
  <CoreCol v-bind="$attrs">
    <div
      class="app-text-field flex-grow-1"
      :class="$attrs.class"
    >
      <VLabel
        v-if="label"
        :for="elementId"
        class="me-1 text-wrap"
        style="line-height: 15px;"
        :text="`${label} : `"
      />
      <br v-if="inline">
      <VLabel
        v-if="label"
        class="ms-1 font-weight-bold text-wrap text-bold"
        style="line-height: 15px;"
        :text="value"
      />
    </div>
  </CoreCol>
</template>
