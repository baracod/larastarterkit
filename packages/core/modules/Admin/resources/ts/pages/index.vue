<route lang="json">
{
  "name": "admin",
  "meta": { "action": "access", "subject": "admin" }
}
</route>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useModuleStore } from '../stores/modules'
import { useNotificationStore } from '../stores/notifications'

const moduleStore = useModuleStore()
const notificationStore = useNotificationStore()

onMounted(async () => {
  await Promise.all([
    moduleStore.fetchModules(),
    notificationStore.fetchUnreadCount(),
    notificationStore.fetchNotifications(1),
  ])
})

const stats = computed(() => [
  { title: 'Admin.dashboard.active_modules', value: moduleStore.activeModules.length, icon: 'mdi-package-variant-closed', color: 'primary' },
  { title: 'Admin.dashboard.total_modules', value: moduleStore.modules.length, icon: 'mdi-view-module', color: 'success' },
  { title: 'Admin.dashboard.unread_notifications', value: notificationStore.unreadCount, icon: 'mdi-bell-outline', color: 'warning' },
])
</script>

<template>
  <VRow>
    <!-- Stats Cards -->
    <VCol
      v-for="stat in stats"
      :key="stat.title"
      cols="12"
      sm="4"
    >
      <VCard>
        <VCardText class="d-flex align-center">
          <VAvatar
            :color="stat.color"
            variant="tonal"
            rounded
            size="42"
            class="me-3"
          >
            <VIcon
              :icon="stat.icon"
              size="24"
            />
          </VAvatar>
          <div>
            <p class="text-body-1 mb-0">
              {{ $t(stat.title) }}
            </p>
            <h5 class="text-h5">
              {{ stat.value }}
            </h5>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Modules Overview -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard :title="$t('Admin.modules.title')">
        <VList lines="two">
          <VListItem
            v-for="module in moduleStore.modules"
            :key="module.name"
          >
            <template #prepend>
              <VAvatar
                color="secondary"
                variant="tonal"
                size="32"
              >
                <VIcon :icon="module.enabled ? 'mdi-check' : 'mdi-close'" />
              </VAvatar>
            </template>
            <VListItemTitle>{{ module.name }}</VListItemTitle>
            <VListItemSubtitle>{{ module.description }}</VListItemSubtitle>
            <template #append>
              <VChip
                :color="module.enabled ? 'success' : 'error'"
                size="small"
              >
                {{ module.enabled ? $t('Admin.modules.active') : $t('Admin.modules.inactive') }}
              </VChip>
            </template>
          </VListItem>
        </VList>
        <VCardText class="text-center">
          <VBtn
            variant="text"
            :to="{ name: 'admin-modules' }"
          >
            {{ $t('Admin.modules.management') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Recent Notifications -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard :title="$t('Admin.notifications.title')">
        <VList v-if="notificationStore.notifications.length">
          <VListItem
            v-for="notif in notificationStore.notifications.slice(0, 5)"
            :key="notif.id"
          >
            <template #prepend>
              <VAvatar
                :color="notif.type"
                variant="tonal"
                size="32"
              >
                <VIcon :icon="notif.read_at ? 'mdi-email-open-outline' : 'mdi-email-outline'" />
              </VAvatar>
            </template>
            <VListItemTitle>{{ notif.title }}</VListItemTitle>
            <VListItemSubtitle>{{ notif.message }}</VListItemSubtitle>
            <template #append>
              <div class="text-caption">
                {{ $d(new Date(notif.created_at), 'short') }}
              </div>
            </template>
          </VListItem>
        </VList>
        <VCardText
          v-else
          class="text-center py-5"
        >
          {{ $t('Admin.notifications.no_notifications') }}
        </VCardText>
        <VCardText class="text-center">
          <VBtn
            variant="text"
            :to="{ name: 'admin-notifications' }"
          >
            {{ $t('Admin.notifications.center') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
