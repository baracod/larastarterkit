<script setup lang="ts">
import useNavigationStore from '@/stores'
import { useAuthStore } from '@auth/stores'

const { t, te } = useI18n()
const navigation = useNavigationStore()
const auth = useAuthStore()
const visible = ref(false)
const query = ref('')
const moduleLabel = (title: string) => te(`navigation.moduleLabels.${title}`) ? t(`navigation.moduleLabels.${title}`) : title
const items = computed(() => navigation.modules.filter(item => (auth.hasRole('administrator') || auth.can(item.action, item.subject)) && [moduleLabel(item.title), item.title].some(label => label.toLowerCase().includes(query.value.trim().toLowerCase()))))

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
            :title="moduleLabel(item.title)"
            :prepend-icon="item.icon"
            @click="visible = false"
          />
        </VList>
        <p
          v-if="!items.length"
          class="text-medium-emphasis mb-0"
        >
          {{ t('starter.noResults') }}
        </p>
      </VCardText>
    </VCard>
  </VDialog>
</template>
