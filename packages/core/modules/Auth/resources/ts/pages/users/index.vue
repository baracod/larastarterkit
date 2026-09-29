<script setup lang="ts">
import { can } from '@/@layouts/plugins/casl'
import { useAuthStore } from '../../stores'
import AuthModuleFrame from '../../components/AuthModuleFrame.vue'
import { UserAPI } from '../../api/User'
import type { IUser } from '../../types/entities'

import AuthUserAddOrEdit from '../../components/AuthUserAddOrEdit.vue'
import { useAuthMailLanguage } from '@auth/composable/useAuthMailLanguage'
import AuthMailLanguageSelect from '@auth/components/AuthMailLanguageSelect.vue'

const emailLocale = useAuthMailLanguage()

const { confirmDialog } = useDialog()
const { t } = useI18n({ useScope: 'global' })
const { notify } = useNotify()
const instructionsSending = ref(false)
const instructionsDialog = ref(false)
const instructionsTarget = ref<IUser | null>(null)

async function sendLoginInstructions(item: IUser) {
  if (instructionsSending.value || !item.id)
    return
  instructionsSending.value = true
  try {
    await UserAPI.sendLoginInstructions(item.id, emailLocale.value)
    instructionsDialog.value = false
    notify({ type: 'success', message: t('Auth.accountMail.queued') })
  }
  catch {
    notify({ type: 'error', message: t('Auth.accountMail.failed') })
  }
  finally { instructionsSending.value = false }
}

// définition de la page et des permissions nécessaires
definePage({
  meta: {
    action: 'browse',
    subject: 'auth_users',
  },
})

const authStore = useAuthStore()
const items = shallowRef<IUser[]>([])
const loading = ref(true)
const loadError = ref(false)
const editDialog = ref(false)
const detailDialog = ref(false)
const selectedItem = ref<IUser | null>(null)
const selectedItems = ref<number[]>([])
const readOnly = ref<boolean>(false)
const forcePasswordDialog = ref(false)
const forcePasswordTarget = ref<IUser | null>(null)

const getData = async () => {
  loading.value = true
  loadError.value = false
  try {
    items.value = await UserAPI.getAll()
  }
  catch (error) {
    loadError.value = true
    console.error(error)
  }
  finally {
    loading.value = false
  }
}

onMounted(async () => {
  await getData()
})

// pour désactiver me mode edition
watch(
  () => editDialog.value,
  newVal => {
    if (!newVal)
      readOnly.value = false
  },
)

const openEditDialog = (item?: IUser) => {
  selectedItem.value = item || null
  editDialog.value = true
}

const deleteItem = async (id: number) => {
  if (await confirmDialog()) {
    loading.value = true
    try {
      await UserAPI.delete(id)
      await getData()
    }
    catch (error) {
      console.error(error)
    }
    finally {
      loading.value = false
    }
  }
}

const deleteManyItems = async (ids: number[]) => {
  if (await confirmDialog()) {
    loading.value = true
    try {
      await UserAPI.deleteMultiple(ids)
      await getData()
    }
    catch (error) {
      console.error(error)
    }
    finally {
      loading.value = false
    }
  }
}

const refreshList = async () => {
  editDialog.value = false
  detailDialog.value = false
  await getData()
}

const entity = 'User'.toLowerCase()

const { tHeaderCols } = useTranslater()

const headers = tHeaderCols({
  module: 'Auth',
  entity: 'user',
  headers:
  [
    { title: 'Name', key: 'name' },
    { title: 'Username', key: 'username' },
    { title: 'Active', key: 'active' },
    { title: 'Roles', key: 'role_names', sortable: false },
    { title: '', key: 'actions', sortable: false, translate: false },
  ],
})

const searchKey = ref('')

const actions = [
  { title: 'Suspendre', value: 'suspend', icon: 'mdi-pause', color: 'warning' },
  { title: 'Activer', value: 'reactivate', icon: 'mdi-play', color: 'success' },
  { title: 'Supprimer', value: 'delete', icon: 'mdi-delete', color: 'error' },
]

const openForcePasswordDialog = (item: IUser) => {
  forcePasswordTarget.value = item
  forcePasswordDialog.value = true
}

const suspendManyItems = async (ids: number[]) => {
  if (await confirmDialog({
    title: 'Confirmer la suspension',
    message: 'Êtes-vous sûr de vouloir suspendre ces utilisateurs ?',

  })) {
    loading.value = true
    try {
      await UserAPI.suspendUsers(ids)
      selectedItems.value = []

      await getData()
    }
    catch (error) {
      console.error(error)
    }
    finally {
      loading.value = false
    }
  }
}

const reactivateManyItems = async (ids: number[]) => {
  if (await confirmDialog({
    title: 'Confirmer la réactivation',
    message: 'Êtes-vous sûr de vouloir réactiver ces utilisateurs ?',
  })) {
    loading.value = true
    try {
      await UserAPI.reactivateUsers(ids)
      await getData()
      selectedItems.value = []
    }
    catch (error) {
      console.error(error)
    }
    finally {
      loading.value = false
    }
  }
}

const emmetAction = (action: string) => {
  if (action === 'delete' && selectedItems.value.length)
    deleteManyItems(selectedItems.value)

  else if (action === 'suspend' && selectedItems.value.length)
    suspendManyItems(selectedItems.value)

  else if (action === 'reactivate' && selectedItems.value.length)
    reactivateManyItems(selectedItems.value)
}
</script>

<template>
  <AuthModuleFrame
    :filter-count="searchKey ? 1 : 0"
    @reset="searchKey = ''"
  >
    <template #actions>
      <div class="action">
        <VBtn
          v-if="$can('browse', 'auth_users')"
          class="ms-1"
          color="secondary"
          :title="t('action.refresh')"
          icon="bx-refresh"
          @click="refreshList"
        />
        <VBtn
          v-if="$can('add', 'auth_users')"
          class="mx-1"
          color="success"
          :title="t('action.add')"
          prepend-icon="mdi-plus"
          @click="openEditDialog"
        >
          {{ t('action.add') }}
        </VBtn>

        <!-- Menu des actions -->
        <VMenu>
          <template #activator="{ props }">
            <VBtn
              icon="mdi-dots-vertical"
              :aria-label="t('actions')"
              variant="outlined"
              v-bind="props"
            />
          </template>

          <VList>
            <VListItem
              v-for="(item, i) in actions"
              :key="i"
              :value="i"
              @click="emmetAction(item.value)"
            >
              <template #prepend>
                <VIcon
                  :color="item.color"
                  :icon="item.icon"
                />
              </template>
              <VListItemTitle>{{ item.title }}</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>
      </div>
    </template>
    <template #filters>
      <VTextField
        v-model="searchKey"
        :label="t('Auth.design.search')"
        prepend-inner-icon="mdi-magnify"
        clearable
        hide-details
        @click:clear="searchKey = ''"
      />
    </template>
    <VAlert
      v-if="loadError"
      type="error"
      variant="tonal"
      role="alert"
      class="mb-4"
    >
      {{ t('Auth.access.requestError') }}
      <VBtn
        variant="text"
        :loading="loading"
        @click="getData"
      >
        {{ t('action.refresh') }}
      </VBtn>
    </VAlert>
    <VCard>
      <!-- header -->

      <!-- table -->
      <VCardText>
        <VDataTable
          v-model="selectedItems"
          :mobile-breakpoint="600"
          :entity="entity"
          :disabled="loading"
          :loading="loading"
          :headers="headers"
          :items="items"
          :search="searchKey"
          module="Auth"
          item-value="id"
          show-select
        >
          <template #top />

          <template #item.name="{ item }">
            <div class="d-flex align-center">
              <VAvatar
                size="32"
                :color="item.avatar ? '' : 'primary'"
                :class="item.avatar ? '' : 'v-avatar-light-bg primary--text'"
                :variant="!item.avatar ? 'tonal' : undefined"
              >
                <VImg
                  v-if="item.avatar"
                  :src="item.avatar"
                />
                <span v-else>{{ avatarText(item.name) }}</span>
              </VAvatar>
              <div class="d-flex flex-column ms-3">
                <span class="d-block font-weight-medium text-high-emphasis text-truncate">{{ item.name }}</span>
                <small>{{ item.email }}</small>
              </div>
            </div>
          </template>

          <template #item.role_names="{ item }">
            <VChip
              v-for="role in item.role_names"
              :key="role"
              class="mx-1"
              size="small"
            >
              {{ role }}
            </VChip>
          </template>

          <template #item.active="{ item }">
            <VChip
              :color="item.active ? 'success' : 'error'"
              size="small"
            >
              {{ item.active ? t('action.yes') : t('action.no') }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex flex-nowrap align-center justify-end px-1">
              <VBtn
                color="info"
                variant="text"
                icon="bx-show-alt"
                density="compact"
                :aria-label="t('Auth.design.details')"
                :to="{ name: 'auth-users-id', params: { id: item.id } }"
              />
              <VMenu v-if="can('edit', 'auth_users') || can('delete', 'auth_users')">
                <template #activator="{ props }">
                  <VBtn
                    icon="mdi-dots-vertical"
                    variant="outlined"
                    v-bind="props"
                    density="compact"
                    :aria-label="t('actions')"
                  />
                </template>
                <VList>
                  <VListItem
                    v-if="$can('edit', 'auth_users')"
                    prepend-icon="bx-edit-alt"
                    :title="t('action.edit')"
                    @click="openEditDialog(item)"
                  />
                  <VListItem
                    v-if="authStore.hasRole('administrator')"
                    prepend-icon="mdi-map-marker-account-outline"
                    :title="t('Auth.assignments.tab')"
                    :to="{ name: 'auth-users-id', params: { id: item.id }, query: { tab: 'assignments' } }"
                  />
                  <VListItem
                    v-if="$can('edit', 'auth_users')"
                    prepend-icon="mdi-lock-reset"
                    :title="t('Auth.user.forceReset.action')"
                    @click="openForcePasswordDialog(item)"
                  />
                  <VListItem
                    v-if="$can('delete', 'auth_users')"
                    prepend-icon="bx-trash-alt"
                    :title="t('action.delete')"
                    @click="deleteItem(item.id)"
                  />
                  <VListItem
                    v-if="authStore.hasRole('administrator')"
                    prepend-icon="mdi-email-fast-outline"
                    :title="t('Auth.accountMail.sendInstructions')"
                    :disabled="instructionsSending"
                    @click="instructionsTarget = item; instructionsDialog = true"
                  />
                </VList>
              </VMenu>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <AuthUserAddOrEdit
      v-model="editDialog"
      :readonly="readOnly"
      :item="selectedItem ?? undefined"
      @saved="refreshList"
    />

    <AuthForcePasswordDialog
      v-model="forcePasswordDialog"
      :user="forcePasswordTarget"
      @done="refreshList"
    />
    <VDialog
      v-model="instructionsDialog"
      max-width="480"
      :persistent="instructionsSending"
    >
      <VCard :title="t('Auth.accountMail.sendInstructions')">
        <VCardText>
          <p class="mb-4">
            {{ instructionsTarget?.email }}
          </p>
          <AuthMailLanguageSelect
            v-model="emailLocale"
            :disabled="instructionsSending"
          />
        </VCardText>
        <VCardActions>
          <VBtn
            :disabled="instructionsSending"
            @click="instructionsDialog = false"
          >
            {{ t('action.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="instructionsSending"
            @click="instructionsTarget && sendLoginInstructions(instructionsTarget)"
          >
            {{ t('Auth.accountMail.send') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </AuthModuleFrame>
</template>
