<script setup lang="ts">
import { RoleAPI } from '../../api/Role'
import User from '../../api/User'
import { useUser } from '../../composable/useUser'

definePage({
  meta: {
    action: 'access',
    subject: 'auth_users',
  },
})

const userTab = ref(null)

const tabs = [
  { icon: 'bx-lock-alt', title: 'Security' },
  { icon: 'bx-bell', title: 'Notifications' },
]

const route = useRoute('auth-users-id')
const userId = ref<number>(+route.params.id)

const { userData, isLoading: isLoadingProfile, error, fetchUser } = useUser(userId.value)

const roles = ref(await RoleAPI.getAll())

const isLoadingRoles = ref<boolean>(false)

const handleChangeRole = async (selectedRoles: number[]) => {
  console.log(selectedRoles)
  try {
    isLoadingRoles.value = true
    await User.assignRoles(userId.value, selectedRoles)
    await fetchUser() // Refresh user data
    isLoadingRoles.value = false
  }
  catch (e) {
    console.error('Error changing roles:', e)
  }
}
</script>

<template>
  <VRow v-if="userData">
    <!-- Profile user -->
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <UserProfilPanel
        :user-data="userData"
        :is-loading="isLoadingProfile"
        :error="error"
        @profile-updated="fetchUser"
      />
    </VCol>

    <!-- tabs of user -->
    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <!-- menu -->
      <VTabs
        v-model="userTab"
        class="v-tabs-pill"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.icon"
        >
          <VIcon
            :size="18"
            :icon="tab.icon"
            class="me-1"
          />
          <span>{{ tab.title }}</span>
        </VTab>
      </VTabs>

      <VWindow
        v-model="userTab"
        class="mt-6 disable-tab-transition"
        :touch="false"
      >
        <VWindowItem>
          <UserTabSecurity
            :user="userData"
            :roles="roles"
            :is-loading-roles="isLoadingRoles"
            @refresh-user="fetchUser"
            @change-role="handleChangeRole"
          />
        </VWindowItem>

        <VWindowItem>
          <UserTabNotifications />
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
</template>
