<script setup lang="ts">
import { useDisplay } from 'vuetify'
import '../styles/auth-module.scss'

const props = defineProps<{ modelValue: boolean; title: string; saveLabel?: string; readonly?: boolean; busy?: boolean; errors?: Record<string, string> }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; save: [] }>()
const { smAndDown } = useDisplay()
const { t } = useI18n()
const form = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const body = ref<HTMLElement | null>(null)
async function validate() {
  const result = await form.value?.validate()
  if (!result?.valid) {
    await nextTick()
    body.value?.querySelector<HTMLElement>('.v-input--error input, .v-input--error textarea')?.focus()
  }

  return result?.valid ?? false
}
defineExpose({ validate })
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="smAndDown"
    max-width="900"
    scrollable
    :persistent="busy"
    :aria-label="title"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="auth-record-dialog">
      <VCardTitle class="auth-record-heading">
        <VIcon
          icon="mdi-shield-account-outline"
          color="primary"
        /><h2>{{ title }}</h2><VBtn
          icon="mdi-close"
          variant="text"
          :aria-label="t('action.close')"
          :disabled="busy"
          @click="emit('update:modelValue', false)"
        />
      </VCardTitle>
      <VDivider />
      <VCardText class="auth-record-body">
        <div ref="body">
          <VAlert
            v-if="errors && Object.keys(errors).length"
            type="error"
            variant="tonal"
            role="alert"
            class="mb-4"
          >
            {{ errors.general || t('Auth.design.formErrors') }}
          </VAlert>
          <VForm
            ref="form"
            :readonly="props.readonly"
            :disabled="busy"
            @submit.prevent="!props.readonly && !busy && emit('save')"
          >
            <slot />
          </VForm>
        </div>
      </VCardText>
      <VDivider />
      <VCardActions class="auth-record-actions">
        <VSpacer />
        <VBtn
          variant="tonal"
          :disabled="busy"
          @click="emit('update:modelValue', false)"
        >
          {{ t(props.readonly ? 'action.close' : 'action.cancel') }}
        </VBtn>
        <VBtn
          v-if="!props.readonly"
          color="primary"
          variant="flat"
          :loading="busy"
          prepend-icon="mdi-check"
          @click="emit('save')"
        >
          {{ props.saveLabel || t('action.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
