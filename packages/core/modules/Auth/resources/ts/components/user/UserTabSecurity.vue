<script setup lang="ts">
import { useNotification } from '@/composables/useNotification'
import { RoleAPI } from '../../api/Role'
import { UserAPI } from '../../api/User'
import type { IRole, IUser } from '../../types/entities'
import { useAuthMailLanguage } from '@auth/composable/useAuthMailLanguage'
import AuthMailLanguageSelect from '@auth/components/AuthMailLanguageSelect.vue'
import { useAuthStore } from '@auth/stores'

const props = defineProps<{
  user: IUser
}>()

const emailLocale = useAuthMailLanguage()

const { t } = useI18n({ useScope: 'global' })
const { showNotification } = useNotification()

const auth = useAuthStore()
const canEditRoles = computed(() => auth.hasRole('administrator'))

const availableRoles = ref<IRole[]>([])
const selectedRoleIds = ref<number[]>(props.user.roles?.map(r => r.id) ?? [])
const rolesLoading = ref(false)

watch(() => props.user, newUser => {
  selectedRoleIds.value = newUser?.roles?.map(r => r.id) ?? []
}, { immediate: true })

const fetchRoles = async (): Promise<void> => {
  if (!canEditRoles.value)
    return
  try {
    availableRoles.value = await RoleAPI.getAll()
  }
  catch {
    // ignore
  }
}

const saveRoles = async (): Promise<void> => {
  if (!props.user.id || !canEditRoles.value)
    return

  rolesLoading.value = true
  try {
    await UserAPI.assignRoles(props.user.id, selectedRoleIds.value)
    showNotification({ type: 'success', message: 'Rôles mis à jour avec succès' })
  }
  catch {
    showNotification({ type: 'error', message: 'Erreur lors de la mise à jour des rôles' })
  }
  finally {
    rolesLoading.value = false
  }
}

onMounted(fetchRoles)

const formRef = ref()
const loading = ref(false)
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')

const liveChecks = computed(() => ({
  minLength: newPassword.value.length >= 8,
  mixedCase: /[a-z]/.test(newPassword.value) && /[A-Z]/.test(newPassword.value),
  hasNumber: /\d/.test(newPassword.value),
  hasSymbol: /[^A-Z0-9]/i.test(newPassword.value),
  confirmation: !!confirmPassword.value && newPassword.value === confirmPassword.value,
}))

const currentPasswordRules = [
  (value: string) => !!value || t('Auth.security.validation.currentPasswordRequired'),
]

const newPasswordRules = [
  (value: string) => !!value || t('Auth.security.validation.newPasswordRequired'),
  (value: string) => value.length >= 8 || t('Auth.security.validation.minLength'),
  (value: string) => (/[a-z]/.test(value) && /[A-Z]/.test(value)) || t('Auth.security.validation.mixedCase'),
  (value: string) => /\d/.test(value) || t('Auth.security.validation.number'),
  (value: string) => /[^A-Z0-9]/i.test(value) || t('Auth.security.validation.symbol'),
]

const confirmPasswordRules = [
  (value: string) => !!value || t('Auth.security.validation.confirmationRequired'),
  (value: string) => value === newPassword.value || t('Auth.security.validation.confirmationMismatch'),
]

const notifyUser = (type: 'success' | 'error', message: string) => {
  showNotification({ type, message })
}

const resetForm = () => {
  currentPassword.value = ''
  newPassword.value = ''
  confirmPassword.value = ''
}

const submit = async () => {
  const result = await formRef.value?.validate()

  if (!result?.valid) {
    notifyUser('error', t('Auth.security.feedback.formInvalid'))

    return
  }

  loading.value = true
  try {
    await UserAPI.changePassword({
      emailLocale: emailLocale.value,
      userId: props.user.id,
      currentPassword: currentPassword.value,
      newPassword: newPassword.value,
      newPasswordConfirmation: confirmPassword.value,
    })

    notifyUser('success', t('Auth.security.feedback.passwordUpdated'))
    resetForm()
    formRef.value?.resetValidation()
  }
  catch (error: any) {
    const backendMessage = String(error?.data?.message || error?.message || '').toLowerCase()

    let message = t('Auth.security.feedback.genericError')

    if (backendMessage.includes('actuel') || backendMessage.includes('current'))
      message = t('Auth.security.feedback.currentPasswordIncorrect')

    if (backendMessage.includes('confirmation'))
      message = t('Auth.security.feedback.confirmationMismatch')

    notifyUser('error', message)
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <!-- ─── Section : Rôles assignés ─── -->
  <VCard class="mb-6">
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="mdi-shield-account-outline" />
        Rôles assignés
      </VCardTitle>
      <VCardSubtitle>
        <template v-if="canEditRoles">
          Modifiez les rôles attribués à cet utilisateur.
        </template>
        <template v-else>
          Consultation des rôles — droits insuffisants pour modifier.
        </template>
      </VCardSubtitle>
    </VCardItem>

    <VCardText>
      <!-- Mode lecture seule -->
      <template v-if="!canEditRoles">
        <div
          v-if="user.roles?.length"
          class="d-flex gap-2 flex-wrap"
        >
          <VChip
            v-for="role in user.roles"
            :key="role.id"
            color="primary"
            variant="tonal"
            prepend-icon="mdi-shield-check"
          >
            {{ role.display_name || role.name }}
          </VChip>
        </div>
        <p
          v-else
          class="text-medium-emphasis mb-0"
        >
          Aucun rôle assigné.
        </p>
      </template>

      <!-- Mode édition -->
      <template v-else>
        <VSelect
          v-model="selectedRoleIds"
          :items="availableRoles"
          item-title="display_name"
          item-value="id"
          label="Rôles"
          multiple
          chips
          closable-chips
          class="mb-4"
        />
        <VBtn
          color="primary"
          :loading="rolesLoading"
          prepend-icon="mdi-content-save-outline"
          @click="saveRoles"
        >
          Enregistrer les rôles
        </VBtn>
      </template>
    </VCardText>
  </VCard>

  <!-- ─── Section : Sécurité / Mot de passe ─── -->
  <VCard
    :title="t('Auth.security.title')"
    :loading="loading"
  >
    <VCardText>
      <VAlert
        variant="tonal"
        color="warning"
        class="mb-4"
      >
        {{ t('Auth.security.description') }}
      </VAlert>

      <VForm
        ref="formRef"
        @submit.prevent="submit"
      >
        <AuthMailLanguageSelect v-model="emailLocale" />
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="currentPassword"
              :label="t('Auth.security.fields.currentPassword')"
              :rules="currentPasswordRules"
              :type="showCurrentPassword ? 'text' : 'password'"
              :append-inner-icon="showCurrentPassword ? 'bx-hide' : 'bx-show'"
              @click:append-inner="showCurrentPassword = !showCurrentPassword"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="newPassword"
              :label="t('Auth.security.fields.newPassword')"
              :rules="newPasswordRules"
              :type="showNewPassword ? 'text' : 'password'"
              :append-inner-icon="showNewPassword ? 'bx-hide' : 'bx-show'"
              @click:append-inner="showNewPassword = !showNewPassword"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="confirmPassword"
              :label="t('Auth.security.fields.confirmPassword')"
              :rules="confirmPasswordRules"
              :type="showConfirmPassword ? 'text' : 'password'"
              :append-inner-icon="showConfirmPassword ? 'bx-hide' : 'bx-show'"
              @click:append-inner="showConfirmPassword = !showConfirmPassword"
            />
          </VCol>
        </VRow>

        <VList
          density="compact"
          class="mb-4 rounded border"
        >
          <VListItem>
            <VListItemTitle class="text-subtitle-2">
              {{ t('Auth.security.validation.title') }}
            </VListItemTitle>
          </VListItem>
          <VListItem>
            <VIcon
              :icon="liveChecks.minLength ? 'bx-check-circle' : 'bx-x-circle'"
              :color="liveChecks.minLength ? 'success' : 'error'"
              class="me-2"
            />
            {{ t('Auth.security.validation.minLength') }}
          </VListItem>
          <VListItem>
            <VIcon
              :icon="liveChecks.mixedCase ? 'bx-check-circle' : 'bx-x-circle'"
              :color="liveChecks.mixedCase ? 'success' : 'error'"
              class="me-2"
            />
            {{ t('Auth.security.validation.mixedCase') }}
          </VListItem>
          <VListItem>
            <VIcon
              :icon="liveChecks.hasNumber ? 'bx-check-circle' : 'bx-x-circle'"
              :color="liveChecks.hasNumber ? 'success' : 'error'"
              class="me-2"
            />
            {{ t('Auth.security.validation.number') }}
          </VListItem>
          <VListItem>
            <VIcon
              :icon="liveChecks.hasSymbol ? 'bx-check-circle' : 'bx-x-circle'"
              :color="liveChecks.hasSymbol ? 'success' : 'error'"
              class="me-2"
            />
            {{ t('Auth.security.validation.symbol') }}
          </VListItem>
          <VListItem>
            <VIcon
              :icon="liveChecks.confirmation ? 'bx-check-circle' : 'bx-x-circle'"
              :color="liveChecks.confirmation ? 'success' : 'error'"
              class="me-2"
            />
            {{ t('Auth.security.validation.confirmationMatch') }}
          </VListItem>
        </VList>

        <div class="d-flex gap-2">
          <VBtn
            type="submit"
            color="primary"
            :loading="loading"
          >
            {{ t('Auth.security.actions.updatePassword') }}
          </VBtn>
          <VBtn
            type="button"
            variant="tonal"
            @click="resetForm"
          >
            {{ t('Auth.security.actions.reset') }}
          </VBtn>
        </div>
      </VForm>
    </VCardText>
  </VCard>
</template>
