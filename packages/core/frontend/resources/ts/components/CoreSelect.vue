<script lang="ts" setup>
defineOptions({
  name: 'CoreSelect',
  inheritAttrs: false, // On veut contrôler ce qui est passé
})

const emit = defineEmits(['update:modelValue'])
const attrs = useAttrs()
const accessibleLabel = computed(() => attrs['aria-label'] || attrs.label || attrs.placeholder || undefined)
</script>

<template>
  <CoreCol v-bind="$attrs">
    <AppSelect
      v-bind="$attrs"
      variant="outlined"
      :aria-label="accessibleLabel"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </CoreCol>
</template>
