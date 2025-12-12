<script setup lang="ts">
import { useTheme } from 'vuetify'
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

// console.log('ROUTE : ', t)
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
  <!--  les composants communs dans l'application -->

  <ConfirmDialog />
  <CoreDialog />
  <CoreNotify />
</template>
