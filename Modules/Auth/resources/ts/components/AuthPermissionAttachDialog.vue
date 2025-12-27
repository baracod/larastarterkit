<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { PermissionAPI } from '@auth/api/Permission'
import { RoleAPI } from '@auth/api/Role'
import type { IPermission } from '@auth/types/entities'

// --- Props & Emits ---
interface Props {
  modelValue: boolean
  roles: number[]
}
const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved'): void
}>()

// --- State ---
const loading = ref(false)
const searchQuery = ref('')
const allPermissions = ref<IPermission[]>([])
const selectedPermissions = ref<number[]>([])
const attached = ref<IPermission[]>([])

const dialog = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

// --- Computed ---
const filteredPermissions = computed(() => {
  const noAttachedPermissions = allPermissions.value.filter(p => attached.value.includes(attached.value.find(ap => ap.id === p.id)!) === false)

  if (!searchQuery.value)
    return noAttachedPermissions

  const lowerCaseQuery = searchQuery.value.toLowerCase()

  return noAttachedPermissions.filter(
    p =>
      p.key.toLowerCase().includes(lowerCaseQuery)
      || p.action?.toLowerCase()?.includes(lowerCaseQuery)
      || p.subject?.toLowerCase()?.includes(lowerCaseQuery),
  )
})

// --- Data Fetching ---
const fetchAllPermissions = async () => {
  allPermissions.value = await PermissionAPI.getAll()
}

const fetchAttachedPermissions = async () => {
  if (props.roles.length === 0) {
    selectedPermissions.value = []

    return
  }

  if (props.roles.length === 1)
    attached.value = await RoleAPI.getPermissions(props.roles[0])
  else
    attached.value = await RoleAPI.getCommonPermissions(props.roles)
}

const loadData = async () => {
  loading.value = true
  try {
    // Exécute les deux appels en parallèle pour plus de performance
    await Promise.all([fetchAllPermissions(), fetchAttachedPermissions()])
  }
  catch (error) {
    console.error('Failed to load permissions data:', error)

    // Ici, on pourrait afficher une notification d'erreur à l'utilisateur
  }
  finally {
    loading.value = false
  }
}

// --- Lifecycle & State Management ---
const resetState = () => {
  searchQuery.value = ''
  selectedPermissions.value = []
}

watch(dialog, isOpen => {
  if (isOpen) {
    loadData()
  }
  else {
    // Nettoie l'état quand le dialogue se ferme pour éviter les "flashs" de données
    resetState()
  }
})

// --- Actions ---
const onSave = async () => {
  if (props.roles.length === 0)
    return

  loading.value = true
  try {
    await RoleAPI.attachPermissions(props.roles, [...selectedPermissions.value, ...attached.value.map(p => p.id)])
    emit('saved')
    dialog.value = false
  }
  catch (error) {
    console.error('Failed to attach permissions:', error)
  }
  finally {
    loading.value = false
  }
}

const onCancel = () => {
  dialog.value = false
}

// --- Constants ---
const headers = [
  { title: 'Key', key: 'key', sortable: true },
  { title: 'Action', key: 'action', sortable: true },
  { title: 'Subject', key: 'subject', sortable: true },
]
</script>

<template>
  <VDialog
    v-model="dialog"
    max-width="800px"
    persistent
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center pa-5">
        <span class="text-h5">Gérer les permissions</span>
        <VSpacer />
        <VBtn
          icon="mdi-close"
          variant="text"
          density="compact"
          @click="onCancel"
        />
      </VCardTitle>

      <VCardText class="px-5 pb-5">
        <!-- Champ de recherche -->
        <VTextField
          v-model="searchQuery"
          label="Rechercher une permission..."
          variant="outlined"
          prepend-inner-icon="mdi-magnify"
          density="compact"
          clearable
          class="mb-4"
        />

        <!-- Datatable -->
        <VDataTable
          v-model="selectedPermissions"
          :loading="loading"
          :headers="headers"
          :items="filteredPermissions"
          :search="searchQuery"
          item-value="id"
          show-select
          density="compact"
          fixed-header
          height="400px"
          class="border rounded"
        />
      </VCardText>

      <VCardActions class="pa-5 pt-0">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="onCancel"
        >
          Annuler
        </VBtn>
        <VBtn
          :loading="loading"
          variant="elevated"
          color="primary"
          @click="onSave"
        >
          Enregistrer
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
