<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import User from '../../api/User'
import type { IBioEditable, IUser } from '../../types/entities'

const props = withDefaults(defineProps<Props>(), {
  userData: () => ({
    id: 0,
    name: '',
    username: '',
    email: '',
    active: 1,
    avatar: null,
    avatarFile: null,
  } satisfies IBioEditable),
})

const emit = defineEmits<Emits>()

const { t } = useI18n()

/* ------------------------------------------------------------------
 * Props & Emits
 * ---------------------------------------------------------------- */
interface Props {
  userData?: IBioEditable
  isDialogVisible: boolean
}

interface Emits {
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'profileUpdated'): void
}

/* ------------------------------------------------------------------
 * Local state
 * ---------------------------------------------------------------- */
const userData = ref<IUser | IBioEditable>({ ...(props.userData as IUser) })
const extraInfoStr = ref<string>('')
const avatarFile = ref<File | null>(null)
const avatarUrl = ref<string | null>(props.userData.avatar ?? null)
let blobUrl: string | null = null
const formattedErrors = ref<Record<string, string>>({})
const isLoading = ref(false)

/* ------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------- */
const { formatErrorMessages } = useTranslater()

const revokeBlob = () => {
  if (blobUrl)
    URL.revokeObjectURL(blobUrl)
  blobUrl = null
}

const setPreview = (file: File | null) => {
  revokeBlob()
  if (file) {
    blobUrl = URL.createObjectURL(file)
    avatarUrl.value = blobUrl
  }
  else {
    avatarUrl.value = props.userData.avatar ?? null
  }
}

/* ------------------------------------------------------------------
 * Computed
 * ---------------------------------------------------------------- */
const initials = computed(() => {
  const parts = (userData.value.name ?? '').trim().split(/\s+/)

  return parts.length
    ? (parts[0][0] + (parts.at(-1) ?? '')[0]).toUpperCase()
    : ''
})

/* ------------------------------------------------------------------
 * Watches
 * ---------------------------------------------------------------- */
watch(avatarFile, file => setPreview(file))
watch(() => props.userData, newVal => {
  userData.value = { ...(newVal as IUser) }
  avatarFile.value = null
  setPreview(null)
}, { immediate: true })

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible)
    formattedErrors.value = {}
})

/* ------------------------------------------------------------------
 * Handlers
 * ---------------------------------------------------------------- */
const closeDialog = (val = false) => emit('update:isDialogVisible', val)
const clearAvatar = () => setPreview(avatarFile.value = null)

const submit = async () => {
  if (!props.userData?.id)
    return

  isLoading.value = true
  formattedErrors.value = {}

  try {
    await User.updateProfile(+props.userData.id, userData.value as IBioEditable, avatarFile.value ?? undefined)

    emit('profileUpdated')
    closeDialog(false)
  }
  catch (e: any) {
    console.log('Error updating profile:', e.data)
    if (e.data) {
      const errors = e.data.errors

      formattedErrors.value = formatErrorMessages(errors, 'user')
    }
    else {
      alert('An unexpected error occurred.')
      console.error(e)
    }
  }
  finally {
    isLoading.value = false
  }
}

const reset = () => {
  clearAvatar()
  closeDialog(false)
}

onBeforeUnmount(revokeBlob)

/* ------------------------------------------------------------------
 * Select options
 * ---------------------------------------------------------------- */
const statusItems = [
  { title: 'Active', value: 1 },
  { title: 'Inactive', value: 0 },
]
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="closeDialog"
  >
    <DialogCloseBtn @click="closeDialog(false)" />

    <VCard
      class="pa-sm-10 pa-2"
      :loading="isLoading"
    >
      <VCardText>
        <h4 class="text-h4 text-center mb-2">
          {{ t('action.edit') }} {{ t('Auth.user.title') }}
        </h4>

        <VForm
          class="mt-6"
          @submit.prevent="submit"
        >
          <VRow>
            <!-- Avatar & FileInput -->
            <VCol cols="12">
              <div
                class="d-flex flex-column align-center text-center mb-5"
                style="gap: 12px;"
              >
                <VAvatar
                  v-if="avatarUrl"
                  size="140"
                  :image="avatarUrl"
                />
                <VAvatar
                  v-else
                  size="140"
                  color="primary"
                >
                  <span class="text-h5">{{ initials }}</span>
                </VAvatar>

                <VFileInput
                  v-model="avatarFile"
                  :label="t('Auth.user.field.avatar')"
                  accept="image/*"
                  prepend-icon=""
                  prepend-inner-icon="bx-photo-album"
                  clearable
                  class="w-100"
                  style="max-inline-size: 320px;"
                  :error-messages="formattedErrors.avatarFile"
                  @click:clear="clearAvatar"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="userData.name"
                :label="t('Auth.user.field.name')"
                :placeholder="t('Auth.user.field.name')"
                :error-messages="formattedErrors.name"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="userData.username"
                :label="t('Auth.user.field.username')"
                :placeholder="t('Auth.user.field.username')"
                :error-messages="formattedErrors.username"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="userData.email"
                :label="t('Auth.user.field.email')"
                :placeholder="t('Auth.user.field.email')"
                :error-messages="formattedErrors.email"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="(userData as any).active"
                :label="t('Auth.user.field.active')"
                placeholder="Select"
                :items="statusItems"
                :error-messages="formattedErrors.active"
              />
            </VCol>

            <VCol
              cols="12"
              class="d-flex flex-wrap justify-center gap-4"
            >
              <VBtn
                type="submit"
                :loading="isLoading"
              >
                {{ t('action.submit') }}
              </VBtn>
              <VBtn
                color="secondary"
                variant="tonal"
                @click="reset"
              >
                {{ t('action.cancel') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
