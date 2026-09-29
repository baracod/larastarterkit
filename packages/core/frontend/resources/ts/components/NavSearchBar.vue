<script setup lang="ts">
import useNavigationStore from '@/stores'
import { useAuthStore } from '@auth/stores'

const { t } = useI18n()
const navigation = useNavigationStore()
const auth = useAuthStore()
const visible = ref(false)
const query = ref('')
const items = computed(() => navigation.modules.filter(item => (auth.hasRole('administrator') || auth.can(item.action, item.subject)) && t(`navigation.moduleLabels.${item.title}`).toLowerCase().includes(query.value.toLowerCase())))

useEventListener(document, 'keydown', (event: KeyboardEvent) => {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    visible.value = !visible.value
  }
})
</script>

<template>
  <VBtn
    variant="text"
    icon="mdi-magnify"
    :aria-label="t('starter.search')"
    @click="visible = true"
  />
  <VDialog
    v-model="visible"
    max-width="600"
  >
    <VCard :title="t('starter.search')">
      <VCardText>
        <VTextField
          v-model="query"
          :label="t('starter.search')"
          autofocus
        />
        <VList>
          <VListItem
            v-for="item in items"
            :key="item.title"
            :to="item.to"
            :title="t(`navigation.moduleLabels.${item.title}`)"
            :prepend-icon="item.icon"
            @click="visible = false"
          />
        </VList>
      </VCardText>
    </VCard>
  </VDialog>
</template>
