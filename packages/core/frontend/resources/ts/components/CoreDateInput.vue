<script lang="ts" setup>
defineOptions({
  name: 'CoreDateInput',
  inheritAttrs: false,
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <CoreCol v-bind="$attrs">
    <AppDateField
      v-bind="$attrs"
      variant="outlined"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </CoreCol>
</template>
