<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { SettingAPI } from '@admin/api/SettingAPI'
import type { ISetting } from '@admin/types/entities'

const { notify } = useNotify()
const loading = ref(false)
const settings = ref<ISetting[]>([])
const isEditDialogVisible = ref(false)
const editingSetting = ref<Partial<ISetting> | null>(null)
const search = ref('')

const filteredSettings = computed(() => {
  if (!search.value)
    return settings.value

  return settings.value.filter(s =>
    s.key.toLowerCase().includes(search.value.toLowerCase())
    || s.label?.toLowerCase().includes(search.value.toLowerCase())
    || s.description?.toLowerCase().includes(search.value.toLowerCase()),
  )
})

const fetchSettings = async () => {
  loading.value = true
  try {
    settings.value = await SettingAPI.getSystem()
  }
  catch (err: any) {
    notify({ type: 'error', title: 'Erreur', message: err.message || 'Erreur lors du chargement' })
  }
  finally {
    loading.value = false
  }
}

const openEditDialog = (setting: ISetting) => {
  editingSetting.value = { ...setting }
  isEditDialogVisible.value = true
}

const openCreateDialog = () => {
  editingSetting.value = { type: 'system', valueType: 'string', inputType: 'text', isPublic: false }
  isEditDialogVisible.value = true
}

const saveSetting = async () => {
  if (!editingSetting.value)
    return

  loading.value = true
  try {
    if (editingSetting.value.id) {
      await SettingAPI.update(editingSetting.value.id, editingSetting.value)
      notify({ type: 'success', title: 'Succès', message: 'Paramètre mis à jour' })
    }
    else {
      await SettingAPI.create(editingSetting.value)
      notify({ type: 'success', title: 'Succès', message: 'Paramètre créé' })
    }
    await fetchSettings()
    isEditDialogVisible.value = false
  }
  catch (err: any) {
    notify({ type: 'error', title: 'Erreur', message: err.message || 'Erreur lors de la sauvegarde' })
  }
  finally {
    loading.value = false
  }
}

const deleteSetting = async (setting: ISetting) => {
  if (!confirm('Êtes-vous sûr de vouloir supprimer ce paramètre ?'))
    return

  loading.value = true
  try {
    await SettingAPI.delete({ key: setting.key, type: 'system' })
    notify({ type: 'success', title: 'Succès', message: 'Paramètre supprimé' })
    await fetchSettings()
  }
  catch (err: any) {
    notify({ type: 'error', title: 'Erreur', message: err.message || 'Erreur lors de la suppression' })
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard
        title="Paramètres Système"
        :loading="loading"
      >
        <template #default>
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <VTextField
                v-model="search"
                placeholder="Rechercher..."
                prepend-icon="bx-search"
                clearable
                density="compact"
                style="max-width: 300px"
              />
              <VBtn
                color="primary"
                @click="openCreateDialog"
              >
                <VIcon icon="bx-plus" />
                Nouveau paramètre
              </VBtn>
            </div>

            <VTable
              v-if="filteredSettings.length"
              class="text-no-wrap"
            >
              <thead>
                <tr>
                  <th>Clé</th>
                  <th>Libellé</th>
                  <th>Valeur</th>
                  <th>Type</th>
                  <th class="text-center">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="setting in filteredSettings"
                  :key="setting.id"
                >
                  <td class="font-weight-bold">
                    {{ setting.key }}
                  </td>
                  <td>{{ setting.label || '-' }}</td>
                  <td
                    class="text-truncate"
                    style="max-width: 200px"
                  >
                    {{ String(setting.value) }}
                  </td>
                  <td>
                    <VChip
                      size="small"
                      :color="setting.valueType === 'boolean' ? 'info' : 'default'"
                    >
                      {{ setting.valueType }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <VBtn
                      icon="bx-edit"
                      size="small"
                      variant="tonal"
                      color="primary"
                      @click="openEditDialog(setting)"
                    />
                    <VBtn
                      icon="bx-trash"
                      size="small"
                      variant="tonal"
                      color="error"
                      class="ms-2"
                      @click="deleteSetting(setting)"
                    />
                  </td>
                </tr>
              </tbody>
            </VTable>
            <div
              v-else
              class="text-center py-10"
            >
              Aucun paramètre trouvé
            </div>
          </VCardText>
        </template>
      </VCard>
    </VCol>
  </VRow>

  <!-- Edit/Create Dialog -->
  <VDialog
    v-model="isEditDialogVisible"
    max-width="600px"
  >
    <VCard :title="editingSetting?.id ? 'Modifier le paramètre' : 'Créer un paramètre'">
      <VCardText>
        <VForm @submit.prevent="saveSetting">
          <VTextField
            v-model="editingSetting!.key"
            label="Clé"
            required
            class="mb-4"
          />
          <VTextField
            v-model="editingSetting!.label"
            label="Libellé"
            class="mb-4"
          />
          <VTextarea
            v-model="editingSetting!.description"
            label="Description"
            class="mb-4"
          />
          <VTextField
            v-model="editingSetting!.value"
            label="Valeur"
            required
            class="mb-4"
          />
          <VSelect
            v-model="editingSetting!.valueType"
            :items="['string', 'boolean', 'integer', 'float', 'array', 'json']"
            label="Type de valeur"
            class="mb-4"
          />
          <VSelect
            v-model="editingSetting!.inputType"
            :items="['text', 'textarea', 'select', 'checkbox', 'toggle', 'number', 'email', 'url', 'date', 'time', 'color']"
            label="Type d'entrée"
            class="mb-4"
          />
          <VSwitch
            v-model="editingSetting!.isPublic"
            label="Public"
            class="mb-4"
          />
          <div class="d-flex gap-2">
            <VBtn
              type="submit"
              color="primary"
              :loading="loading"
            >
              Enregistrer
            </VBtn>
            <VBtn
              variant="tonal"
              @click="isEditDialogVisible = false"
            >
              Annuler
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
