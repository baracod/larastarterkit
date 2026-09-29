<script setup lang="ts">
import { useNotification } from '@/composables/useNotification'
import type { IUserSetting } from '../../api/User'
import { UserAPI } from '../../api/User'

interface Props {
  userId: number
}

const props = defineProps<Props>()

const { t } = useI18n({ useScope: 'global' })
const { showNotification } = useNotification()

const loading = ref(false)
const search = ref('')
const settings = ref<IUserSetting[]>([])
const isEditDialogVisible = ref(false)
const editingSetting = ref<IUserSetting | null>(null)

const categoryMap: Record<string, string> = {
  'theme': 'appearance',
  'language': 'general',
  'timezone': 'general',
  'notification.email_enabled': 'notifications',
  'notification.push_enabled': 'notifications',
  'dashboard.default_view': 'dashboard',
}

const settingCatalog = [
  {
    key: 'theme',
    labelKey: 'Auth.settings.catalog.theme.label',
    descriptionKey: 'Auth.settings.catalog.theme.description',
    inputType: 'select',
    valueType: 'string',
    options: { values: ['light', 'dark'] },
    defaultValue: 'light',
  },
  {
    key: 'language',
    labelKey: 'Auth.settings.catalog.language.label',
    descriptionKey: 'Auth.settings.catalog.language.description',
    inputType: 'select',
    valueType: 'string',
    options: { values: ['fr', 'en'] },
    defaultValue: 'fr',
  },
  {
    key: 'notification.email_enabled',
    labelKey: 'Auth.settings.catalog.notificationEmail.label',
    descriptionKey: 'Auth.settings.catalog.notificationEmail.description',
    inputType: 'checkbox',
    valueType: 'boolean',
    defaultValue: true,
  },
  {
    key: 'notification.push_enabled',
    labelKey: 'Auth.settings.catalog.notificationPush.label',
    descriptionKey: 'Auth.settings.catalog.notificationPush.description',
    inputType: 'checkbox',
    valueType: 'boolean',
    defaultValue: true,
  },
  {
    key: 'dashboard.default_view',
    labelKey: 'Auth.settings.catalog.dashboardDefaultView.label',
    descriptionKey: 'Auth.settings.catalog.dashboardDefaultView.description',
    inputType: 'select',
    valueType: 'string',
    options: { values: ['overview', 'analytics', 'tasks'] },
    defaultValue: 'overview',
  },
  {
    key: 'timezone',
    labelKey: 'Auth.settings.catalog.timezone.label',
    descriptionKey: 'Auth.settings.catalog.timezone.description',
    inputType: 'select',
    valueType: 'string',
    options: { values: ['UTC', 'Europe/Paris', 'Africa/Abidjan', 'America/New_York'] },
    defaultValue: 'UTC',
  },
]

const normalizeSetting = (setting: IUserSetting): IUserSetting => {
  const category = setting.category ?? categoryMap[setting.key] ?? 'general'

  return {
    ...setting,
    category,
  }
}

const mergeWithCatalog = (userSettings: IUserSetting[]): IUserSetting[] => {
  const byKey = new Map(userSettings.map(item => [item.key, normalizeSetting(item)]))

  for (const catalogItem of settingCatalog) {
    if (!byKey.has(catalogItem.key)) {
      byKey.set(catalogItem.key, {
        key: catalogItem.key,
        value: catalogItem.defaultValue,
        valueType: catalogItem.valueType,
        inputType: catalogItem.inputType,
        label: t(catalogItem.labelKey),
        description: t(catalogItem.descriptionKey),
        options: catalogItem.options ?? null,
        category: categoryMap[catalogItem.key] ?? 'general',
      })
    }
  }

  return Array.from(byKey.values())
}

const filteredSettings = computed(() => {
  const keyword = search.value.trim().toLowerCase()

  if (!keyword)
    return settings.value

  return settings.value.filter(setting => {
    const label = setting.label?.toLowerCase() ?? ''
    const description = setting.description?.toLowerCase() ?? ''

    return setting.key.toLowerCase().includes(keyword) || label.includes(keyword) || description.includes(keyword)
  })
})

const groupedSettings = computed(() => {
  const groups: Record<string, IUserSetting[]> = {}

  for (const setting of filteredSettings.value) {
    const category = setting.category ?? 'general'

    if (!groups[category])
      groups[category] = []

    groups[category].push(setting)
  }

  return groups
})

const categoryTitle = (category: string): string => {
  return t(`Auth.settings.categories.${category}`)
}

const toBoolean = (value: any): boolean => {
  return value === true || value === '1' || value === 1 || value === 'true'
}

const fetchSettings = async () => {
  loading.value = true
  try {
    const userSettings = await UserAPI.getUserSettings(props.userId)

    settings.value = mergeWithCatalog(userSettings)
  }
  catch (error: any) {
    showNotification({ type: 'error', message: error?.message ?? t('Auth.settings.feedback.loadError') })
  }
  finally {
    loading.value = false
  }
}

const openEditDialog = (setting: IUserSetting) => {
  editingSetting.value = {
    ...setting,
    options: setting.options ?? null,
  }
  isEditDialogVisible.value = true
}

const saveSetting = async () => {
  if (!editingSetting.value)
    return

  loading.value = true
  try {
    await UserAPI.updateUserSettings(props.userId, [editingSetting.value])
    await fetchSettings()
    isEditDialogVisible.value = false
    showNotification({ type: 'success', message: t('Auth.settings.feedback.updateSuccess') })
  }
  catch (error: any) {
    showNotification({ type: 'error', message: error?.message ?? t('Auth.settings.feedback.updateError') })
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <VCard
    :title="t('Auth.settings.title')"
    :loading="loading"
  >
    <VCardText>
      <div class="d-flex justify-space-between align-center mb-4">
        <VTextField
          v-model="search"
          :placeholder="t('Auth.settings.searchPlaceholder')"
          prepend-icon="bx-search"
          clearable
          density="compact"
          style="max-width: 320px"
        />
      </div>

      <template v-if="Object.keys(groupedSettings).length">
        <div
          v-for="(items, category) in groupedSettings"
          :key="category"
          class="mb-6"
        >
          <h6 class="text-h6 mb-2">
            {{ categoryTitle(category) }}
          </h6>

          <VList class="pa-0 rounded border">
            <VListItem
              v-for="setting in items"
              :key="setting.key"
              class="border-b"
            >
              <VListItemTitle>{{ setting.label || setting.key }}</VListItemTitle>
              <VListItemSubtitle>{{ setting.description }}</VListItemSubtitle>

              <template #append>
                <div class="d-flex align-center gap-3">
                  <VChip
                    v-if="setting.valueType === 'boolean'"
                    size="small"
                    :color="toBoolean(setting.value) ? 'success' : 'error'"
                    variant="tonal"
                  >
                    {{ toBoolean(setting.value) ? t('Auth.settings.values.enabled') : t('Auth.settings.values.disabled') }}
                  </VChip>

                  <span
                    v-else
                    class="text-body-2"
                  >{{ String(setting.value ?? '') }}</span>

                  <VBtn
                    size="small"
                    variant="tonal"
                    color="primary"
                    @click="openEditDialog(setting)"
                  >
                    {{ t('Auth.settings.actions.edit') }}
                  </VBtn>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>
      </template>

      <VAlert
        v-else
        variant="tonal"
        color="info"
      >
        {{ t('Auth.settings.empty') }}
      </VAlert>
    </VCardText>
  </VCard>

  <VDialog
    v-model="isEditDialogVisible"
    max-width="520px"
  >
    <VCard :title="t('Auth.settings.dialog.title')">
      <VCardText>
        <VForm @submit.prevent="saveSetting">
          <VTextField
            v-if="editingSetting && editingSetting.inputType !== 'checkbox'"
            v-model="editingSetting.value"
            :label="t('Auth.settings.dialog.value')"
            class="mb-4"
          />

          <VCheckbox
            v-else-if="editingSetting"
            v-model="editingSetting.value"
            :label="t('Auth.settings.dialog.value')"
            class="mb-4"
          />

          <div class="d-flex gap-2">
            <VBtn
              type="submit"
              color="primary"
              :loading="loading"
            >
              {{ t('Auth.settings.actions.save') }}
            </VBtn>
            <VBtn
              variant="tonal"
              @click="isEditDialogVisible = false"
            >
              {{ t('Auth.settings.actions.cancel') }}
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
