<script setup lang="ts">
import AuthModuleFrame from '../../components/AuthModuleFrame.vue'
import { useUser } from '../../composable/useUser'
import { useAuthStore } from '../../stores'

definePage({
  meta: {
    action: 'read',
    subject: 'auth_users',
  },
})

const userTab = ref('general')
const { t } = useI18n({ useScope: 'global' })
const authStore = useAuthStore()

const tabs = computed(() => [
  { value: 'general', icon: 'bx-user', title: t('Auth.profile.tabs.general') },
  { value: 'notifications', icon: 'bx-bell', title: t('Auth.profile.tabs.notifications') },
  { value: 'settings', icon: 'bx-cog', title: t('Auth.profile.tabs.settings') },
  ...(authStore.hasRole('administrator') ? [{ value: 'assignments', icon: 'bx-map', title: t('Auth.assignments.tab') }] : []),
  { value: 'security', icon: 'bx-lock-alt', title: t('Auth.profile.tabs.security') },
])

const route = useRoute('auth-users-id')
const userId = computed(() => Number(route.params.id))

watch(() => route.query.tab, tab => {
  if (typeof tab === 'string' && tabs.value.some(item => item.value === tab))
    userTab.value = tab
}, { immediate: true })

const router = useRouter()

// Vérifier si l'utilisateur peut accéder à ce profil
const canAccessProfile = computed(() => {
  // Soit c'est son propre profil
  if (authStore.user?.id === userId.value)
    return true

  // Soit il a la permission de lire tous les profils (admin)
  return authStore.can('read', 'auth_users') || authStore.hasRole('administrator')
})

// Rediriger si accès non autorisé
watchEffect(() => {
  if (!canAccessProfile.value) {
    console.warn('Unauthorized access attempt to user profile:', userId.value)
    router.replace({ name: 'not-authorized' })
  }
})

const { userData, isLoading: isLoadingProfile, error, fetchUser } = useUser(userId)
</script>

<template>
  <AuthModuleFrame>
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
        <VCard
          class="mb-4"
          variant="tonal"
          color="primary"
        >
          <VCardText class="d-flex align-center justify-space-between flex-wrap gap-2">
            <div>
              <div class="text-h6">
                {{ t('Auth.profile.title') }}
              </div>
              <div class="text-body-2">
                {{ t('Auth.profile.subtitle') }}
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- menu -->
        <VTabs
          v-model="userTab"
          class="auth-profile-tabs"
          show-arrows
        >
          <VTab
            v-for="tab in tabs"
            :key="tab.icon"
            :value="tab.value"
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
          <VWindowItem value="general">
            <VCard>
              <VCardText>
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="text-caption text-disabled">
                      {{ t('Auth.profile.fields.name') }}
                    </div>
                    <div class="text-body-1">
                      {{ userData.name }}
                    </div>
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="text-caption text-disabled">
                      {{ t('Auth.profile.fields.email') }}
                    </div>
                    <div class="text-body-1">
                      {{ userData.email }}
                    </div>
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="text-caption text-disabled">
                      {{ t('Auth.profile.fields.username') }}
                    </div>
                    <div class="text-body-1">
                      {{ userData.username || '-' }}
                    </div>
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="text-caption text-disabled">
                      {{ t('Auth.profile.fields.status') }}
                    </div>
                    <VChip
                      :color="userData.active ? 'success' : 'error'"
                      size="small"
                      variant="tonal"
                    >
                      {{ userData.active ? t('Auth.profile.status.active') : t('Auth.profile.status.inactive') }}
                    </VChip>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VWindowItem>

          <VWindowItem value="notifications">
            <UserTabNotifications :user-id="userId" />
          </VWindowItem>

          <VWindowItem value="settings">
            <UserTabSettings :user-id="userId" />
          </VWindowItem>

          <VWindowItem
            v-if="authStore.hasRole('administrator')"
            value="assignments"
          >
            <UserTabModules
              :key="userId"
              :user-id="userId"
            />
          </VWindowItem>

          <VWindowItem value="security">
            <UserTabSecurity :user="userData" />
          </VWindowItem>
        </VWindow>
      </VCol>
    </VRow>
  </AuthModuleFrame>
</template>
