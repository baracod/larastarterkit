<script setup lang="ts">
import type { IRole, IUser } from '../../types/entities'
import { UserAPI } from '@auth/api/User'

const props = defineProps<{
  user: IUser
  roles: Array<IRole>
  isLoadingRoles?: boolean
}>()

const emit = defineEmits([
  'changeRole',
  'refreshUser',
])

const isNewPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const passwordIsDifferent = ref(false)
const isSettingRoleDialogVisible = ref(false)
const newPassword = ref('')
const confirmPassword = ref('')
const selectedRoles = ref<number[]>([])

// Recent devices Headers
const roleHeaders = [
  { title: 'ID', key: 'id' },
  { title: 'NAME', key: 'name' },
  { title: 'DISPLAY NAME', key: 'display_name' },
  { title: 'DESCRIPTION', key: 'description' },
]

const roleUser = ref(props.user.roles)
const roles = ref(props.roles)
const isLoadingRoles = ref(props.isLoadingRoles ?? false)
const isLoadingChangePassword = ref(false)
const changedPassword = ref(false)
const erroredChangePassword = ref(false)

const { t } = useI18n({ useScope: 'global' })

type RuleFn = (value: string) => true | string

const passwordRules: RuleFn[] = [
  // obligatoire
  v => !!v || t('message.validation.required').replace(':field', ''),

  // longueur minimale
  v => v.length >= 8 || t('Auth.user.labels.changePassword.minLength'),

  // majuscule
  v => /[A-Z]/.test(v) || t('Auth.user.labels.changePassword.uppercase'),

  // symbole (tout sauf lettre ou chiffre)
  v => /[^A-Z0-9]/i.test(v) || t('Auth.user.labels.changePassword.symbol'),
]

watch(() => props.user.roles, newItems => {
  roleUser.value = newItems
}, { immediate: true })

watch(() => props.isLoadingRoles, newItems => {
  console.log('Loading roles:', newItems)
  isLoadingRoles.value = newItems
}, { immediate: true })

const handleChangePassword = async () => {
  if (newPassword.value === confirmPassword.value) {
    passwordIsDifferent.value = false
  }
  else {
    passwordIsDifferent.value = true

    return
  }

  try {
    isLoadingChangePassword.value = true
    await UserAPI.changePassword({
      newPassword: newPassword.value,
      userId: props.user.id,
    })

    newPassword.value = ''
    confirmPassword.value = ''
    changedPassword.value = true
    erroredChangePassword.value = false
  }
  catch (error) {
    console.error('Error changing password:', error)
    erroredChangePassword.value = true
  }
  finally {
    isLoadingChangePassword.value = false
  }

  emit('changePassword', newPassword.value)
}

const v = useValidation()

const setSelectedRoleToUser = () => {
  // UserAPI.assignRoles(props.user.id, selectedRoles.value)
  emit('changeRole', selectedRoles.value)
  isSettingRoleDialogVisible.value = false
}

onMounted(() => {
  selectedRoles.value = props.user.roles?.map(role => role.id) || []
})
</script>

<template>
  <VRow>
    <!-- � Change password -->
    <VCol cols="12">
      <VCard
        :title="t('Auth.user.labels.changePassword.titleForm')"
        :loading="isLoadingChangePassword"
      >
        <VCardText>
          <VSnackbar
            v-model="passwordIsDifferent"
            location="top end"
            :timeout="3000"
          >
            <p>
              <VBtn
                color="error"
                type="tone"
                @click="passwordIsDifferent = false"
              >
                {{ t('Auth.user.labels.changePassword.passwordMismatch') }}
                <VIcon>bx-x</VIcon>
              </VBtn>
            </p>
          </VSnackbar>
          <div>
            <VAlert
              closable
              variant="tonal"
              color="warning"
              class="mb-4"
              :title="t('Auth.user.labels.changePassword.warningPasswordFormaTitle')"
              :text="t('Auth.user.message.changePasswordRule')"
              icon="mdi-alert-circle"
            />
            <VAlert
              v-model="changedPassword"
              closable
              variant="tonal"
              color="success"
              class="mb-4"
              :title="t('Auth.user.labels.changePassword.warningPasswordFormaTitle')"
              :text="t('Auth.user.message.changePasswordSuccess')"
              icon="mdi-check"
            />
            <VAlert
              v-model="erroredChangePassword"
              closable
              variant="tonal"
              color="error"
              class="mb-4"
              :title="t('Auth.user.labels.changePassword.warningPasswordFormaTitle')"
              :text="t('Auth.user.message.changePasswordError')"
              icon="mdi-alert-remove-outline"
            />
          </div>
          <VForm @submit.prevent="handleChangePassword">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="newPassword"
                  :rules="passwordRules"
                  :label="t('Auth.user.labels.changePassword.newPassword')"
                  placeholder="············"
                  :type="isNewPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isNewPasswordVisible ? 'bx-hide' : 'bx-show'"
                  @click:append-inner="isNewPasswordVisible = !isNewPasswordVisible"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="confirmPassword"
                  :rules="[v.required(t('Auth.user.labels.changePassword.confirmPassword'))]"
                  :label="t('Auth.user.labels.changePassword.confirmPassword')"
                  autocomplete="confirm-password"
                  placeholder="············"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isConfirmPasswordVisible ? 'bx-hide' : 'bx-show'"
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                />
              </VCol>
            </VRow>

            <VBtn
              type="submit"
              class="mt-4"
            >
              {{ t('Auth.user.labels.changePassword.actionChangePassword') }}
            </VBtn>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <!-- � Roles -->

    <VCol cols="12">
      <VCard>
        <div class="d-flex align-center justify-space-between pe-4">
          <h4 class="v-card-title">
            {{ t('Auth.role.titlePlural') }}
          </h4>
          <VBtn
            size="small"
            type="icon"
            color="primary"
            @click="isSettingRoleDialogVisible = true"
          >
            <VIcon>mdi-synch-link</VIcon>
            {{ t('Auth.user.action.addOrRemoveRole') }}
          </VBtn>
        </div>

        <VDivider />
        <VDataTable
          v-if="roleUser !== null"
          :items="roleUser"
          :headers="roleHeaders"
          :loading="isLoadingRoles"
          hide-default-footer
          class="text-no-wrap"
        >
          <!-- TODO Refactor this after vuetify provides proper solution for removing default footer -->
          <template #bottom />
        </VDataTable>
      </VCard>
    </VCol>

    <VDialog
      v-model="isSettingRoleDialogVisible"
      persistent
      class="v-dialog-xl"
      transition="dialog-transition"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isSettingRoleDialogVisible = !isSettingRoleDialogVisible" />

      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pe-8">
          <h3 class="v-card-title ">
            Roles
          </h3>

          <VBtn
            color="success"
            size="small"
            :disabled="!selectedRoles.length"
            @click="setSelectedRoleToUser"
          >
            Valider
          </VBtn>
        </VCardTitle>
        <VCardText class="d-flex flex-wrap gap-3">
          <VDataTable
            v-model="selectedRoles"
            item-value="id"
            :items="roles"
            :headers="roleHeaders"
            show-select
            class="text-no-wrap"
          />
        </VCardText>
      </VCard>
    </VDialog>
  </VRow>
</template>
