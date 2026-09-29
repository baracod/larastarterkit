<script setup lang="ts">
import dayjs from 'dayjs'
import type { Publication } from '../types/cms'
import { useDocumentationContext } from '../stores/context'
import { cmsUrl, publicSiteUrl, useCms } from '../composables/useCms'
import CmsSegmented from './ui/CmsSegmented.vue'

const { t, api, busy, can, confirm, run } = useCms()
const context = useDocumentationContext()
const { edition, editionId } = storeToRefs(context)
const publications = ref<Publication[]>([])
const scope = ref<'edition' | 'all'>('edition')
const logItem = ref<Publication | null>(null)

const visible = computed(() => publications.value.filter(item => scope.value === 'all' || !editionId.value || item.edition_id === editionId.value || item.kind === 'restore'))
const pending = computed(() => publications.value.some(item => item.status === 'queued' || item.status === 'building'))
const lastSuccess = computed(() => publications.value.find(item => item.status === 'succeeded'))

const states: Record<Publication['status'], { color: string; icon: string }> = {
  queued: { color: 'secondary', icon: 'tabler-clock' },
  building: { color: 'info', icon: 'tabler-loader-2' },
  succeeded: { color: 'success', icon: 'tabler-circle-check' },
  failed: { color: 'error', icon: 'tabler-alert-circle' },
}

function duration(item: Publication) {
  if (!item.finished_at)
    return '—'
  const seconds = Math.max(0, dayjs(item.finished_at).diff(dayjs(item.created_at), 'second'))

  return seconds >= 60 ? `${Math.floor(seconds / 60)} min ${seconds % 60} s` : `${seconds} s`
}
function target(item: Publication) {
  if (item.kind === 'restore')
    return t('Documentation.entirePortal')

  return item.edition ? `${item.edition.collection?.title || ''} · ${item.edition.title}` : `#${item.edition_id}`
}
async function refresh() {
  publications.value = await api<Publication[]>(cmsUrl('publications'))
}

// Polls only while a build is queued or running.
const { pause, resume } = useIntervalFn(() => refresh().catch(() => {}), 4000, { immediate: false })

watch(pending, value => value ? resume() : pause(), { immediate: true })

async function publish() {
  if (!await confirm(t('Documentation.confirmPublish')))
    return
  await run(async () => {
    await api(cmsUrl(`editions/${editionId.value}/publish`), { method: 'POST' })
    await refresh()
  }, t('Documentation.queued'))
}
async function restore(item: Publication) {
  if (!await confirm(t('Documentation.confirmPortalRestore'), 'warning'))
    return
  await run(async () => {
    await api(cmsUrl(`publications/${item.id}/restore`), { method: 'POST' })
    await refresh()
  }, t('Documentation.queued'))
}

onMounted(() => run(async () => {
  await context.load()
  await refresh()
}))
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center gap-3 mb-6">
      <div class="page-title">
        <h1 class="text-h4 font-weight-bold">
          {{ t('Documentation.publications') }}
        </h1>
        <p class="text-medium-emphasis mb-0">
          {{ t('Documentation.publicationHint') }}
        </p>
      </div>
      <VSpacer />
      <VBtn
        :href="publicSiteUrl()"
        target="_blank"
        rel="noopener noreferrer"
        variant="text"
        prepend-icon="tabler-external-link"
      >
        {{ t('Documentation.publicSite') }}
      </VBtn>
      <VBtn
        v-if="can('publish') && edition"
        color="success"
        prepend-icon="tabler-rocket"
        :disabled="busy"
        data-test="publish-edition"
        @click="publish"
      >
        {{ t('Documentation.publish') }} · {{ edition.title }}
      </VBtn>
    </div>

    <VRow class="mb-2">
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="h-100">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              :color="pending ? 'info' : lastSuccess ? 'success' : 'secondary'"
              variant="tonal"
              size="48"
              rounded
            >
              <VIcon
                :icon="pending ? 'tabler-loader-2' : 'tabler-world'"
                :class="{ spin: pending }"
              />
            </VAvatar>
            <div>
              <div class="text-h6">
                {{ pending ? t('Documentation.buildRunning') : lastSuccess ? t('Documentation.siteOnline') : t('Documentation.siteNeverPublished') }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                <template v-if="lastSuccess">
                  {{ t('Documentation.lastPublished', { date: dayjs(lastSuccess.finished_at || lastSuccess.created_at).format('DD/MM/YYYY HH:mm') }) }}
                </template>
                <template v-else>
                  {{ t('Documentation.publishFirst') }}
                </template>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="h-100">
          <VCardText>
            <div class="text-subtitle-2 mb-2">
              {{ t('Documentation.howItWorks') }}
            </div>
            <ol class="publish-steps">
              <li>{{ t('Documentation.publishStep1') }}</li>
              <li>{{ t('Documentation.publishStep2') }}</li>
              <li>{{ t('Documentation.publishStep3') }}</li>
            </ol>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardItem>
        <VCardTitle>{{ t('Documentation.publicationHistory') }}</VCardTitle>
        <template #append>
          <div class="d-flex align-center gap-2">
            <CmsSegmented
              v-model="scope"
              size="small"
              :items="[{ value: 'edition', label: t('Documentation.thisEdition') }, { value: 'all', label: t('Documentation.filters.all') }]"
            />
            <VBtn
              icon="tabler-refresh"
              variant="text"
              size="small"
              :aria-label="t('Documentation.refresh')"
              :disabled="busy"
              @click="run(refresh)"
            />
          </div>
        </template>
      </VCardItem>
      <VProgressLinear
        :active="busy"
        indeterminate
      />
      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th>{{ t('Documentation.publication') }}</th>
            <th>{{ t('Documentation.target') }}</th>
            <th>{{ t('Documentation.status') }}</th>
            <th>{{ t('Documentation.author') }}</th>
            <th>{{ t('Documentation.duration') }}</th>
            <th class="text-end">
              {{ t('Documentation.rowActions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in visible"
            :key="item.id"
            :data-test="`publication-${item.id}`"
          >
            <td>
              <div class="font-weight-medium">
                #{{ item.id }}
                <VChip
                  v-if="item.kind === 'restore'"
                  size="x-small"
                  variant="tonal"
                  prepend-icon="tabler-restore"
                  class="ms-1"
                >
                  {{ t('Documentation.rollback') }}
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ dayjs(item.created_at).format('DD/MM/YYYY HH:mm') }}
              </div>
            </td>
            <td>{{ target(item) }}</td>
            <td>
              <VChip
                size="small"
                :color="states[item.status].color"
                variant="tonal"
                :prepend-icon="states[item.status].icon"
                :data-status="item.status"
              >
                {{ t(`Documentation.states.${item.status}`) }}
              </VChip>
            </td>
            <td>{{ item.author?.name || '—' }}</td>
            <td>{{ duration(item) }}</td>
            <td class="text-end">
              <VBtn
                size="small"
                variant="text"
                prepend-icon="tabler-file-text"
                @click="logItem = item"
              >
                {{ t('Documentation.log') }}
              </VBtn>
              <VBtn
                v-if="can('restore') && can('publish') && item.status === 'succeeded'"
                size="small"
                variant="text"
                prepend-icon="tabler-restore"
                :disabled="busy"
                @click="restore(item)"
              >
                {{ t('Documentation.restore') }}
              </VBtn>
            </td>
          </tr>
          <tr v-if="!visible.length">
            <td
              colspan="6"
              class="text-center text-medium-emphasis py-8"
            >
              {{ t('Documentation.noPublications') }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <VDialog
      :model-value="!!logItem"
      max-width="900"
      scrollable
      @update:model-value="logItem = null"
    >
      <VCard v-if="logItem">
        <VCardItem>
          <VCardTitle>{{ t('Documentation.log') }} · #{{ logItem.id }}</VCardTitle>
          <VCardSubtitle>{{ target(logItem) }}</VCardSubtitle>
        </VCardItem>
        <VCardText>
          <pre class="publication-log">{{ logItem.log || t('Documentation.noLog') }}</pre>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="logItem = null">
            {{ t('Documentation.close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.page-title { flex: 1 1 320px; min-width: 0; }
.publish-steps { margin: 0; padding-inline-start: 1.2rem; font-size: 14px; color: rgba(var(--v-theme-on-surface), .75); }
.publish-steps li + li { margin-top: 4px; }
.publication-log { max-height: 60vh; margin: 0; padding: 16px; overflow: auto; border-radius: 10px; background: #1e1f26; color: #d8dae3; font: 12px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
