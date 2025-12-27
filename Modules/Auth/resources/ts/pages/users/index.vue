<script setup lang="ts">
import { can } from '@/@layouts/plugins/casl'
import { UserAPI } from '../../api/User'
import type { IUser } from '../../types/entities'

import AuthUserAddOrEdit from '../../components/AuthUserAddOrEdit.vue'

const { confirmDialog } = useDialog()
const { t } = useI18n({ useScope: 'global' })

// définition de la page et des permissions nécessaires
definePage({
  meta: {
    action: 'access',
    subject: 'auth_users',
  },
})

const items = shallowRef<IUser[]>([])
const loading = ref(true)
const editDialog = ref(false)
const detailDialog = ref(false)
const selectedItem = ref<IUser | null>(null)
const selectedItems = ref<number[]>([])
const readOnly = ref<boolean>(false)

const getData = async () => {
  loading.value = true
  try {
    items.value = await UserAPI.getAll()
  }
  catch (error) {
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
  (newVal, oldVal) => {
    if (newVal)
      console.log('Entré en mode édition ✅')

    else
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

const toRead = async (item: IUser) => {
  const router = useRouter()

  if (!item.id)
    return

  await nextTick(() => {
    router.replace({
      name: 'auth-users-id',
      params: { id: item.id },
    })
  })
}

const searchKey = ref('')

const actions = [
  { title: 'Suspendre', value: 'suspend', icon: 'mdi-pause', color: 'warning' },
  { title: 'Activer', value: 'reactivate', icon: 'mdi-play', color: 'success' },
  { title: 'Supprimer', value: 'delete', icon: 'mdi-delete', color: 'error' },
]

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
  <VCard>
    <!-- header -->
    <VCardTitle class="d-flex justify-space-between align-center">
      <h2>{{ t(`Auth.${entity}.titlePlural`) }}</h2>
      <div class="flex-grow-1">
        <CoreTextField
          v-model="searchKey"
          :placeholder="t('action.search')"
          class="mx-auto pa-0"
          append-inner-icon="bx-search"
        />
      </div>

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
          icon="bx-plus"
          @click="openEditDialog"
        />

        <!-- Menu des actions -->
        <VMenu>
          <template #activator="{ props }">
            <VBtn
              icon="mdi-dots-vertical"
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
    </VCardTitle>
    <!-- table -->
    <VCardText>
      <VDataTable
        v-model="selectedItems"
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
              title="Details"
              :to="{ name: 'auth-users-id', params: { id: item.id } }"
              @click="toRead(item)"
            />
            <VMenu
              v-if="can('edit', 'auth_users') || can('delete', 'auth_users')"
              open-on-hover
            >
              <template #activator="{ props }">
                <VBtn
                  icon="mdi-dots-vertical"
                  variant="outlined"
                  v-bind="props"
                  density="compact"
                  title="Actions"
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
                  v-if="$can('delete', 'auth_users')"
                  prepend-icon="bx-trash-alt"
                  :title="t('action.delete')"
                  @click="deleteItem(item.id)"
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
    :item="selectedItem"
    @saved="refreshList"
  />
</template>
