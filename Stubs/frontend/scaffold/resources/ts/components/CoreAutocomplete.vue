<script lang="ts" setup>
defineOptions({
  name: 'CoreAutocomplete',
  inheritAttrs: false, // On veut contrôler ce qui est passé
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <CoreCol v-bind="$attrs">
    <AppAutocomplete
      v-bind="$attrs"
      variant="outlined"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </CoreCol>
</template>
