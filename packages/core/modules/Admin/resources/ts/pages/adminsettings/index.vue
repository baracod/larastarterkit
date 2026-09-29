<script setup lang="ts">
import { can } from '@/@layouts/plugins/casl'
import { AdminSettingAPI } from '../../api/AdminSetting'
import type { IAdminSetting } from '../../types/entities'

import AdminAdminSettingAddOrEdit from '../../components/AdminAdminSettingAddOrEdit.vue'

const { confirmDialog } = useConfirm()
const { t } = useI18n({ useScope: 'global' })

// définition de la page et des permissions nécessaires
definePage({
  meta: {
    action: 'access',
    subject: 'admin_settings',
  },
})

const items = ref<IAdminSetting[]>([])
const loading = ref(true)
const editDialog = ref(false)
const detailDialog = ref(false)
const selectedItem = ref<IAdminSetting | null>(null)
const selectedItems = ref<number[]>([])
const readOnly = ref<boolean>(false)
const { tHeaderCols } = useTranslater()

const getData = async () => {
  loading.value = true
  try {
    items.value = await AdminSettingAPI.getAll()
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
  newVal => {
    if (!newVal)
      readOnly.value = false
  },
)

const openEditDialog = (item?: IAdminSetting) => {
  selectedItem.value = item || null
  editDialog.value = true
}

const openDetailDialog = (item?: IAdminSetting) => {
  selectedItem.value = item || null
  editDialog.value = true
  readOnly.value = true
}

const deleteItem = async (id: number) => {
  if (await confirmDialog()) {
    loading.value = true
    try {
      await AdminSettingAPI.delete(id)
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
      await AdminSettingAPI.deleteMultiple(ids)
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

const { toCamelCase } = useCamelCase()

const entity = toCamelCase('AdminSetting')

const headers = tHeaderCols ({
  entity,
  module: 'Admin',
  headers: [
    { title: 'Type', key: 'type' },
    { title: 'Module', key: 'module' },
    { title: 'User_id', key: 'user_id' },
    { title: 'Key', key: 'key' },
    { title: 'Value', key: 'value' },
    { title: 'Value_type', key: 'value_type' },
    { title: 'Label', key: 'label' },
    { title: 'Description', key: 'description' },
    { title: 'Input_type', key: 'input_type' },
    { title: 'Options', key: 'options' },
    { title: 'Default_value', key: 'default_value' },
    { title: 'Is_public', key: 'is_public' },
    { title: 'actions', key: 'actions', sortable: false, translate: false },
  ],
})

const searchKey = ref('')
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex justify-space-between align-center">
      <h2>{{ t(`Admin.${entity}.titlePlural`) }}</h2>
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
          v-if="$can('browse', 'admin_settings')"
          class="ms-1"
          color="secondary"
          :title="t('action.refresh')"
          icon="bx-refresh"
          @click="refreshList"
        />
        <VBtn
          v-if="$can('add', 'admin_settings')"
          class="ms-1"
          color="success"
          :title="t('action.add')"
          icon="bx-plus"
          @click="openEditDialog"
        />
        <VBtn
          v-if="selectedItems.length"
          class="ms-1"
          color="error"
          :title="t('action.delete')"
          icon="bx-trash"
          @click="deleteManyItems"
        />
      </div>
    </VCardTitle>
    <VCardText>
      <VDataTable
        v-model="selectedItems"
        :entity="entity"
        :disabled="loading"
        :loading="loading"
        :headers="headers"
        :items="items"
        :search="searchKey"
      >
        <template #top />

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
              title="Details"
              @click="openDetailDialog(item)"
            />
            <VMenu
              v-if="can('edit', 'admin_settings') || can('delete', 'admin_settings')"
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
                  v-if="$can('edit', 'admin_settings')"
                  prepend-icon="bx-edit-alt"
                  :title="t('action.edit')"
                  @click="openEditDialog(item)"
                />
                <VListItem
                  v-if="$can('delete', 'admin_settings')"
                  prepend-icon="bx-trash-alt"
                  :title="t('action.delete')"
                  @click="item.id && deleteItem(item.id)"
                />
              </VList>
            </VMenu>
          </div>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>

  <AdminAdminSettingAddOrEdit
    v-model="editDialog"
    :readonly="readOnly"
    :item="selectedItem ?? undefined"
    @saved="refreshList"
  />
</template>
