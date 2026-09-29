<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue'
import { useNotificationStore } from '@admin/stores/notifications'
import type { Notification } from '@layouts/types'

const notificationStore = useNotificationStore()

// Filter state
const filter = ref<'all' | 'unread' | 'read'>('all')

onMounted(() => {
  notificationStore.fetchNotifications()
  notificationStore.fetchUnreadCount()
})

const mappedNotifications = computed(() => {
  let filtered = notificationStore.notifications

  if (filter.value === 'unread')
    filtered = filtered.filter(n => !n.read_at)
  else if (filter.value === 'read')
    filtered = filtered.filter(n => !!n.read_at)

  return filtered.map(n => ({
    id: n.id,
    title: n.sender ? `${n.sender.name}` : n.title, // Use sender name as title if present, or original title
    subtitle: n.sender ? n.title : (n.message || ''), // If sender is title, message/title becomes subtitle
    time: new Date(n.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    isSeen: !!n.read_at,
    color: n.type,

    // Logic for Avatar: Image > Text (Initials) > Icon
    img: n.sender?.avatar || undefined,
    text: n.sender && !n.sender.avatar ? n.sender.name : undefined,
    icon: !n.sender ? (n.type === 'error' ? 'bx-error' : n.type === 'warning' ? 'bx-error-circle' : 'bx-bell') : undefined,
  })) as Notification[]
})

const removeNotification = () => {
  // Optionnel: implémenter une suppression si supportée par l'API
}

const markRead = (notificationIds: number[]) => {
  notificationIds.forEach(id => notificationStore.markAsRead(id))
}

const markUnRead = () => {
  // Optionnel: l'API Admin actuelle ne semble pas supporter le marquage comme non lu
}

const handleNotificationClick = (notification: Notification) => {
  if (!notification.isSeen)
    notificationStore.markAsRead(notification.id)
}
</script>

<template>
  <Notifications
    :notifications="mappedNotifications"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  >
    <template #header-action>
      <div class="d-flex align-center gap-1 me-2">
        <VBtnToggle
          v-model="filter"
          density="compact"
          mandatory
          variant="outlined"
        >
          <VBtn
            value="all"
            size="x-small"
          >
            All
          </VBtn>
          <VBtn
            value="unread"
            size="x-small"
          >
            Unread
          </VBtn>
        </VBtnToggle>
      </div>
    </template>
  </Notifications>
</template>
