<script setup lang="ts">
const { isOpen, title, message, handleConfirm, handleCancel, persistent, color } = useDialog
const { t } = useI18n()
</script>

<template>
  <VDialog
    v-model="isOpen"
    :persistent="persistent ?? false"
    max-width="400px"
  >
    <VCard>
      <!-- ✅ Ajout de la couleur du titre -->
      <VCardTitle :class="`text-${color ?? 'primary'}`">
        {{ title || t('message.confirmation.title') }}
      </VCardTitle>

      <VCardText>
        {{ message || t('message.confirmation.message') }}
      </VCardText>

      <VCardActions class="justify-end">
        <VBtn
          variant="outlined"
          color="primary"
          @click="handleConfirm"
        >
          Confirmer
        </VBtn>
        <VBtn
          variant="outlined"
          color="secondary"
          @click="handleCancel"
        >
          Annuler
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
