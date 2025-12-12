<script lang="ts" setup>
defineOptions({
  name: 'CoreTextarea',
  inheritAttrs: false, // On veut contrôler ce qui est passé
})

const emit = defineEmits(['update:modelValue'])
const attrs = useAttrs()
</script>

<template>
  <CoreCol v-bind="attrs">
    <AppTextarea
      v-bind="$attrs"
      variant="outlined"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </CoreCol>
</template>
