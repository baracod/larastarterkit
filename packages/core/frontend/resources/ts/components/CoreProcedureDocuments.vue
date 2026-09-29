<script setup lang="ts">
import dayjs from 'dayjs'
import { useDisplay } from 'vuetify'
import CoreDocumentGeneration from './CoreDocumentGeneration.vue'
import CorePdfReaderDialog from '@/components/CorePdfReaderDialog.vue'
import { $api } from '@/utils/api'
import type { GenerationContext, GenerationField } from '@/types/documentGeneration'

const props = defineProps<{
  operationType: string
  recordId: number | null
  preselectKey?: string | null
}>()

const emit = defineEmits<{ close: []; saved: [] }>()
const { t } = useI18n()
const { smAndDown } = useDisplay()
interface Requirement { can_upload: boolean; mode: string; fields: GenerationField[]; key: string; title: string; can_generate: boolean; issuer: string | null; authorization_reference: string | null }
interface Document { version: number; id: number; document_key: string; reference: string; issuer: string; issued_at: string; source: string; original_name: string }
const requirements = ref<Requirement[]>([])
const documents = ref<Document[]>([])
const context = ref<GenerationContext | null>(null)
const method = ref('upload')
const previewBlob = ref<Blob | null>(null)
const previewName = ref('')
const previewOpen = ref(false)
const previewLoading = ref(false)

const canEdit = ref(false)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const key = ref<string | null>(null)
const reference = ref('')
const issuer = ref('')
const issuedAt = ref(dayjs().format('YYYY-MM-DD'))
const files = ref<File | File[] | null>(null)
const documentTypes = computed(() => requirements.value.map(item => ({ ...item, title: item.title })))
const selected = computed(() => requirements.value.find(item => item.key === key.value))

const methods = computed(() => [
  ...(selected.value?.can_upload ? [{ value: 'upload', title: t('procedureDocs.upload') }] : []),
  ...(selected.value?.can_generate ? [{ value: 'generate', title: t('documentWorkflow.modes.generate') }] : []),
])

watch(methods, options => {
  if (!options.some(item => item.value === method.value))
    method.value = options[0]?.value ?? ''
})

const file = computed(() => Array.isArray(files.value) ? files.value[0] : files.value)
const canUpload = computed(() => !!selected.value?.can_upload && !!key.value && !!reference.value.trim() && !!issuer.value.trim() && !!issuedAt.value && !!file.value)
const endpoint = computed(() => `/documents/${props.operationType}/${props.recordId}`)
let requestVersion = 0

function message(cause: any) {
  return Object.values(cause?.data?.errors ?? {}).flat().join(' ') || cause?.data?.message || t('procedureDocs.error')
}

/**
 * Conserve le document sélectionné s'il existe encore, sinon applique le document
 * demandé par la page appelante (ex. « Importer la pièce » sur une annexe précise).
 */
function resolveKey(items: Requirement[]): string | null {
  const wanted = props.preselectKey ?? key.value

  return items.some(item => item.key === wanted) ? wanted! : items[0]?.key ?? null
}

async function load() {
  const version = ++requestVersion
  if (!props.recordId)
    return
  loading.value = true
  error.value = ''
  try {
    const result = await $api<{ requirements: Requirement[]; documents: Document[]; can_edit: boolean; context: GenerationContext }>(endpoint.value)
    if (version !== requestVersion)
      return
    requirements.value = result.requirements
    documents.value = result.documents
    canEdit.value = result.can_edit
    context.value = result.context
    key.value = resolveKey(result.requirements)
  }
  catch (cause) {
    if (version === requestVersion)
      error.value = message(cause)
  }
  finally {
    if (version === requestVersion)
      loading.value = false
  }
}

watch(() => [props.operationType, props.recordId], () => {
  requestVersion++
  previewOpen.value = false
  previewBlob.value = null
  requirements.value = []
  documents.value = []
  canEdit.value = false
  key.value = null
  reference.value = ''
  issuer.value = ''
  issuedAt.value = dayjs().format('YYYY-MM-DD')
  files.value = null
  success.value = ''
  load()
}, { immediate: true })

async function save(generate: boolean) {
  if (saving.value || !canEdit.value || !key.value || (generate ? !selected.value?.can_generate : !canUpload.value))
    return
  saving.value = true
  error.value = ''
  success.value = ''

  const body = new FormData()

  body.append('document_key', key.value)
  body.append('supersedes_id', String(documents.value.find(doc => doc.document_key === key.value)?.id ?? ''))
  body.append('issued_at', generate ? dayjs().format('YYYY-MM-DD') : issuedAt.value)
  if (!generate) {
    body.append('reference', reference.value)
    body.append('issuer', issuer.value)
    body.append('file', file.value!)
  }
  try {
    await $api(`${endpoint.value}${generate ? '/generate' : ''}`, { method: 'POST', body })
    files.value = null
    reference.value = ''
    await load()
    success.value = t('procedureDocs.saved')
    emit('saved')
  }
  catch (cause) {
    error.value = message(cause)
  }
  finally {
    saving.value = false
  }
}

async function generatedSaved() {
  emit('saved')
  await load()
  success.value = t('procedureDocs.saved')
}

async function preview(document: Document) {
  const version = requestVersion

  previewLoading.value = true
  error.value = ''
  try {
    const blob = await $api<Blob, 'blob'>(`${endpoint.value}/${document.id}`, { responseType: 'blob' })
    if (version !== requestVersion)
      return
    previewBlob.value = blob
    previewName.value = document.original_name
    previewOpen.value = true
  }
  catch (cause) {
    if (version === requestVersion)
      error.value = message(cause)
  }
  finally { previewLoading.value = false }
}

async function download(document: Document) {
  error.value = ''
  try {
    const blob = await $api<Blob, 'blob'>(`${endpoint.value}/${document.id}`, { responseType: 'blob' })
    const url = URL.createObjectURL(blob)
    const link = window.document.createElement('a')

    link.href = url
    link.download = document.original_name
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }
  catch (cause) {
    error.value = message(cause)
  }
}
</script>

<template>
  <VDialog
    :model-value="recordId !== null"
    :fullscreen="smAndDown"
    max-width="960"
    scrollable
    :persistent="saving"
    :aria-label="t('procedureDocs.title')"
    @update:model-value="!$event && emit('close')"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between ga-3 text-wrap">
        {{ t('procedureDocs.title') }}
        <VBtn
          icon="mdi-close"
          variant="text"
          :aria-label="t('action.close')"
          :disabled="saving"
          @click="emit('close')"
        />
      </VCardTitle>
      <VDivider />
      <VCardText>
        <VProgressLinear
          v-if="loading"
          indeterminate
          :aria-label="t('procedureDocs.loading')"
        />
        <VAlert
          v-if="error"
          type="error"
          class="mb-4"
          role="alert"
        >
          {{ error }}
        </VAlert>
        <VAlert
          v-if="success"
          type="success"
          class="mb-4"
          role="status"
        >
          {{ success }}
        </VAlert>
        <p class="text-body-2 mb-5">
          {{ t('procedureDocs.help') }}
        </p>
        <template v-if="!loading">
          <section
            v-if="canEdit"
            :aria-label="t('procedureDocs.add')"
          >
            <h3 class="text-h6 mb-4">
              {{ t('procedureDocs.add') }}
            </h3>
            <VSelect
              v-model="key"
              :items="documentTypes"
              item-title="title"
              item-value="key"
              :label="t('procedureDocs.kind')"
              :aria-label="t('procedureDocs.kind')"
              variant="outlined"
              hide-details="auto"
              class="mb-4"
              :disabled="saving"
            />
            <VSelect
              v-if="methods.length > 1"
              v-model="method"
              :items="methods"
              :label="t('documentWorkflow.action')"
              :disabled="saving"
              class="mb-4"
            />
            <CoreDocumentGeneration
              v-if="selected?.can_generate && method === 'generate'"
              :key="`${operationType}-${recordId}-${key}`"
              :endpoint="endpoint"
              :document-key="key!"
              :fields="selected.fields"
              :context="context"
              :supersedes-id="documents.find(doc => doc.document_key === key)?.id ?? null"
              @busy="saving = $event"
              @saved="generatedSaved"
            />
            <VForm
              v-if="selected?.can_upload && method === 'upload'"
              @submit.prevent="save(false)"
            >
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="reference"
                    :label="t('procedureDocs.reference')"
                    maxlength="255"
                    variant="outlined"
                    hide-details="auto"
                    :disabled="saving"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="issuer"
                    :label="t('procedureDocs.issuer')"
                    maxlength="255"
                    variant="outlined"
                    hide-details="auto"
                    :disabled="saving"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VTextField
                    v-model="issuedAt"
                    type="date"
                    :max="dayjs().format('YYYY-MM-DD')"
                    :label="t('procedureDocs.date')"
                    variant="outlined"
                    hide-details="auto"
                    :disabled="saving"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <VFileInput
                    v-model="files"
                    accept="application/pdf,image/jpeg,image/png"
                    :label="t('procedureDocs.file')"
                    :hint="t('procedureDocs.fileHint')"
                    persistent-hint
                    variant="outlined"
                    :disabled="saving"
                    show-size
                  />
                </VCol>
              </VRow>
              <VBtn
                type="submit"
                color="primary"
                prepend-icon="mdi-upload"
                :block="smAndDown"
                :disabled="!canUpload || saving"
                :loading="saving"
                class="mt-4"
              >
                {{ t('procedureDocs.upload') }}
              </VBtn>
            </VForm>
          </section>
          <VDivider class="my-6" />
          <h3 class="text-h6 mb-4">
            {{ t('procedureDocs.history') }} ({{ documents.length }})
          </h3>
          <p
            v-if="!documents.length"
            class="text-medium-emphasis"
          >
            {{ t('procedureDocs.empty') }}
          </p>
          <VCard
            v-for="document in documents"
            :key="document.id"
            variant="outlined"
            class="mb-3"
          >
            <VCardText class="d-flex flex-wrap align-center ga-3">
              <div class="document-description flex-grow-1">
                <div class="font-weight-bold">
                  {{ requirements.find(item => item.key === document.document_key)?.title ?? document.document_key }}
                </div>
                <p class="my-1">
                  {{ document.reference }} · {{ document.issuer }} · {{ dayjs(document.issued_at).format('DD/MM/YYYY') }}
                </p>
                <VChip
                  size="small"
                  :color="document.source === 'generated' ? 'primary' : 'secondary'"
                >
                  {{ t(`procedureDocs.${document.source}`) }}
                </VChip>
              </div>
              <VBtn
                variant="tonal"
                prepend-icon="mdi-eye-outline"
                :disabled="previewLoading"
                :aria-label="`${t('documentWorkflow.preview')} ${document.reference}`"
                @click="preview(document)"
              >
                {{ t('documentWorkflow.preview') }}
              </VBtn>
              <VBtn
                variant="tonal"
                prepend-icon="mdi-download"
                :aria-label="`${t('procedureDocs.download')} ${document.reference}`"
                @click="download(document)"
              >
                {{ t('procedureDocs.download') }}
              </VBtn>
            </VCardText>
          </VCard>
        </template>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4">
        <VSpacer /><VBtn
          :disabled="saving"
          @click="emit('close')"
        >
          {{ t('action.close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <CorePdfReaderDialog
    v-model="previewOpen"
    :pdf-blob="previewBlob"
    :file-name="previewName"
    :title="previewName"
  />
</template>

<style scoped>
.document-description { min-inline-size: 0; overflow-wrap: anywhere; }
.generate-button { block-size: auto !important; min-block-size: 44px; padding-block: 10px; }
.generate-button :deep(.v-btn__content) { white-space: normal; }
</style>
