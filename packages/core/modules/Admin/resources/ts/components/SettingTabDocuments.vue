<script setup lang="ts">
import { $api } from '@/utils/api'

interface DocumentRule { key: string; title: string; override: string | null; mode: string; default_mode: string }
interface Step { key: string; override: string | null; mode: string; default_mode: string; documents: DocumentRule[] }
const { t } = useI18n()
const steps = ref<Step[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref(false)
const modes = computed(() => ['upload', 'generate', 'both'].map(value => ({ value, title: t(`documentWorkflow.modes.${value}`) })))
const stepModes = computed(() => [{ value: null, title: t('documentWorkflow.inheritConfig') }, ...modes.value])
const documentModes = computed(() => [{ value: null, title: t('documentWorkflow.inherit') }, ...modes.value])
async function load() {
  loading.value = true
  try {
    steps.value = (await $api<{ steps: Step[] }>('documents/settings')).steps
  }
  catch (cause: any) { error.value = cause?.data?.message ?? t('procedureDocs.error') }
  finally { loading.value = false }
}
async function save() {
  saving.value = true
  error.value = ''
  success.value = false
  try {
    const body = { steps: Object.fromEntries(steps.value.map(step => [step.key, { mode: step.override, documents: Object.fromEntries(step.documents.map(doc => [doc.key, doc.override])) }])) }

    steps.value = (await $api<{ steps: Step[] }>('documents/settings', { method: 'PUT', body })).steps
    success.value = true
  }
  catch (cause: any) { error.value = Object.values(cause?.data?.errors ?? {}).flat().join(' ') || cause?.data?.message || t('procedureDocs.error') }
  finally { saving.value = false }
}
onMounted(load)
</script>

<template>
  <div class="pa-5">
    <p class="mb-5">
      {{ t('documentWorkflow.settingsHelp') }}
    </p>
    <VAlert
      v-if="error"
      type="error"
      class="mb-4"
    >
      {{ error }}
    </VAlert>
    <VAlert
      v-if="success"
      type="success"
      class="mb-4"
    >
      {{ t('documentWorkflow.settingsSaved') }}
    </VAlert>
    <VAlert
      v-if="!loading && !steps.length && !error"
      type="info"
    >
      {{ t('starter.noDocumentContexts') }}
    </VAlert>
    <VProgressLinear
      v-if="loading"
      indeterminate
    />
    <VCard
      v-for="step in steps"
      :key="step.key"
      variant="outlined"
      class="mb-4"
    >
      <VCardTitle class="text-wrap">
        {{ step.key }}
      </VCardTitle>
      <VCardText>
        <VSelect
          v-model="step.override"
          :items="stepModes"
          :label="t('documentWorkflow.stepMode')"
          :hint="`${t('documentWorkflow.defaultMode')} : ${t(`documentWorkflow.modes.${step.default_mode}`)}`"
          persistent-hint
          :disabled="saving"
        />
        <VExpansionPanels class="mt-4">
          <VExpansionPanel :title="t('documentWorkflow.exceptions', { count: step.documents.length })">
            <VExpansionPanelText>
              <div
                v-for="doc in step.documents"
                :key="doc.key"
                class="mb-5"
              >
                <VSelect
                  v-model="doc.override"
                  :items="documentModes"
                  :label="doc.title"
                  :disabled="saving"
                  :hint="`${t('documentWorkflow.effectiveMode')} : ${t(`documentWorkflow.modes.${doc.override ?? step.override ?? doc.default_mode}`)}`"
                  persistent-hint
                />
              </div>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>
      </VCardText>
    </VCard>
    <VBtn
      :disabled="loading || saving || !steps.length"
      :loading="saving"
      prepend-icon="mdi-content-save"
      @click="save"
    >
      {{ t('action.save') }}
    </VBtn>
  </div>
</template>
