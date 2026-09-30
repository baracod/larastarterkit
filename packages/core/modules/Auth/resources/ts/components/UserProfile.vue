<script setup lang="ts">
import { avatarText } from '@core/utils/formatters'
import { useAuthStore } from '@auth/stores'
import type { IUser } from '@auth/types/entities'

const { t } = useI18n({ useScope: 'global' })
const authStore = useAuthStore()
const router = useRouter()
const userCookie = useCookie<IUser | null>('userData')
const user = computed(() => authStore.user ?? userCookie.value)
const avatarFailed = ref(false)

watch(() => user.value?.avatar, () => {
  avatarFailed.value = false
})

const logout = () => authStore.logout()

// const role = computed((() => {
//   if (user.value.roles)
//     return user.value.roles.map!.name

//   return ''
// }))

const openProfileTab = async (tab?: string) => {
  if (user.value)
    await router.replace({ name: 'auth-users-id', params: { id: user.value.id }, query: tab ? { tab } : {} })
}

const toProfile = () => openProfileTab()
const toSettings = () => openProfileTab('settings')
</script>

<template>
  <VBadge
    dot
    bordered
    location="bottom right"
    offset-x="3"
    offset-y="3"
    color="success"
  >
    <VAvatar
      class="cursor-pointer"
      color="primary"
      variant="tonal"
    >
      <VImg
        v-if="user?.avatar && !avatarFailed"
        :src="user.avatar"
        cover
        @error="avatarFailed = true"
      />
      <span
        v-else
        class="text-5xl font-weight-medium"
      >
        {{ avatarText(user?.name ?? '') }}
      </span>
      <!-- SECTION Menu -->
      <VMenu
        activator="parent"
        width="230"
        location="bottom end"
        offset="14px"
      >
        <VList>
          <!-- 👉 User Avatar & Name -->
          <VListItem>
            <template #prepend>
              <VListItemAction start>
                <VBadge
                  dot
                  location="bottom right"
                  offset-x="3"
                  offset-y="3"
                  color="success"
                >
                  <VAvatar
                    v-if="user?.avatar && !avatarFailed"
                    color="primary"
                    variant="tonal"
                  >
                    <VImg
                      :src="user.avatar"
                      cover
                      @error="avatarFailed = true"
                    />
                  </VAvatar>
                </VBadge>
              </VListItemAction>
            </template>

            <VListItemTitle class="font-weight-semibold">
              {{ user?.name }}
            </VListItemTitle>
            <VListItemSubtitle>
              <span
                v-for="role in user?.roles ?? []"
                :key="role.id"
                class="ma-1"
              >
                {{ role.name }}
              </span>
            </VListItemSubtitle>
          </VListItem>

          <VDivider class="my-2" />

          <!-- 👉 Profile -->
          <VListItem
            to="/auth/change-password"
            prepend-icon="mdi-lock-reset"
            :title="t('Auth.accountMail.changePassword')"
          />
          <VListItem
            link
            @click="toProfile"
          >
            <template #prepend>
              <VIcon
                class="me-2"
                icon="bx-user"
                size="22"
              />
            </template>

            <VListItemTitle>{{ t('Auth.profile.title') }}</VListItemTitle>
          </VListItem>

          <!-- 👉 Settings -->
          <VListItem
            link
            @click="toSettings"
          >
            <template #prepend>
              <VIcon
                class="me-2"
                icon="bx-cog"
                size="22"
              />
            </template>

            <VListItemTitle>{{ t('Auth.profile.tabs.settings') }}</VListItemTitle>
          </VListItem>

          <!-- Divider -->
          <VDivider class="my-2" />

          <!-- 👉 Logout -->
          <VListItem @click="logout">
            <template #prepend>
              <VIcon
                class="me-2"
                icon="bx-log-out"
                size="22"
              />
            </template>

            <VListItemTitle>
              {{ t('action.logout') }}
            </VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>
