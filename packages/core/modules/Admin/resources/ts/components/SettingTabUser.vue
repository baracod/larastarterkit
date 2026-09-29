<script setup lang="ts">
import { UserAPI } from '@auth/api/User'
import type { IUser } from '@auth/types/entities'
import { computed, onMounted, ref } from 'vue'
import type { ISetting } from '@admin/types/entities'
import { SettingAPI } from '@admin/api/SettingAPI'

const { notify } = useNotify()
const loading = ref(false)
const users = ref<IUser[]>([])
const selectedUserId = ref<number | null>(null)
const settings = ref<ISetting[]>([])
const isEditDialogVisible = ref(false)
const editingSetting = ref<Partial<ISetting> | null>(null)
const search = ref('')
const searchUser = ref('')

const filteredSettings = computed(() => {
  if (!search.value)
    return settings.value

  return settings.value.filter(s =>
    s.key.toLowerCase().includes(search.value.toLowerCase())
    || s.label?.toLowerCase().includes(search.value.toLowerCase()),
  )
})

const filteredUsers = computed(() => {
  if (!searchUser.value)
    return users.value

  return users.value.filter(u =>
    u.name.toLowerCase().includes(searchUser.value.toLowerCase())
    || u.email.toLowerCase().includes(searchUser.value.toLowerCase()),
  )
})

const fetchUsers = async () => {
  loading.value = true
  try {
    users.value = await UserAPI.getAll()
  }
  catch (err: any) {
    notify({ type: 'error', title: 'Erreur', message: err.message })
  }
  finally {
    loading.value = false
  }
}

const fetchSettings = async () => {
  if (!selectedUserId.value)
    return
  loading.value = true
  try {
    settings.value = await SettingAPI.getUsers(selectedUserId.value)
  }
  catch (err: any) {
    notify({ type: 'error', title: 'Erreur', message: err.message })
  }
  finally {
    loading.value = false
  }
}

const selectUser = (userId: number) => {
  selectedUserId.value = userId
  fetchSettings()
}

const openEditDialog = (setting: ISetting) => {
  editingSetting.value = { ...setting }
  isEditDialogVisible.value = true
}

const openCreateDialog = () => {
  editingSetting.value = {
    type: 'user',
    userId: selectedUserId.value || undefined,
    valueType: 'string',
    inputType: 'text',
    isPublic: false,
  }
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
    notify({ type: 'error', title: 'Erreur', message: err.message })
  }
  finally {
    loading.value = false
  }
}

const deleteSetting = async (setting: ISetting) => {
  if (!confirm('Êtes-vous sûr ?'))
    return

  loading.value = true
  try {
    await SettingAPI.delete({ key: setting.key, type: 'user', userId: setting.userId })
    notify({ type: 'success', title: 'Succès', message: 'Paramètre supprimé' })
    await fetchSettings()
  }
  catch (err: any) {
    notify({ type: 'error', title: 'Erreur', message: err.message })
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchUsers)
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="3"
    >
      <VCard title="Utilisateurs">
        <VCardText>
          <VTextField
            v-model="searchUser"
            placeholder="Rechercher..."
            prepend-icon="bx-search"
            clearable
            density="compact"
            class="mb-4"
          />
          <VList
            v-if="filteredUsers.length"
            class="pa-0"
          >
            <VListItem
              v-for="user in filteredUsers"
              :key="user.id"
              :active="selectedUserId === user.id"
              class="cursor-pointer"
              @click="selectUser(user.id)"
            >
              <VListItemTitle class="text-body-2">
                {{ user.name }}
              </VListItemTitle>
              <VListItemSubtitle class="text-xs">
                {{ user.email }}
              </VListItemSubtitle>
            </VListItem>
          </VList>
          <div
            v-else
            class="text-center py-6 text-body-2"
          >
            Aucun utilisateur trouvé
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      md="9"
    >
      <VCard
        v-if="selectedUserId"
        title="Paramètres utilisateur"
        :loading="loading"
      >
        <template #default>
          <VCardText>
            <div class="mb-4 pb-4 border-b">
              <p class="text-body-2 mb-2 font-weight-bold">
                Utilisateur sélectionné :
              </p>
              <p class="text-body-2">
                {{ users.find(u => u.id === selectedUserId)?.name }}
              </p>
            </div>

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
                    style="max-width: 150px"
                  >
                    {{ String(setting.value) }}
                  </td>
                  <td>
                    <VChip size="small">
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
              Aucun paramètre pour cet utilisateur
            </div>
          </VCardText>
        </template>
      </VCard>
      <VCard
        v-else
        title="Paramètres utilisateur"
      >
        <VCardText class="text-center py-10">
          Sélectionnez un utilisateur pour voir ses paramètres
        </VCardText>
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
