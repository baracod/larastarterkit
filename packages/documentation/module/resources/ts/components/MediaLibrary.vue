<script setup lang="ts">
import dayjs from 'dayjs'
import type { CmsImage } from '../types/cms'
import { useDocumentationContext } from '../stores/context'
import { cmsUrl, imageUrl, useCms } from '../composables/useCms'
import CmsSegmented from './ui/CmsSegmented.vue'

const { t, api, busy, can, confirm, run, notify } = useCms()
const context = useDocumentationContext()
const { collection, collectionId } = storeToRefs(context)
const images = ref<CmsImage[]>([])
const search = ref('')
const filter = ref<'all' | 'used' | 'unused' | 'archived'>('all')
const sort = ref<'recent' | 'name' | 'size'>('recent')
const selected = ref<CmsImage | null>(null)
const details = reactive({ name: '', alt: '' })
const queue = ref<{ name: string; state: 'pending' | 'done' | 'error'; message?: string }[]>([])
const input = ref<HTMLInputElement>()
const drop = ref<HTMLElement>()
const canUpload = computed(() => can('media') && !!collection.value && !collection.value.archived_at)
const { isOverDropZone } = useDropZone(drop, files => files?.length && upload(files))

const visible = computed(() => {
  const term = search.value.trim().toLowerCase()

  const items = images.value.filter(image => {
    if (filter.value === 'archived' ? !image.archived_at : image.archived_at)
      return false
    if (filter.value === 'used' && !image.usage?.length)
      return false
    if (filter.value === 'unused' && image.usage?.length)
      return false

    return `${image.name} ${image.alt || ''}`.toLowerCase().includes(term)
  })

  return items.sort((a, b) => sort.value === 'name' ? a.name.localeCompare(b.name) : sort.value === 'size' ? (b.size || 0) - (a.size || 0) : b.id - a.id)
})

const totals = computed(() => {
  const active = images.value.filter(image => !image.archived_at)

  return { count: active.length, size: active.reduce((sum, image) => sum + (image.size || 0), 0), unused: active.filter(image => !image.usage?.length).length }
})

function formatSize(bytes = 0) {
  return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} Mo` : `${Math.max(1, Math.round(bytes / 1024))} Ko`
}
async function load() {
  images.value = collectionId.value ? await api<CmsImage[]>(cmsUrl(`collections/${collectionId.value}/images`)) : []
  if (selected.value)
    selected.value = images.value.find(image => image.id === selected.value!.id) || null
}
async function upload(files: File[]) {
  if (!canUpload.value)
    return
  const accepted = files.filter(file => /^image\/(?:png|jpeg|webp|gif)$/.test(file.type))
  if (accepted.length < files.length)
    notify({ type: 'warning', message: t('Documentation.unsupportedFiles') })
  queue.value = accepted.map(file => ({ name: file.name, state: 'pending' }))
  for (const [index, file] of accepted.entries()) {
    const body = new FormData()

    body.append('image', file)
    body.append('alt', file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' '))
    try {
      await api(cmsUrl(`collections/${collectionId.value}/images`), { method: 'POST', body })
      queue.value[index].state = 'done'
    }
    catch (failure: any) {
      queue.value[index] = { name: file.name, state: 'error', message: Object.values(failure?.data?.errors || {}).flat().join(' ') || t('Documentation.failed') }
    }
  }
  await run(load)
  if (queue.value.every(item => item.state === 'done')) {
    notify({ type: 'success', message: t('Documentation.uploaded', { count: accepted.length }) })
    queue.value = []
  }
}
function open(image: CmsImage) {
  selected.value = image
  Object.assign(details, { name: image.name, alt: image.alt || '' })
}
async function saveDetails() {
  if (!selected.value)
    return
  const done = await run(() => api(cmsUrl(`images/${selected.value!.id}`), { method: 'PATCH', body: details }), t('Documentation.mediaSaved'))
  if (done)
    await run(load)
}
async function archive(image: CmsImage) {
  const message = !image.archived_at && image.usage?.length ? t('Documentation.confirmArchiveUsedImage', { count: image.usage.length }) : t(image.archived_at ? 'Documentation.confirmUnarchive' : 'Documentation.confirmArchive')
  if (!await confirm(message, 'warning'))
    return
  await run(async () => {
    await api(cmsUrl(`images/${image.id}/archive`), { method: 'PATCH', body: { archived: !image.archived_at } })
    await load()
  }, t(image.archived_at ? 'Documentation.unarchived' : 'Documentation.archivedDone'))
}
async function copySnippet(image: CmsImage) {
  try {
    await navigator.clipboard.writeText(`![${(image.alt || image.name).replace(/[[\]\\]/g, '')}](doc-image:${image.id})`)
    notify({ type: 'success', message: t('Documentation.snippetCopied') })
  }
  catch {
    notify({ type: 'error', message: t('Documentation.clipboardUnavailable') })
  }
}

watch(collectionId, () => run(load))
onMounted(() => run(async () => {
  await context.load()
  await load()
}))
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center gap-3 mb-6">
      <div class="page-title">
        <h1 class="text-h4 font-weight-bold">
          {{ t('Documentation.mediaLibrary') }}
        </h1>
        <p class="text-medium-emphasis mb-0">
          {{ collection ? t('Documentation.mediaSubtitle', { title: collection.title }) : t('Documentation.chooseContext') }}
        </p>
      </div>
      <VSpacer />
      <VBtn
        v-if="canUpload"
        prepend-icon="tabler-upload"
        :disabled="busy"
        @click="input?.click()"
      >
        {{ t('Documentation.upload') }}
      </VBtn>
      <input
        ref="input"
        type="file"
        accept="image/png,image/jpeg,image/webp,image/gif"
        multiple
        hidden
        data-test="media-input"
        @change="upload(Array.from(($event.target as HTMLInputElement).files || [])); ($event.target as HTMLInputElement).value = ''"
      >
    </div>

    <VRow class="mb-2">
      <VCol
        v-for="stat in [
          { icon: 'tabler-photo', color: 'primary', value: totals.count, label: t('Documentation.mediaCount') },
          { icon: 'tabler-database', color: 'info', value: formatSize(totals.size), label: t('Documentation.mediaWeight') },
          { icon: 'tabler-unlink', color: 'warning', value: totals.unused, label: t('Documentation.mediaUnused') },
        ]"
        :key="stat.label"
        cols="12"
        sm="4"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              :color="stat.color"
              variant="tonal"
              rounded
            >
              <VIcon :icon="stat.icon" />
            </VAvatar>
            <div>
              <div class="text-h5">
                {{ stat.value }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ stat.label }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div
      v-if="canUpload"
      ref="drop"
      class="media-drop mb-5"
      :class="{ 'is-over': isOverDropZone }"
      role="button"
      tabindex="0"
      @click="input?.click()"
      @keydown.enter="input?.click()"
    >
      <VIcon
        icon="tabler-cloud-upload"
        size="36"
        color="primary"
      />
      <div>
        <strong>{{ t('Documentation.dropImages') }}</strong>
        <p class="mb-0 text-body-2 text-medium-emphasis">
          {{ t('Documentation.imageHint') }}
        </p>
      </div>
    </div>
    <VList
      v-if="queue.length"
      density="compact"
      class="mb-4 rounded border"
    >
      <VListItem
        v-for="item in queue"
        :key="item.name"
        :title="item.name"
        :subtitle="item.message"
      >
        <template #prepend>
          <VProgressCircular
            v-if="item.state === 'pending'"
            indeterminate
            size="18"
            width="2"
            class="me-3"
          />
          <VIcon
            v-else
            :icon="item.state === 'done' ? 'tabler-circle-check' : 'tabler-alert-circle'"
            :color="item.state === 'done' ? 'success' : 'error'"
          />
        </template>
      </VListItem>
    </VList>

    <div class="d-flex flex-wrap align-center gap-3 mb-4">
      <VTextField
        v-model="search"
        density="compact"
        prepend-inner-icon="tabler-search"
        :placeholder="t('Documentation.searchMedia')"
        clearable
        hide-details
        style="max-width: 320px"
      />
      <CmsSegmented
        v-model="filter"
        :items="(['all', 'used', 'unused', 'archived'] as const).map(value => ({ value, label: t(`Documentation.filters.${value}`) }))"
      />
      <VSpacer />
      <VSelect
        v-model="sort"
        density="compact"
        hide-details
        style="max-width: 200px"
        prepend-inner-icon="tabler-arrows-sort"
        :items="[{ title: t('Documentation.sorts.recent'), value: 'recent' }, { title: t('Documentation.sorts.name'), value: 'name' }, { title: t('Documentation.sorts.size'), value: 'size' }]"
      />
    </div>
    <VProgressLinear
      :active="busy"
      indeterminate
      class="mb-3"
    />

    <div class="media-grid">
      <button
        v-for="image in visible"
        :key="image.id"
        type="button"
        class="media-card"
        :class="{ 'is-selected': selected?.id === image.id, 'is-archived': !!image.archived_at }"
        :data-test="`media-${image.id}`"
        @click="open(image)"
      >
        <span class="media-thumb">
          <img
            :src="imageUrl(image.id)"
            :alt="image.alt || image.name"
            loading="lazy"
          >
          <VChip
            v-if="image.usage?.length"
            size="x-small"
            color="success"
            variant="elevated"
            class="media-badge"
          >
            {{ t('Documentation.usedIn', { count: image.usage.length }) }}
          </VChip>
        </span>
        <span class="media-name">{{ image.name }}</span>
        <span class="media-meta">{{ image.width }}×{{ image.height }} · {{ formatSize(image.size) }}</span>
      </button>
    </div>
    <VCard
      v-if="!visible.length && !busy"
      variant="outlined"
      class="text-center pa-10 media-empty"
    >
      <VIcon
        icon="tabler-photo-off"
        size="48"
        class="mb-3"
      />
      <p class="mb-0">
        {{ t('Documentation.noMedia') }}
      </p>
    </VCard>

    <VNavigationDrawer
      :model-value="!!selected"
      location="end"
      temporary
      width="420"
      @update:model-value="!$event && (selected = null)"
    >
      <template v-if="selected">
        <div class="d-flex align-center pa-4">
          <h2 class="text-h6">
            {{ t('Documentation.mediaDetails') }}
          </h2>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            :aria-label="t('Documentation.close')"
            @click="selected = null"
          />
        </div>
        <div class="px-4 pb-6">
          <a
            :href="imageUrl(selected.id)"
            target="_blank"
            rel="noopener noreferrer"
            class="d-block mb-4"
          >
            <img
              :src="imageUrl(selected.id)"
              :alt="selected.alt || selected.name"
              class="media-preview"
            >
          </a>
          <dl class="media-facts mb-4">
            <dt>{{ t('Documentation.dimensions') }}</dt><dd>{{ selected.width }} × {{ selected.height }} px</dd>
            <dt>{{ t('Documentation.weight') }}</dt><dd>{{ formatSize(selected.size) }}</dd>
            <dt>{{ t('Documentation.format') }}</dt><dd>{{ selected.mime }}</dd>
            <dt>{{ t('Documentation.addedOn') }}</dt><dd>{{ selected.created_at ? dayjs(selected.created_at).format('DD/MM/YYYY HH:mm') : '—' }}</dd>
            <dt>{{ t('Documentation.reference') }}</dt><dd><code>doc-image:{{ selected.id }}</code></dd>
          </dl>
          <VTextField
            v-model="details.name"
            :label="t('Documentation.fileName')"
            :readonly="!can('media')"
            density="compact"
            class="mb-3"
          />
          <VTextarea
            v-model="details.alt"
            :label="t('Documentation.altText')"
            :hint="t('Documentation.altHint')"
            persistent-hint
            :readonly="!can('media')"
            rows="2"
            auto-grow
            density="compact"
            class="mb-4"
            data-test="media-alt"
          />
          <div class="d-flex flex-wrap gap-2 mb-6">
            <VBtn
              v-if="can('media')"
              color="primary"
              :disabled="busy || !details.name"
              data-test="media-save"
              @click="saveDetails"
            >
              {{ t('Documentation.save') }}
            </VBtn>
            <VBtn
              variant="tonal"
              prepend-icon="tabler-copy"
              @click="copySnippet(selected)"
            >
              Markdown
            </VBtn>
            <VBtn
              v-if="can('media')"
              variant="text"
              :color="selected.archived_at ? undefined : 'error'"
              :prepend-icon="selected.archived_at ? 'tabler-archive-off' : 'tabler-archive'"
              :disabled="busy"
              @click="archive(selected)"
            >
              {{ t(selected.archived_at ? 'Documentation.unarchive' : 'Documentation.archive') }}
            </VBtn>
          </div>
          <h3 class="text-subtitle-1 font-weight-medium mb-2">
            {{ t('Documentation.usage') }}
          </h3>
          <VList
            v-if="selected.usage?.length"
            density="compact"
          >
            <VListItem
              v-for="use in selected.usage"
              :key="use.page_id"
              :title="use.title"
              :subtitle="use.edition || ''"
              prepend-icon="tabler-file-text"
              :to="{ path: '/documentation/editor', query: { doc: collectionId, edition: use.edition_id, page: use.page_id } }"
            />
          </VList>
          <p
            v-else
            class="text-body-2 text-medium-emphasis"
          >
            {{ t('Documentation.notUsed') }}
          </p>
        </div>
      </template>
    </VNavigationDrawer>
  </div>
</template>

<style scoped>
.page-title { flex: 1 1 320px; min-width: 0; }
.media-drop { display: flex; align-items: center; justify-content: center; gap: 16px; padding: 28px; border: 2px dashed rgba(var(--v-border-color), .3); border-radius: 14px; cursor: pointer; transition: border-color .2s, background .2s; }
.media-drop:hover, .media-drop.is-over, .media-drop:focus-visible { border-color: rgb(var(--v-theme-primary)); background: rgba(var(--v-theme-primary), .05); outline: none; }
.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
.media-card { display: flex; flex-direction: column; gap: 4px; padding: 8px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 12px; background: rgb(var(--v-theme-surface)); text-align: start; transition: border-color .2s, transform .2s, box-shadow .2s; }
.media-card:hover, .media-card:focus-visible { transform: translateY(-2px); border-color: rgb(var(--v-theme-primary)); box-shadow: 0 8px 24px -12px rgba(var(--v-theme-primary), .6); outline: none; }
.media-card.is-selected { border-color: rgb(var(--v-theme-primary)); box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), .2); }
.media-card.is-archived { opacity: .6; }
.media-thumb { position: relative; display: block; overflow: hidden; border-radius: 8px; aspect-ratio: 4 / 3; background: repeating-conic-gradient(rgba(var(--v-theme-on-surface), .06) 0% 25%, transparent 0% 50%) 50% / 16px 16px; }
.media-thumb img { width: 100%; height: 100%; object-fit: cover; }
.media-badge { position: absolute; top: 6px; inset-inline-end: 6px; }
.media-name { overflow: hidden; margin-top: 4px; font-size: 13px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.media-meta { font-size: 12px; color: rgba(var(--v-theme-on-surface), .55); }
.media-empty { border-style: dashed; color: rgba(var(--v-theme-on-surface), .6); }
.media-preview { width: 100%; max-height: 260px; object-fit: contain; border-radius: 10px; background: repeating-conic-gradient(rgba(var(--v-theme-on-surface), .06) 0% 25%, transparent 0% 50%) 50% / 16px 16px; }
.media-facts { display: grid; grid-template-columns: auto 1fr; gap: 6px 12px; font-size: 13px; }
.media-facts dt { color: rgba(var(--v-theme-on-surface), .6); }
.media-facts dd { margin: 0; text-align: end; overflow-wrap: anywhere; }
</style>
