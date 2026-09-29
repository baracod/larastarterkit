<script lang="ts" setup>
defineOptions({
  name: 'CoreSwitch',
  inheritAttrs: false, // On veut contrôler ce qui est passé
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <CoreCol v-bind="$attrs">
    <VSwitch
      v-bind="$attrs"
      variant="outlined"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </CoreCol>
</template>
