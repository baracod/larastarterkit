<script setup lang="ts">
defineOptions({ inheritAttrs: false })
defineProps<{ modelValue: string; label: string; autocomplete?: string }>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const { t } = useI18n()
const visible = ref(false)
</script>

<template>
  <AppTextField
    v-bind="$attrs"
    :model-value="modelValue"
    :label="label"
    :type="visible ? 'text' : 'password'"
    :autocomplete="autocomplete ?? 'new-password'"
    prepend-inner-icon="mdi-lock-outline"
    @update:model-value="emit('update:modelValue', $event ?? '')"
  >
    <template #append-inner>
      <VBtn
        type="button"
        variant="text"
        size="small"
        :icon="visible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        :aria-label="t(visible ? 'Auth.access.hidePassword' : 'Auth.access.showPassword', { field: label })"
        :aria-pressed="visible"
        @click="visible = !visible"
      />
    </template>
  </AppTextField>
</template>
