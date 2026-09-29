<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useNotificationStore } from '../../stores/notifications'

const notificationStore = useNotificationStore()
const loading = ref(false)

onMounted(async () => {
  await notificationStore.fetchNotifications(1)
  await notificationStore.fetchUnreadCount()
})

const markAsRead = async (id: number) => {
  await notificationStore.markAsRead(id)
}

const markAllAsRead = async () => {
  loading.value = true
  await notificationStore.markAllAsRead()
  loading.value = false
}

const changePage = (page: number) => {
  notificationStore.fetchNotifications(page)
}
</script>

<template>
  <VCard :title="$t('Admin.notifications.center')">
    <template #append>
      <VBtn
        v-if="notificationStore.unreadCount > 0"
        variant="text"
        color="primary"
        prepend-icon="mdi-check-all"
        :loading="loading"
        @click="markAllAsRead"
      >
        {{ $t('Admin.notifications.mark_all_read') }}
      </VBtn>
    </template>

    <VDivider />

    <VList lines="three">
      <template v-if="notificationStore.notifications.length">
        <VListItem
          v-for="notif in notificationStore.notifications"
          :key="notif.id"
          :active="!notif.read_at"
          @click="!notif.read_at && markAsRead(notif.id)"
        >
          <template #prepend>
            <VAvatar
              v-if="notif.sender && notif.sender.avatar"
              size="40"
              rounded
              class="me-3"
            >
              <VImg
                :src="notif.sender.avatar"
                :alt="notif.sender.name"
              />
            </VAvatar>
            <VAvatar
              v-else
              :color="notif.type"
              variant="tonal"
              rounded
              class="me-3"
            >
              <VIcon :icon="notif.read_at ? 'mdi-email-open-outline' : 'mdi-email-outline'" />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-bold">
            <template v-if="notif.sender">
              <span class="text-primary me-1">{{ notif.sender.name }}</span>
            </template>
            {{ notif.title }}
          </VListItemTitle>
          <VListItemSubtitle>{{ notif.message }}</VListItemSubtitle>

          <template #append>
            <div class="d-flex flex-column align-end">
              <span class="text-caption">{{ $d(new Date(notif.created_at), 'long') }}</span>
              <VBadge
                v-if="!notif.read_at"
                color="primary"
                dot
                inline
              />
            </div>
          </template>
        </VListItem>
      </template>
      <VListItem
        v-else
        class="text-center py-10"
      >
        {{ $t('Admin.notifications.no_notifications') }}
      </VListItem>
    </VList>

    <VDivider />

    <VCardText class="d-flex justify-center">
      <VPagination
        v-model="notificationStore.currentPage"
        :length="notificationStore.lastPage"
        :total-visible="5"
        @update:model-value="changePage"
      />
    </VCardText>
  </VCard>
</template>

<style scoped>
.v-list-item--active {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
