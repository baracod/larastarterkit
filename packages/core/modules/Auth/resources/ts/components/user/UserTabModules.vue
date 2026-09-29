<script setup lang="ts">
import { UserAPI } from '../../api/User'
import { useAuthStore } from '@auth/stores'

const props = defineProps<{ userId: number }>()
const { t } = useI18n()
const auth = useAuthStore()
const text = ref('[]')
const error = ref('')
const busy = ref(false)
async function load() {
  try {
    const payload = await UserAPI.getUserModules(props.userId)

    text.value = JSON.stringify(payload.settings, null, 2)
  }
  catch (cause: any) { error.value = cause?.data?.message ?? t('starter.error') }
}
async function save() {
  busy.value = true
  error.value = ''
  try {
    const settings = JSON.parse(text.value)

    text.value = JSON.stringify((await UserAPI.updateUserModules(props.userId, settings)).settings, null, 2)
  }
  catch (cause: any) { error.value = cause?.data?.message ?? t('starter.invalidSettings') }
  finally { busy.value = false }
}
watch(() => props.userId, load, { immediate: true })
</script>

<template>
  <VCard :title="t('starter.moduleSettings')">
    <VCardText>
      <VAlert
        v-if="error"
        type="error"
        class="mb-4"
      >
        {{ error }}
      </VAlert>
      <VTextarea
        v-model="text"
        :label="t('starter.moduleSettings')"
        :hint="t('starter.moduleSettingsHelp')"
        persistent-hint
        :readonly="!auth.hasRole('administrator')"
        rows="12"
      />
      <VBtn
        v-if="auth.hasRole('administrator')"
        class="mt-4"
        :loading="busy"
        @click="save"
      >
        {{ t('action.save') }}
      </VBtn>
    </VCardText>
  </VCard>
</template>
