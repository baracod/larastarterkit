<script setup lang="ts">
import dayjs from 'dayjs'
import { useAbility } from '@casl/vue'
import { $api } from '@/utils/api'
import { useDocumentationContext } from '../stores/context'
import type { CmsImage, Page, Publication } from '../types/cms'

definePage({ meta: { action: 'browse', subject: 'documentation' } })

const { t } = useI18n()
const ability = useAbility()
const context = useDocumentationContext()
const { collectionId, editionId } = storeToRefs(context)
const pages = ref<Page[]>([])
const images = ref<CmsImage[]>([])
const publications = ref<Publication[]>([])
const loading = ref(false)
const error = ref('')
let requestId = 0
const ready = ref(false)
const publicUrl = JSON.parse(document.getElementById('starter-config')?.textContent || '{}').documentationUrl || '/docs/'
const recentPages = computed(() => [...pages.value].filter(page => !page.archived_at).sort((a, b) => b.current_revision_id - a.current_revision_id).slice(0, 6))
const recentPublications = computed(() => publications.value.filter(item => !editionId.value || item.edition_id === editionId.value || item.kind === 'restore').slice(0, 5))

const stats = computed(() => [
  { title: 'collectionsTitle', value: context.collections.filter(item => !item.archived_at).length, icon: 'tabler-books', path: '/documentation/collections', color: 'primary' },
  { title: 'contextEditions', value: context.collection?.editions.filter(item => !item.archived_at).length || 0, icon: 'tabler-versions', path: '/documentation/collections', color: 'info' },
  { title: 'contextPages', value: pages.value.filter(item => !item.archived_at).length, icon: 'tabler-file-text', path: '/documentation/editor', color: 'success' },
  { title: 'mediaLibrary', value: images.value.filter(item => !item.archived_at).length, icon: 'tabler-photo', path: '/documentation/media', color: 'warning' },
])

async function refresh() {
  const currentRequest = ++requestId

  loading.value = true
  error.value = ''
  pages.value = []
  images.value = []
  try {
    const [nextPages, nextImages, nextPublications] = await Promise.all([
      editionId.value ? $api<Page[]>(`documentation/editions/${editionId.value}/pages`) : Promise.resolve([]),
      collectionId.value ? $api<CmsImage[]>(`documentation/collections/${collectionId.value}/images`) : Promise.resolve([]),
      $api<Publication[]>('documentation/publications'),
    ])

    if (currentRequest !== requestId)
      return
    pages.value = nextPages
    images.value = nextImages
    publications.value = nextPublications
  }
  catch (failure: any) {
    if (currentRequest === requestId)
      error.value = failure.data?.message || t('Documentation.failed')
  }
  finally {
    if (currentRequest === requestId)
      loading.value = false
  }
}
watch([collectionId, editionId], () => {
  if (ready.value)
    refresh()
})
onMounted(async () => {
  try {
    await context.load()
    ready.value = true
    await refresh()
  }
  catch { error.value = context.error }
})
onBeforeUnmount(() => {
  requestId++
})
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-6">
      <div>
        <h1 class="text-h4">
          {{ t('Documentation.dashboard') }}
        </h1><p class="text-medium-emphasis mt-1">
          {{ t('Documentation.dashboardSubtitle') }}
        </p>
      </div>
      <VBtn
        :href="publicUrl"
        target="_blank"
        rel="noopener noreferrer"
        variant="outlined"
        prepend-icon="tabler-external-link"
      >
        {{ t('Documentation.publicSite') }}
      </VBtn>
    </div>
    <VAlert
      v-if="error"
      type="error"
      class="mb-4"
    >
      {{ error }}
    </VAlert>
    <VProgressLinear
      v-if="loading || context.loading"
      indeterminate
      class="mb-4"
    />
    <VCard
      class="documentation-welcome mb-6 pa-6"
      variant="tonal"
      color="primary"
    >
      <VRow align="center">
        <VCol
          cols="12"
          md="8"
        >
          <p class="text-overline mb-2">
            {{ t('Documentation.currentContext') }}
          </p>
          <h2 class="text-h4 mb-2">
            {{ context.collection?.title || t('Documentation.noCollections') }}<span
              v-if="context.edition"
              class="text-h6"
            > · {{ context.edition.title }}</span>
          </h2>
          <p class="mb-4">
            {{ context.collection?.description || t('Documentation.startHint') }}
          </p>
          <div class="d-flex flex-wrap gap-3">
            <VBtn
              v-if="context.edition"
              :to="context.location('/documentation/editor')"
              prepend-icon="tabler-writing"
            >
              {{ t('Documentation.openEditor') }}
            </VBtn>
            <VBtn
              :to="context.location('/documentation/collections')"
              variant="outlined"
            >
              {{ t('Documentation.manageCollections') }}
            </VBtn>
          </div>
        </VCol>
        <VCol
          cols="12"
          md="4"
          class="text-center d-none d-md-block"
        >
          <VIcon
            icon="tabler-book-2"
            size="110"
            class="opacity-70"
          />
        </VCol>
      </VRow>
    </VCard>
    <VRow class="mb-4">
      <VCol
        v-for="stat in stats"
        :key="stat.title"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard
          :to="context.location(stat.path)"
          class="h-100"
        >
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              :color="stat.color"
              variant="tonal"
              rounded
              size="52"
            >
              <VIcon
                :icon="stat.icon"
                size="28"
              />
            </VAvatar>
            <div>
              <p class="text-h4 mb-1">
                {{ stat.value }}
              </p><p class="mb-0">
                {{ t(`Documentation.${stat.title}`) }}
              </p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <VRow>
      <VCol
        cols="12"
        lg="7"
      >
        <VCard class="h-100">
          <VCardItem :title="t('Documentation.recentPages')">
            <template #append>
              <VBtn
                variant="text"
                :to="context.location('/documentation/editor')"
              >
                {{ t('Documentation.viewAll') }}
              </VBtn>
            </template>
          </VCardItem>
          <VList>
            <VListItem
              v-for="page in recentPages"
              :key="page.id"
              :to="context.location('/documentation/editor', { page: page.id })"
              :title="page.revision.title"
              :subtitle="dayjs(page.revision.created_at).format('DD/MM/YYYY HH:mm')"
              prepend-icon="tabler-file-text"
            >
              <template #append>
                <VIcon icon="tabler-chevron-right" />
              </template>
            </VListItem>
            <VListItem
              v-if="!recentPages.length"
              :title="t('Documentation.emptyPages')"
            />
          </VList>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        lg="5"
      >
        <VCard class="h-100">
          <VCardItem :title="t('Documentation.recentPublications')">
            <template #append>
              <VBtn
                variant="text"
                :to="context.location('/documentation/publications')"
              >
                {{ t('Documentation.viewAll') }}
              </VBtn>
            </template>
          </VCardItem>
          <VList>
            <VListItem
              v-for="publication in recentPublications"
              :key="publication.id"
              :value="publication.id"
              :to="context.location('/documentation/publications')"
              :title="`#${publication.id} · ${dayjs(publication.created_at).format('DD/MM/YYYY HH:mm')}`"
              :subtitle="publication.kind === 'restore' ? t('Documentation.entirePortal') : context.edition?.title"
            >
              <template #append>
                <VChip
                  size="small"
                  :color="publication.status === 'succeeded' ? 'success' : publication.status === 'failed' ? 'error' : 'warning'"
                >
                  {{ t(`Documentation.states.${publication.status}`) }}
                </VChip>
              </template>
            </VListItem>
            <VListItem
              v-if="!recentPublications.length"
              :title="t('Documentation.noPublications')"
            />
          </VList>
        </VCard>
      </VCol>
      <VCol cols="12">
        <VCard class="pa-5">
          <h2 class="text-h6 mb-4">
            {{ t('Documentation.quickActions') }}
          </h2>
          <div class="d-flex flex-wrap gap-3">
            <VBtn
              :to="context.location('/documentation/media')"
              variant="tonal"
              prepend-icon="tabler-photo"
            >
              {{ t('Documentation.mediaLibrary') }}
            </VBtn>
            <VBtn
              v-if="ability.can('publish', 'documentation')"
              :to="context.location('/documentation/publications')"
              variant="tonal"
              prepend-icon="tabler-cloud-upload"
            >
              {{ t('Documentation.publications') }}
            </VBtn>
            <VBtn
              :to="context.location('/documentation/configuration')"
              variant="tonal"
              prepend-icon="tabler-settings"
            >
              {{ t('Documentation.configuration') }}
            </VBtn>
          </div>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
