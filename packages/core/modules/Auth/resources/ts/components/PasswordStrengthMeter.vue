<script setup lang="ts">
const props = defineProps<{
  password: string
  confirmPassword?: string
}>()

const emit = defineEmits<{
  (e: 'update:strength', score: number): void
}>()

const { t } = useI18n({ useScope: 'global' })

const liveChecks = computed(() => ({
  minLength: props.password.length >= 8,
  mixedCase: /\p{Ll}/u.test(props.password) && /\p{Lu}/u.test(props.password),
  hasNumber: /\p{N}/u.test(props.password),
  hasSymbol: /[\p{Z}\p{S}\p{P}]/u.test(props.password),
  confirmation: props.confirmPassword !== undefined
    ? !!props.confirmPassword && props.password === props.confirmPassword
    : null,
}))

const strengthScore = computed(() => {
  const { minLength, mixedCase, hasNumber, hasSymbol } = liveChecks.value

  return [minLength, mixedCase, hasNumber, hasSymbol].filter(Boolean).length
})

const strengthColor = computed(() => {
  if (!props.password)
    return 'secondary'
  if (strengthScore.value <= 1)
    return 'error'
  if (strengthScore.value === 2)
    return 'warning'
  if (strengthScore.value === 3)
    return 'info'

  return 'success'
})

const strengthPercent = computed(() => (strengthScore.value / 4) * 100)

watch(strengthScore, val => emit('update:strength', val), { immediate: true })
</script>

<template>
  <VList
    density="compact"
    class="rounded border"
  >
    <VListItem>
      <VListItemTitle class="text-subtitle-2">
        {{ t('Auth.security.validation.title') }}
      </VListItemTitle>
    </VListItem>

    <VListItem v-if="password">
      <VProgressLinear
        :model-value="strengthPercent"
        :color="strengthColor"
        rounded
        height="6"
        class="mb-1"
      />
    </VListItem>

    <VListItem>
      <VIcon
        :icon="liveChecks.minLength ? 'bx-check-circle' : 'bx-x-circle'"
        :color="liveChecks.minLength ? 'success' : 'error'"
        class="me-2"
      />
      {{ t('Auth.security.validation.minLength') }}
    </VListItem>
    <VListItem>
      <VIcon
        :icon="liveChecks.mixedCase ? 'bx-check-circle' : 'bx-x-circle'"
        :color="liveChecks.mixedCase ? 'success' : 'error'"
        class="me-2"
      />
      {{ t('Auth.security.validation.mixedCase') }}
    </VListItem>
    <VListItem>
      <VIcon
        :icon="liveChecks.hasNumber ? 'bx-check-circle' : 'bx-x-circle'"
        :color="liveChecks.hasNumber ? 'success' : 'error'"
        class="me-2"
      />
      {{ t('Auth.security.validation.number') }}
    </VListItem>
    <VListItem>
      <VIcon
        :icon="liveChecks.hasSymbol ? 'bx-check-circle' : 'bx-x-circle'"
        :color="liveChecks.hasSymbol ? 'success' : 'error'"
        class="me-2"
      />
      {{ t('Auth.security.validation.symbol') }}
    </VListItem>
    <VListItem v-if="confirmPassword !== undefined">
      <VIcon
        :icon="liveChecks.confirmation ? 'bx-check-circle' : 'bx-x-circle'"
        :color="liveChecks.confirmation ? 'success' : 'error'"
        class="me-2"
      />
      {{ t('Auth.security.validation.confirmationMatch') }}
    </VListItem>
  </VList>
</template>
