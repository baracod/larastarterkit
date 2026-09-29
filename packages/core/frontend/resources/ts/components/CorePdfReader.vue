<script setup lang="ts">
const props = defineProps<{ streamPdf?: Blob }>()
const isPdfDialogOpen = defineModel<boolean>('isPdfDialogOpen', { default: false })
</script>

<template>
  <CorePdfReaderDialog
    v-model="isPdfDialogOpen"
    :pdf-blob="props.streamPdf"
  />
</template>
