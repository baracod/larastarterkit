<script lang="ts" setup>

// Déclaration des props et de l'emit pour v-model
const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

// Synchronisation avec v-model externe
const isDialogVisible = ref(props.modelValue)

watch(
  () => props.modelValue,
  val => (isDialogVisible.value = val),
)

watch(isDialogVisible, val => emit('update:modelValue', val))
</script>

<template>
  <VDialog
    v-model="isDialogVisible"
    persistent
    class="v-dialog-sm"
  >
    <!-- Contenu du dialogue -->
    <VCard class="text-center py-4">
      <VCardText class="d-flex direction-column align-center px-8">
        <VIcon
          icon="mdi-alert-circle"
          color="error"
          size="120"
          class=""
        />
        <p>
          <VCardTitle>
            Information de sécurité
          </VCardTitle>
          Votre compte a été temporairement suspendu pour des raisons de sécurité.
          Veuillez contacter l'administrateur du système pour obtenir de l'aide.
        </p>
      </VCardText>

      <VCardActions class="d-flex justify-end ">
        <VBtn
          color="error"
          variant="flat"
          @click="isDialogVisible = false"
        >
          D'accord
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
