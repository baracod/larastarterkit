<script setup lang="ts">
import { can } from '@/@layouts/plugins/casl'
import AuthModuleFrame from '../../components/AuthModuleFrame.vue'
import { PermissionAPI } from '../../api/Permission'
import type { IPermission } from '../../types/entities'

import AuthPermissionAddOrEdit from '../../components/AuthPermissionAddOrEdit.vue'

const { confirmDialog } = useDialog()
const { t } = useI18n({ useScope: 'global' })

// définition de la page et des permissions nécessaires
definePage({
  meta: {
    action: 'browse',
    subject: 'auth_permissions',
  },
})

const items = ref<IPermission[]>([])
const loading = ref(true)
const loadError = ref(false)
const editDialog = ref(false)
const detailDialog = ref(false)
const selectedItem = ref<IPermission | null>(null)
const selectedItems = ref<number[]>([])
const readOnly = ref<boolean>(false)

const { tHeaderCols } = useTranslater()

const getData = async () => {
  loading.value = true
  loadError.value = false
  try {
    items.value = await PermissionAPI.getAll()
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

const openEditDialog = (item?: IPermission) => {
  selectedItem.value = item || null
  editDialog.value = true
}

const openDetailDialog = (item?: IPermission) => {
  selectedItem.value = item || null
  editDialog.value = true
  readOnly.value = true
}

const deleteItem = async (id: number) => {
  if (await confirmDialog()) {
    loading.value = true
    try {
      await PermissionAPI.delete(id)
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
      await PermissionAPI.deleteMultiple(ids)
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

const entity = 'Permission'.toLowerCase()

const headers = tHeaderCols({
  entity,
  module: 'Auth',
  headers: [
    { title: 'Key', key: 'key' },
    { title: 'Action', key: 'action' },
    { title: 'Subject', key: 'subject' },
    { title: 'Description', key: 'description' },
    { title: 'Table_name', key: 'table_name' },
    { title: 'Always_allow', key: 'always_allow' },
    { title: 'Is_public', key: 'is_public' },
    { title: '', key: 'actions', sortable: false, translate: false },
  ],
})

const searchKey = ref('')
</script>

<template>
  <AuthModuleFrame
    :filter-count="searchKey ? 1 : 0"
    @reset="searchKey = ''"
  >
    <template #actions>
      <div class="action">
        <VBtn
          v-if="$can('browse', 'auth_permissions')"
          class="ms-1"
          color="secondary"
          :title="t('action.refresh')"
          icon="bx-refresh"
          @click="refreshList"
        />
        <VBtn
          v-if="$can('add', 'auth_permissions')"
          class="ms-1"
          color="success"
          :title="t('action.add')"
          prepend-icon="mdi-plus"
          @click="openEditDialog"
        >
          {{ t('action.add') }}
        </VBtn>
        <VBtn
          v-if="selectedItems.length"
          class="ms-1"
          color="error"
          :title="t('action.delete')"
          icon="bx-trash"
          @click="deleteManyItems"
        />
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
        >
          <template #top />

          <template #item.always_allow="{ item }">
            <VChip
              :color="item.always_allow ? 'success' : 'error'"
              size="small"
            >
              {{ item.always_allow ? t('action.yes') : t('action.no') }}
            </VChip>
          </template>

          <template #item.is_public="{ item }">
            <VChip
              :color="item.is_public ? 'success' : 'error'"
              size="small"
            >
              {{ item.is_public ? t('action.yes') : t('action.no') }}
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
                @click="openDetailDialog(item)"
              />
              <VMenu v-if="can('edit', 'auth_permissions') || can('delete', 'auth_permissions')">
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
                    v-if="$can('edit', 'auth_permissions')"
                    prepend-icon="bx-edit-alt"
                    :title="t('action.edit')"
                    @click="openEditDialog(item)"
                  />
                  <VListItem
                    v-if="$can('delete', 'auth_permissions')"
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

    <AuthPermissionAddOrEdit
      v-model="editDialog"
      :readonly="readOnly"
      :item="selectedItem ?? undefined"
      @saved="refreshList"
    />
  </AuthModuleFrame>
</template>
