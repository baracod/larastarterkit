<script setup lang="ts">
import RoleAPI from '../../api/Role'
import User from '../../api/User'
import type { IUser } from '../../types/entities'

const { t } = useI18n({ useScope: 'global' })

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

const userId = ref(5)

const userData = ref<IUser | null>()

const roles = ref(await RoleAPI.getAll())

const isLoadingRoles = ref<boolean>(false)

const handleChangePassword = (e: string) => {
  User.changePassword({ newPassword: e, userId: userId.value })
    .then(() => {
      // userData.password = e
    })
    .catch(error => {
      console.error('Error changing password:', error)
    })
  console.log('Test Event :', e)
}

const handleRefreshUser = async () => {
  userData.value = await User.getById(userId.value)
}

onMounted(() => {
  handleRefreshUser()
})

const handleChangeRole = async (selectedRoles: number[]) => {
  console.log(selectedRoles)
  try {
    isLoadingRoles.value = true
    await User.assignRoles(userId.value, selectedRoles)
    userData.value = await User.getById(userId.value)
    isLoadingRoles.value = false
  }
  catch (error) {
    console.error('Error changing roles:', error)
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
      <UserProfilPanel :user-data="userData" />
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
            @change-password="handleChangePassword"
            @refresh-user="handleRefreshUser"
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
