<script setup lang="ts">
import { useDocumentationContext } from '../stores/context'

const context = useDocumentationContext()
const { t } = useI18n()
const opened = ref(false)

onMounted(() => context.load().catch(() => {}))
async function choose(doc: number, edition?: number) {
  await context.select(doc, edition)
  opened.value = false
}
</script>

<template>
  <VMenu
    v-model="opened"
    :close-on-content-click="false"
    max-width="420"
  >
    <template #activator="{ props }">
      <VBtn
        v-bind="props"
        variant="tonal"
        class="documentation-context mx-2 text-none"
        :disabled="context.loading || context.busy"
        :aria-label="t('Documentation.chooseContext')"
        append-icon="tabler-chevron-down"
      >
        <VIcon
          icon="tabler-book"
          class="me-2 d-none d-sm-inline"
        />
        <span class="text-truncate">{{ context.collection?.title || t('Documentation.chooseContext') }}<span
          v-if="context.edition"
          class="text-medium-emphasis"
        > · {{ context.edition.title }}</span></span>
      </VBtn>
    </template>
    <VCard min-width="280">
      <VCardTitle>{{ t('Documentation.chooseContext') }}</VCardTitle>
      <VAlert
        v-if="context.error"
        type="error"
      >
        {{ context.error }}
      </VAlert>
      <VList
        max-height="420"
        class="overflow-y-auto"
      >
        <template
          v-for="doc in context.collections"
          :key="doc.id"
        >
          <VListItem
            :active="doc.id === context.collectionId"
            :title="doc.title"
            :subtitle="doc.archived_at ? t('Documentation.archived') : t('Documentation.collection')"
            prepend-icon="tabler-book"
            @click="choose(doc.id)"
          />
          <VListItem
            v-for="edition in doc.editions"
            :key="edition.id"
            class="ps-10"
            :active="edition.id === context.editionId"
            :title="edition.title"
            :subtitle="edition.archived_at ? t('Documentation.archived') : undefined"
            @click="choose(doc.id, edition.id)"
          />
        </template>
        <VListItem
          v-if="!context.collections.length"
          :title="t('Documentation.noCollections')"
        />
      </VList>
    </VCard>
  </VMenu>
</template>

<style scoped>
.documentation-context { flex-shrink: 1; min-width: 0; max-width: 380px; }
.documentation-context :deep(.v-btn__content) { min-width: 0; }
@media (max-width: 600px) { .documentation-context { max-width: 145px; padding-inline: 8px; } }
</style>
