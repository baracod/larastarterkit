<script setup lang="ts">
import { useDialog } from '@/composables/useDialog'

const { isOpen, title, message, handleConfirm, handleCancel, persistent, color, type } = useDialog()
const { t } = useI18n()
</script>

<template>
  <VDialog
    v-model="isOpen"
    :persistent="persistent ?? false"
    max-width="400px"
  >
    <VCard>
      <VToolbar
        :color="color ?? 'primary'"
        flat
        class="px-2"
      >
        <VIcon>mdi-information-slab-circle-outline</VIcon>
        <h3 class="ms-2">
          {{ title || t('message.confirmation.title') }}
        </h3>
      </VToolbar>

      <VCardText>
        {{ message || t('message.confirmation.message') }}
      </VCardText>

      <VCardActions class="justify-end">
        <template v-if="type === 'confirm'">
          <VBtn
            variant="outlined"
            :color="color ?? 'primary'"
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
        </template>
        <VBtn
          v-else
          variant="outlined"
          :color="color ?? 'primary'"
          @click="handleConfirm"
        >
          D'accord
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
