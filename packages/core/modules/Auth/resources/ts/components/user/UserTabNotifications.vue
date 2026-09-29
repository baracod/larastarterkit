<script lang="ts" setup>
import { useNotification } from '@/composables/useNotification'
import type { IUserNotification } from '../../api/User'
import { UserAPI } from '../../api/User'

interface Props {
  userId: number
}

const props = defineProps<Props>()

const { t } = useI18n({ useScope: 'global' })
const { showNotification } = useNotification()

const loading = ref(false)
const notifications = ref<IUserNotification[]>([])
const statusFilter = ref<'all' | 'read' | 'unread'>('all')
const sortOrder = ref<'newest' | 'oldest'>('newest')
const perPage = ref(10)
const page = ref(1)
const unreadCount = ref(0)

const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 10,
  total: 0,
})

const statusChipColor = (item: IUserNotification): string => {
  return item.readAt ? 'success' : 'warning'
}

const statusChipLabel = (item: IUserNotification): string => {
  return item.readAt ? t('Auth.notifications.status.read') : t('Auth.notifications.status.unread')
}

const fetchNotifications = async () => {
  loading.value = true
  try {
    const response = await UserAPI.getNotifications(props.userId, {
      status: statusFilter.value,
      sort: sortOrder.value,
      page: page.value,
      perPage: perPage.value,
    })

    notifications.value = response.notifications
    pagination.value = response.pagination
    unreadCount.value = response.unreadCount
  }
  catch (error: any) {
    showNotification({ type: 'error', message: error?.message ?? t('Auth.notifications.feedback.loadError') })
  }
  finally {
    loading.value = false
  }
}

const markAsRead = async (notificationId: number) => {
  try {
    await UserAPI.markNotificationAsRead(props.userId, notificationId)
    await fetchNotifications()
    showNotification({ type: 'success', message: t('Auth.notifications.feedback.markReadSuccess') })
  }
  catch (error: any) {
    showNotification({ type: 'error', message: error?.message ?? t('Auth.notifications.feedback.markReadError') })
  }
}

const markAllAsRead = async () => {
  try {
    await UserAPI.markAllNotificationsAsRead(props.userId)
    await fetchNotifications()
    showNotification({ type: 'success', message: t('Auth.notifications.feedback.markAllReadSuccess') })
  }
  catch (error: any) {
    showNotification({ type: 'error', message: error?.message ?? t('Auth.notifications.feedback.markAllReadError') })
  }
}

watch([statusFilter, sortOrder, perPage], () => {
  page.value = 1
  fetchNotifications()
})

watch(page, fetchNotifications)

onMounted(fetchNotifications)
</script>

<template>
  <VCard
    class="user-tab-notification"
    :title="t('Auth.notifications.title')"
    :loading="loading"
  >
    <VCardText>
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
        <div class="d-flex align-center gap-2">
          <VBadge
            color="error"
            :content="unreadCount"
            :model-value="unreadCount > 0"
            offset-x="4"
            offset-y="6"
          >
            <VChip
              color="primary"
              size="small"
            >
              {{ t('Auth.notifications.unreadCounter') }}
            </VChip>
          </VBadge>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <VSelect
            v-model="statusFilter"
            :items="[
              { title: t('Auth.notifications.filters.all'), value: 'all' },
              { title: t('Auth.notifications.filters.unread'), value: 'unread' },
              { title: t('Auth.notifications.filters.read'), value: 'read' },
            ]"
            :label="t('Auth.notifications.filters.label')"
            density="compact"
            style="min-width: 170px"
          />

          <VSelect
            v-model="sortOrder"
            :items="[
              { title: t('Auth.notifications.sort.newest'), value: 'newest' },
              { title: t('Auth.notifications.sort.oldest'), value: 'oldest' },
            ]"
            :label="t('Auth.notifications.sort.label')"
            density="compact"
            style="min-width: 190px"
          />

          <VBtn
            color="primary"
            variant="tonal"
            :disabled="unreadCount === 0"
            @click="markAllAsRead"
          >
            {{ t('Auth.notifications.actions.markAllRead') }}
          </VBtn>
        </div>
      </div>

      <VList
        v-if="notifications.length"
        class="pa-0"
      >
        <VListItem
          v-for="item in notifications"
          :key="item.id"
          class="rounded border mb-2"
        >
          <template #prepend>
            <VIcon :icon="item.readAt ? 'bx-envelope-open' : 'bx-envelope'" />
          </template>

          <VListItemTitle class="d-flex align-center gap-2">
            <span>{{ item.title }}</span>
            <VChip
              size="x-small"
              :color="statusChipColor(item)"
              variant="tonal"
            >
              {{ statusChipLabel(item) }}
            </VChip>
          </VListItemTitle>

          <VListItemSubtitle class="mt-1">
            {{ item.message || t('Auth.notifications.emptyMessage') }}
          </VListItemSubtitle>

          <template #append>
            <VFadeTransition>
              <VBtn
                v-if="!item.readAt"
                size="small"
                variant="tonal"
                color="primary"
                @click="markAsRead(item.id)"
              >
                {{ t('Auth.notifications.actions.markRead') }}
              </VBtn>
            </VFadeTransition>
          </template>
        </VListItem>
      </VList>

      <VAlert
        v-else
        variant="tonal"
        color="info"
        class="mt-4"
      >
        {{ t('Auth.notifications.empty') }}
      </VAlert>

      <div class="d-flex justify-end mt-4">
        <VPagination
          v-model="page"
          :length="pagination.lastPage"
          :total-visible="7"
        />
      </div>
    </VCardText>
  </VCard>
</template>
