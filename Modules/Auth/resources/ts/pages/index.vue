<script lang="ts" setup>
const { notify } = useNotify()

definePage({
  meta: {
    action: 'access',
    subject: 'auth',
  },
})

function handleSuccess() {
  notify({
    type: 'success',
    title: 'Succès',
    message: 'L\'opération a été effectuée avec succès.',
  })
}

function handleError() {
  notify({
    type: 'error',
    message: 'Le champ "email" est invalide.',
  })
}

function showInfo() {
  notify({
    // Le type 'info' est le défaut si non spécifié
    message: 'Mise à jour système prévue ce soir à 23h.',
  })
}

// Dialogues de confirmation

// Exemple pour une action destructive
const { confirmDialog } = useDialog()
async function deleteItem() {
  const userConfirmed = await confirmDialog({
    title: 'Confirmer la suppression',
    message: 'Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est irréversible.',
    color: 'error',
  })

  if (userConfirmed) {
    // L'utilisateur a cliqué sur "Oui, supprimer"
    console.log('API call to delete item...')
    notify({ type: 'success', message: 'Élément supprimé avec succès.' })
  }
  else {
    // L'utilisateur a cliqué sur "Annuler" (ou Echap, etc.)
    console.log('Deletion cancelled by user.')
    notify({ type: 'info', message: 'Suppression annulée.' })
  }
}

// Exemple pour une simple information
async function showInfoDialog() {
  await confirmDialog({
    title: 'Information',
    message: 'Cette fonctionnalité sera bientôt disponible.',
    confirmColor: 'primary',
    icon: 'mdi-information-outline',
  })

  // Le code ici ne s'exécute qu'après que l'utilisateur a cliqué sur "OK"
  console.log('User has acknowledged the info dialog.')
}
</script>

<template>
  <VCard>
    <VCardText>
      bienvenu dans le module  auth
    </VCardText>
  </VCard>
  <VCard class="mt-2">
    <VCardTitle>Notifications</VCardTitle>
    <VCardText class="d-flex gap-4">
      <VBtn
        color="success"
        @click="handleSuccess"
      >
        Succès
      </VBtn>
      <VBtn
        color="error"
        @click="handleError"
      >
        Erreur (sans titre)
      </VBtn>
      <VBtn
        color="info"
        @click="showInfo"
      >
        Info
      </VBtn>
    </VCardText>
  </VCard>

  <VCard class="mt-2">
    <VCardTitle>Dialogues de Confirmation</VCardTitle>
    <VCardText class="d-flex gap-4">
      <VBtn
        color="error"
        @click="deleteItem"
      >
        Supprimer un élément
      </VBtn>
      <VBtn
        color="info"
        @click="showInfoDialog"
      >
        Afficher une info
      </VBtn>
    </VCardText>
  </VCard>
</template>
