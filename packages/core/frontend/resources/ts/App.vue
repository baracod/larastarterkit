<script setup lang="ts">
import { useTheme } from 'vuetify'
import { startRealtime, stopRealtime } from '@/utils/realtime'
import { useNotificationStore } from '@admin/stores/notifications'
import { useAuthStore } from '@auth/stores'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'

const { global } = useTheme()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

// init auth store to load user from localStorage if exists
const auth = useAuthStore()
const notifications = useNotificationStore()

watch(() => auth.user?.id, id => {
  stopRealtime()
  notifications.resetSession()
  if (id) {
    startRealtime()
    notifications.setupWebsocketListener(id)
  }
}, { immediate: true })
onBeforeUnmount(stopRealtime)

const { unauthorized } = storeToRefs(auth)

const configStore = useConfigStore()
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <AuthDialogSuspend v-model="unauthorized" />
      <RouterView />

      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
  <ConfirmDialog />
  <CoreDialog />
  <CoreNotify />
</template>
