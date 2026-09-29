<script setup lang="ts">
import { avatarText } from '@core/utils/formatters'
import { computed, ref } from 'vue'
import AuthUserAddOrEdit from '../AuthUserAddOrEdit.vue'
import { useAuthStore } from '../../stores'
import type { IBioEditable, IUser } from '../../types/entities'
import { userToBioEditable } from '../../utils/userHelpers'
import { UserAPI } from '@auth/api/User'

/* ------------------------------------------------------------------
 * Props & Emits
 * ---------------------------------------------------------------- */
interface Props {
  userData: IUser
  isLoading?: boolean | 1 | 0
  error?: string | null
}
interface Emits {
  (e: 'profileUpdated'): void
}

const props = defineProps<Props>()

const emit = defineEmits<Emits>()

const { t } = useI18n({ useScope: 'global' })

/* ------------------------------------------------------------------
 * Dialogs
 * ---------------------------------------------------------------- */
const authStore = useAuthStore()
const accountDialog = ref(false)
const isUserInfoEditDialogVisible = ref(false)
const isUpgradePlanDialogVisible = ref(false)

/* ------------------------------------------------------------------
 * Données éditables
 * ---------------------------------------------------------------- */
const editableUser = computed<IBioEditable>(() => userToBioEditable(props.userData))

// Toujours un booléen pour Vuetify
const loading = computed<boolean>(() => !!props.isLoading)

const handleSuspend = async (userId: number) => {
  try {
    await UserAPI.suspendUser(userId)
    emit('profileUpdated')
  }
  catch (error) {
    console.error('Error suspending user:', error)
  }
}
</script>

<template>
  <VRow>
    <!-- SECTION User Details -->
    <VCol
      cols="12"
      class="user-detail"
    >
      <VAlert
        v-if="props.error"
        type="error"
        closable
        class="mb-4"
      >
        {{ props.error }}
      </VAlert>

      <VCard
        v-if="props.userData"
        class="v-card-profil"
        :disabled="loading"
        :loading="loading"
      >
        <VCardText class="text-center pt-6">
          <!-- 👉 Avatar -->
          <VAvatar
            rounded
            :size="120"
            :color="!props.userData.avatar ? 'primary' : undefined"
            :variant="!props.userData.avatar ? 'tonal' : undefined"
          >
            <VImg
              v-if="props.userData.avatar"
              :src="props.userData.avatar"
            />
            <span
              v-else
              class="text-5xl font-weight-medium"
            >
              {{ avatarText(props.userData.name) }}
            </span>
          </VAvatar>

          <!-- 👉 User fullName -->
          <h5 class="text-h5 mt-4">
            {{ props.userData.name }}
          </h5>

          <!-- 👉 Role chips -->
          <VChip
            v-for="item in props.userData.role_names"
            :key="item"
            label
            color="primary"
            size="small"
            class="text-capitalize mt-2 me-1"
          >
            {{ item }}
          </VChip>
        </VCardText>

        <VCardText>
          <!-- 👉 Details -->
          <h5 class="text-h5">
            {{ t('Auth.design.details') }}
          </h5>
          <VDivider class="my-4" />

          <!-- 👉 User Details list -->
          <VList class="card-list mt-2">
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{ t('Auth.user.field.username') }} :
                  <div class="d-inline-block text-body-1">
                    {{ props.userData.username }}
                  </div>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{ t('Auth.user.field.name') }} :
                  <div class="d-inline-block text-body-1">
                    {{ props.userData.name }}
                  </div>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{ t('Auth.user.field.email') }} :
                  <span class="text-body-1 d-inline-block">
                    {{ props.userData.email }}
                  </span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{ t('Auth.user.field.active') }} :
                  <div class="d-inline-block text-body-1 text-capitalize">
                    <VChip :color="props.userData.active ? 'success' : 'error'">
                      {{ props.userData.active ? t('action.yes') : t('action.no') }}
                    </VChip>
                  </div>
                </h6>
              </VListItemTitle>
            </VListItem>
          </VList>
        </VCardText>

        <!-- 👉 Edit and Suspend button -->
        <VCardText class="d-flex flex-wrap justify-center gap-3">
          <VBtn
            v-if="authStore.user?.id === props.userData.id || authStore.hasRole('administrator')"
            variant="tonal"
            prepend-icon="mdi-account-edit-outline"
            @click="isUserInfoEditDialogVisible = true"
          >
            {{ t('action.edit') }}
          </VBtn>
          <VBtn
            v-if="$can('edit', 'auth_users')"
            variant="tonal"
            prepend-icon="mdi-shield-account-outline"
            @click="accountDialog = true"
          >
            {{ t('Auth.editProfile.account') }}
          </VBtn>
          <VBtn
            v-if="$can('edit', 'auth_users')"
            variant="tonal"
            :color="props.userData.active ? 'error' : 'success'"
            @click="handleSuspend(props.userData.id)"
          >
            {{ props.userData.active ? t('Auth.user.action.suspend') : t('Auth.user.action.unsuspend') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
    <!-- !SECTION -->
  </VRow>

  <!-- 👉 Edit user info dialog -->
  <AuthUserAddOrEdit
    v-if="accountDialog"
    v-model="accountDialog"
    :item="props.userData"
    @saved="emit('profileUpdated')"
  />
  <UserInfoEditDialog
    v-model:is-dialog-visible="isUserInfoEditDialogVisible"
    :user-data="editableUser"
    @profile-updated="emit('profileUpdated')"
  />

  <!-- 👉 Upgrade plan dialog -->
  <UserUpgradePlanDialog v-model:is-dialog-visible="isUpgradePlanDialogVisible" />
</template>

<style lang="scss" scoped>
@use "@core-scss/template/mixins" as templateMixins;

.user-detail {
  .v-card-profil {
    padding-block: 8px;
    padding-inline: 0;
  }
}

.card-list {
  --v-card-list-gap: 0.5rem;
}

.current-plan {
  border: 2px solid rgb(var(--v-theme-primary));

  @include templateMixins.custom-elevation(var(--v-theme-primary), "sm");
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
