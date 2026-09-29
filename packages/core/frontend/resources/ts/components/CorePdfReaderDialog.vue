<script setup lang="ts">
import { useDisplay } from 'vuetify'

const props = defineProps<{
  modelValue: boolean
  pdfBlob?: Blob | null
  title?: string
  fileName?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const { smAndDown } = useDisplay()
const pdfUrl = ref('')

const isVisible = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const dialogTitle = computed(() => props.title || t('documentWorkflow.preview'))
const isImage = computed(() => ['image/png', 'image/jpeg'].includes(props.pdfBlob?.type ?? ''))
const isPdf = computed(() => props.pdfBlob?.type === 'application/pdf')

function cleanup() {
  if (pdfUrl.value)
    URL.revokeObjectURL(pdfUrl.value)
  pdfUrl.value = ''
}

watch(() => [props.modelValue, props.pdfBlob], () => {
  cleanup()
  if (props.modelValue && props.pdfBlob)
    pdfUrl.value = URL.createObjectURL(props.pdfBlob)
}, { immediate: true })
onBeforeUnmount(cleanup)

function downloadFile() {
  if (!pdfUrl.value)
    return
  const link = document.createElement('a')

  link.href = pdfUrl.value
  link.download = props.fileName || 'document.pdf'
  link.click()
}
</script>

<template>
  <VDialog
    v-model="isVisible"
    :fullscreen="smAndDown"
    max-width="1200"
    height="90vh"
    scrollable
    :aria-label="dialogTitle"
  >
    <VCard class="h-100 d-flex flex-column">
      <VCardTitle class="d-flex justify-space-between align-center ga-2 text-wrap">
        <span class="document-title">{{ dialogTitle }}</span>
        <div class="d-flex ga-2">
          <VBtn
            v-if="pdfUrl"
            icon="mdi-download"
            variant="text"
            :aria-label="t('procedureDocs.download')"
            :title="t('procedureDocs.download')"
            @click="downloadFile"
          />
          <VBtn
            icon="mdi-close"
            variant="text"
            :aria-label="t('action.close')"
            @click="isVisible = false"
          />
        </div>
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-0 flex-grow-1 document-content">
        <img
          v-if="pdfUrl && isImage"
          :src="pdfUrl"
          :alt="dialogTitle"
          class="document-image"
        >
        <iframe
          v-else-if="pdfUrl && isPdf"
          :src="pdfUrl"
          :title="dialogTitle"
          class="document-frame"
        />
        <VAlert
          v-else
          type="info"
          class="ma-4"
        >
          {{ t(pdfUrl ? 'documentWorkflow.previewUnsupported' : 'procedureDocs.empty') }}
        </VAlert>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.document-title { min-inline-size: 0; overflow-wrap: anywhere; }
.document-content { min-block-size: 0; }
.document-frame { inline-size: 100%; block-size: 100%; min-block-size: 60vh; border: 0; }
.document-image { display: block; max-inline-size: 100%; max-block-size: 100%; object-fit: contain; margin-inline: auto; }
</style>
