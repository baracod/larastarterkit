<script setup lang="ts">
import { computed } from 'vue'
import type { IPermission } from '@auth/types/entities'

// 2. Définition des props avec un typage strict
const props = defineProps<{
  permission: IPermission
}>()

// 3. Définition des événements que le composant peut émettre
const emit = defineEmits<{
  (e: 'edit', id: number): void
  (e: 'delete', id: number): void
}>()

// Propriété calculée pour afficher un sous-titre clair
const subtitle = computed(() => {
  const parts = []
  if (props.permission.action)
    parts.push(`Action: ${props.permission.action}`)
  if (props.permission.subject)
    parts.push(`Sujet: ${props.permission.subject}`)

  return parts.join(' | ')
})
</script>

<template>
  <VCard class="mb-4">
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="primary"
          variant="tonal"
          rounded
          size="42"
        >
          <VIcon
            icon="mdi-shield-key-outline"
            size="28"
          />
        </VAvatar>
      </template>

      <VCardTitle>{{ props.permission.key }}</VCardTitle>

      <VCardSubtitle v-if="subtitle">
        {{ subtitle }}
      </VCardSubtitle>
    </VCardItem>

    <VDivider />

    <VCardText>
      <p class="mb-4">
        {{ props.permission.description || 'Aucune description fournie.' }}
      </p>

      <div class="d-flex gap-4">
        <VChip
          v-if="props.permission.always_allow"
          color="success"
          size="small"
          prepend-icon="mdi-check-circle"
        >
          Toujours autorisé
        </VChip>
        <VChip
          v-else
          color="warning"
          size="small"
          prepend-icon="mdi-alert-circle-outline"
        >
          Conditionnel
        </VChip>

        <VChip
          v-if="props.permission.is_public"
          color="info"
          size="small"
          prepend-icon="mdi-earth"
        >
          Public
        </VChip>
        <VChip
          v-else
          color="secondary"
          size="small"
          prepend-icon="mdi-lock-outline"
        >
          Privé
        </VChip>
      </div>
    </VCardText>

    <VCardActions class="mt-5">
      <VSpacer />

      <VBtn
        variant="tonal"
        @click="emit('edit', props.permission.id)"
      >
        Modifier
      </VBtn>
      <VBtn
        color="error"
        @click="emit('delete', props.permission.id)"
      >
        Supprimer
      </VBtn>
    </VCardActions>
  </VCard>
</template>

<style scoped>
/* Utilisation des "gap" pour un espacement moderne entre les éléments flex */
.gap-4 {
  gap: 1rem;
}
</style>
