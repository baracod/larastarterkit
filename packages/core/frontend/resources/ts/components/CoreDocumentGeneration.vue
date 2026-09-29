<script setup lang="ts">
import dayjs from 'dayjs'
import { useDisplay } from 'vuetify'
import { themeConfig } from '@themeConfig'
import { $api } from '@/utils/api'

import type { GenerationContext, GenerationField } from '@/types/documentGeneration'

const props = defineProps<{ endpoint: string; documentKey: string; fields: GenerationField[]; context: GenerationContext | null; supersedesId?: number | null; expiresRequired?: boolean }>()
const emit = defineEmits<{ saved: []; busy: [value: boolean] }>()
const { t, te } = useI18n()
const { smAndDown } = useDisplay()
const previewOpen = ref(false)
const values = ref<Record<string, string | number>>({})
const issuer = ref(themeConfig.app.title)
const expiresAt = ref('')
const busy = ref(false)
const error = ref('')
const previewUrl = ref('')
const previewHtml = ref('')
const previewToken = ref('')
const success = ref(false)
const payload = computed(() => ({ document_key: props.documentKey, issued_at: dayjs().format('YYYY-MM-DD'), issuer: issuer.value, fields: values.value, supersedes_id: props.supersedesId ?? null, ...(props.expiresRequired ? { expires_at: expiresAt.value } : {}) }))
const valid = computed(() => issuer.value.trim() && (!props.expiresRequired || expiresAt.value >= dayjs().format('YYYY-MM-DD')) && props.fields.every(field => !field.required || String(values.value[field.key] ?? '').trim()))
function clearPreview() {
  previewOpen.value = false
  if (previewUrl.value)
    URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = ''
  previewHtml.value = ''
  previewToken.value = ''
}
watch(payload, clearPreview, { deep: true })
onBeforeUnmount(clearPreview)
async function act(preview: boolean) {
  if (!valid.value || busy.value || (!preview && !previewUrl.value))
    return
  busy.value = true
  emit('busy', true)
  error.value = ''
  try {
    if (preview) {
      const result = await $api<{ html: string; pdf: string; preview_token: string }>(`${props.endpoint}/preview?format=json`, { method: 'POST', body: payload.value })
      const bytes = Uint8Array.from(atob(result.pdf), character => character.charCodeAt(0))
      const blob = new Blob([bytes], { type: 'application/pdf' })

      clearPreview()
      previewUrl.value = URL.createObjectURL(blob)
      previewHtml.value = result.html
      previewToken.value = result.preview_token
      previewOpen.value = true
    }
    else {
      await $api(`${props.endpoint}/generate`, { method: 'POST', body: { ...payload.value, preview_token: previewToken.value } })
      success.value = true
      emit('saved')
    }
  }
  catch (cause: any) {
    let data = cause?.data
    if (data instanceof Blob) {
      try {
        data = JSON.parse(await data.text())
      }
      catch { data = null }
    }
    if (data?.errors?.preview_token)
      clearPreview()
    error.value = Object.values(data?.errors ?? {}).flat().join(' ') || data?.message || t('procedureDocs.error')
  }
  finally {
    busy.value = false
    emit('busy', false)
  }
}
</script>

<template>
  <div>
    <VAlert
      type="info"
      variant="tonal"
      class="mb-4"
    >
      {{ t('documentWorkflow.generationHelp') }}
    </VAlert>
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
      {{ t('procedureDocs.saved') }}
    </VAlert>
    <VExpansionPanels
      v-if="context"
      class="mb-4"
    >
      <VExpansionPanel :title="`${t('documentWorkflow.recordData')} — ${context.reference}`">
        <VExpansionPanelText>
          <section
            v-for="(section, index) in context.sections"
            :key="index"
            class="mb-4"
          >
            <h4>{{ te(`documentWorkflow.sections.${section.title}`) ? t(`documentWorkflow.sections.${section.title}`) : section.title }}</h4>
            <dl
              v-for="row in section.rows"
              :key="row.key"
              class="d-flex flex-wrap ga-2 my-1"
            >
              <dt>{{ te(`documentWorkflow.data.${row.key}`) ? t(`documentWorkflow.data.${row.key}`) : row.key }} :</dt><dd>{{ row.value }}</dd>
            </dl>
          </section>
        </VExpansionPanelText>
      </VExpansionPanel>
    </VExpansionPanels>
    <VTextField
      v-model="issuer"
      :label="t('procedureDocs.issuer')"
      :disabled="busy"
      maxlength="255"
      class="mb-3"
    />
    <VTextField
      v-if="expiresRequired"
      v-model="expiresAt"
      type="date"
      :min="dayjs().format('YYYY-MM-DD')"
      :label="t('workflow.expiresAt')"
      :disabled="busy"
      class="mb-3"
    />
    <div
      v-for="field in fields"
      :key="field.key"
      class="mb-3"
    >
      <VTextarea
        v-if="field.type === 'textarea'"
        v-model="values[field.key]"
        :label="`${(te(`documentWorkflow.fields.${field.key}`) ? t(`documentWorkflow.fields.${field.key}`) : field.key)}${field.required ? ' *' : ''}`"
        :disabled="busy"
        maxlength="5000"
        rows="3"
        auto-grow
      />
      <VTextField
        v-else
        v-model="values[field.key]"
        :label="`${(te(`documentWorkflow.fields.${field.key}`) ? t(`documentWorkflow.fields.${field.key}`) : field.key)}${field.required ? ' *' : ''}`"
        :type="['number', 'integer'].includes(field.type) ? 'number' : field.type"
        :min="['number', 'integer'].includes(field.type) ? 0 : undefined"
        :step="field.type === 'integer' ? 1 : 'any'"
        :disabled="busy"
        maxlength="5000"
      />
    </div>
    <div class="d-flex flex-wrap ga-3 my-4">
      <VBtn
        prepend-icon="mdi-eye-outline"
        :disabled="!valid || busy"
        :loading="busy"
        @click="act(true)"
      >
        {{ t('documentWorkflow.preview') }}
      </VBtn>
      <VBtn
        prepend-icon="mdi-content-save-outline"
        :disabled="!previewUrl || busy || success"
        :loading="busy"
        @click="act(false)"
      >
        {{ t('procedureDocs.generate') }}
      </VBtn>
      <VBtn
        v-if="previewUrl"
        variant="tonal"
        @click="previewOpen = true"
      >
        {{ t('documentWorkflow.openPreview') }}
      </VBtn>
    </div>
    <VDialog
      v-model="previewOpen"
      :fullscreen="smAndDown"
      max-width="1200"
      scrollable
      :aria-label="t('documentWorkflow.preview')"
    >
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          {{ t('documentWorkflow.preview') }}
          <VBtn
            icon="mdi-close"
            variant="text"
            :aria-label="t('action.close')"
            @click="previewOpen = false"
          />
        </VCardTitle>
        <VCardText>
          <iframe
            v-if="previewUrl && previewOpen"
            :srcdoc="previewHtml"
            sandbox=""
            :title="t('documentWorkflow.preview')"
            class="pdf-preview"
          />
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.pdf-preview { width: 100%; height: 560px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
dd { overflow-wrap: anywhere; }
</style>
