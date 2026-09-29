<script setup lang="ts">
import dayjs from 'dayjs'
import { useDisplay } from 'vuetify'
import type { CmsImage, Page, Revision } from '../types/cms'
import { useDocumentationContext } from '../stores/context'
import { cmsUrl, publicSiteUrl, useCms } from '../composables/useCms'
import { isValidPath, outline, slugify, textStats } from '../editor/text'
import { movePage } from '../editor/tree'
import type { TreeMove } from '../editor/tree'
import CmsSegmented from './ui/CmsSegmented.vue'
import DocumentationEditor from './DocumentationEditor.vue'
import MarkdownPreview from './MarkdownPreview.vue'
import MediaPicker from './MediaPicker.vue'
import PageTree from './PageTree.vue'
import RevisionPanel from './RevisionPanel.vue'

interface Draft { form: typeof form; base: number | null; at: string }

const { t, api, busy, can, confirm, run, notify } = useCms()
const context = useDocumentationContext()
const { collection, edition, editionId, collectionId } = storeToRefs(context)
const route = useRoute()
const display = useDisplay()
const router = useRouter()

const pages = ref<Page[]>([])
const images = ref<CmsImage[]>([])
const revisions = ref<Revision[]>([])
const selectedPage = ref<Page | null>(null)
const form = reactive({ title: '', path: '', markdown: '', parent_id: null as number | null, position: 0 })
const note = ref('')
const saved = ref('')
const savedAt = ref<string | null>(null)
const ready = ref(false)
const editorKey = ref(0)
const editor = ref<InstanceType<typeof DocumentationEditor>>()
const editorMode = useLocalStorage<'visual' | 'markdown'>('documentation-editor-mode', 'visual')
const view = useLocalStorage<'write' | 'split' | 'preview'>('documentation-editor-view', 'write')
const side = useLocalStorage<'outline' | 'history' | 'info' | ''>('documentation-editor-side', 'outline')
const treeOpen = useLocalStorage('documentation-editor-tree', true)
const focusMode = ref(false)
const settingsOpen = ref(false)
const html = ref('')
const previewing = ref(false)
const pickerOpen = ref(false)
const uploading = ref(false)
const recovered = ref<Draft | null>(null)
const codeTheme = ref('auto')
const createDialog = ref(false)
const creation = reactive({ title: '', path: '', parent_id: null as number | null, pathTouched: false })

// Split view needs room for two readable columns: side panels give way when the screen is too narrow.
const showTree = computed(() => treeOpen.value && (view.value !== 'split' || display.width.value >= 1700))
const showSide = computed(() => !!side.value && (view.value !== 'split' || display.width.value >= 2000))

/** Opening a panel that split view hid for lack of room switches back to the writing view. */
function togglePanel(panel: 'tree' | 'side') {
  const visible = panel === 'tree' ? showTree.value : showSide.value
  if (panel === 'tree')
    treeOpen.value = !visible
  else
    side.value = visible ? '' : (side.value || 'outline')
  if (!visible && view.value === 'split' && !(panel === 'tree' ? showTree.value : showSide.value))
    view.value = 'write'
}
const editable = computed(() => !!edition.value && !edition.value.archived_at && !collection.value?.archived_at && can('edit'))
const locked = computed(() => busy.value || !editable.value || !!selectedPage.value?.archived_at)
const dirty = computed(() => !!selectedPage.value && JSON.stringify(form) !== saved.value)
const stats = computed(() => textStats(form.markdown))
const headings = computed(() => outline(form.markdown))
const parentOptions = computed(() => pages.value.filter(page => !page.archived_at && page.id !== selectedPage.value?.id).map(page => ({ title: page.revision.title, value: page.id })))
const pathError = computed(() => form.path && !isValidPath(form.path) ? t('Documentation.invalidPath') : '')
const pagePublicUrl = computed(() => collection.value && edition.value && form.path ? `${publicSiteUrl().replace(/\/$/, '')}/${collection.value.slug}/${edition.value.slug}/${form.path}.html` : '')

const usedImages = computed(() => {
  const ids = [...form.markdown.matchAll(/doc-image:(\d+)/g)].map(match => Number(match[1]))

  return images.value.filter(image => ids.includes(image.id))
})

const draftKey = computed(() => selectedPage.value ? `documentation-draft:${editionId.value}:${selectedPage.value.id}` : '')

function readDraft(key: string): Draft | null {
  try {
    return JSON.parse(localStorage.getItem(key) || 'null')
  }
  catch {
    return null
  }
}
function clearDraft() {
  try {
    draftKey.value && localStorage.removeItem(draftKey.value)
  }
  catch { /* Storage can be unavailable in private windows. */ }
}

const storeDraft = useDebounceFn(() => {
  if (!dirty.value || !draftKey.value)
    return
  try {
    localStorage.setItem(draftKey.value, JSON.stringify({ form, base: selectedPage.value?.current_revision_id ?? null, at: new Date().toISOString() }))
  }
  catch { /* Storage can be unavailable in private windows. */ }
}, 800)

watch(form, storeDraft, { deep: true })

const refreshPreview = useDebounceFn(async () => {
  if (!collectionId.value || !selectedPage.value)
    return
  previewing.value = true
  try {
    html.value = (await api<{ html: string }>(cmsUrl(`collections/${collectionId.value}/preview`), { method: 'POST', body: { markdown: form.markdown } })).html
  }
  catch (failure: any) {
    html.value = ''
    notify({ type: 'error', message: Object.values(failure?.data?.errors || {}).flat().join(' ') || t('Documentation.failed') })
  }
  finally {
    previewing.value = false
  }
}, 600)

async function loadPages() {
  pages.value = editionId.value ? await api<Page[]>(cmsUrl(`editions/${editionId.value}/pages`)) : []
}
async function loadImages() {
  images.value = collectionId.value ? await api<CmsImage[]>(cmsUrl(`collections/${collectionId.value}/images`)) : []
}
async function loadEdition() {
  selectedPage.value = null
  revisions.value = []
  html.value = ''
  await Promise.all([loadPages(), loadImages()])

  const requested = Number(route.query.page)

  const page = pages.value.find(item => item.id === requested)
    ?? (route.query.page ? undefined : pages.value.find(item => !item.archived_at && item.revision.parent_id === null))

  if (page)
    await selectPage(page)
  if (route.query.page === 'new')
    openCreate(null)
}

async function selectPage(page: Page) {
  editorKey.value++
  selectedPage.value = page
  Object.assign(form, { title: page.revision.title, path: page.revision.path, markdown: page.revision.markdown, parent_id: page.revision.parent_id, position: page.revision.position })
  saved.value = JSON.stringify(form)
  savedAt.value = page.revision.created_at
  note.value = ''
  settingsOpen.value = false
  recovered.value = null

  const draft = readDraft(draftKey.value)
  if (draft && JSON.stringify(draft.form) !== saved.value)
    recovered.value = draft
  revisions.value = await api<Revision[]>(cmsUrl(`pages/${page.id}/revisions`))
  if (view.value !== 'write')
    refreshPreview()
}
function restoreDraft() {
  if (!recovered.value)
    return
  Object.assign(form, recovered.value.form)
  editorKey.value++
  if (recovered.value.base !== selectedPage.value?.current_revision_id)
    notify({ type: 'warning', message: t('Documentation.draftOutdated') })
  recovered.value = null
}
function discardDraft() {
  clearDraft()
  recovered.value = null
}

async function mayLeave() {
  return !dirty.value || await confirm(t('Documentation.unsaved'), 'warning')
}
onBeforeRouteLeave(() => !busy.value && mayLeave())
onBeforeRouteUpdate(async to => {
  // Changing only the page within the same edition is handled here without reloading everything.
  if (busy.value || !await mayLeave())
    return false
  if (to.query.doc === route.query.doc && to.query.edition === route.query.edition && ready.value) {
    const page = pages.value.find(item => item.id === Number(to.query.page))
    if (page)
      await run(() => selectPage(page))
  }

  return true
})

async function openPage(page: Page) {
  if (page.id === selectedPage.value?.id)
    return
  await router.push(context.location('/documentation/editor', { page: page.id }))
}

async function savePage() {
  if (!selectedPage.value || locked.value || pathError.value)
    return
  const submitted = JSON.stringify(form)

  const page = await run(() => api<Page>(cmsUrl(`editions/${editionId.value}/pages/${selectedPage.value!.id}`), {
    method: 'PUT',
    body: { ...form, note: note.value || null, expected_revision_id: selectedPage.value!.current_revision_id },
  }))

  // The header status shows the save time: no toast, so the action buttons stay visible.
  if (!page)
    return
  selectedPage.value = page
  saved.value = submitted
  savedAt.value = page.revision.created_at
  note.value = ''
  clearDraft()
  await run(async () => {
    await loadPages()
    revisions.value = await api<Revision[]>(cmsUrl(`pages/${page.id}/revisions`))
  })
}

function openCreate(parentId: number | null) {
  Object.assign(creation, { title: '', path: '', parent_id: parentId, pathTouched: false })
  createDialog.value = true
}
watch(() => creation.title, title => {
  if (!creation.pathTouched) {
    const parent = pages.value.find(page => page.id === creation.parent_id)

    creation.path = [parent?.revision.path, slugify(title)].filter(Boolean).join('/')
  }
})
async function createPage() {
  if (!await mayLeave())
    return
  const siblings = pages.value.filter(page => !page.archived_at && page.revision.parent_id === creation.parent_id).length

  const page = await run(() => api<Page>(cmsUrl(`editions/${editionId.value}/pages`), {
    method: 'POST',
    body: { title: creation.title, path: creation.path, markdown: '', parent_id: creation.parent_id, position: siblings, note: t('Documentation.pageCreated') },
  }), t('Documentation.pageCreated'))

  if (!page)
    return
  createDialog.value = false
  saved.value = JSON.stringify(form)
  await run(loadPages)
  await router.push(context.location('/documentation/editor', { page: page.id }))
}

async function move(page: Page, direction: TreeMove) {
  if (dirty.value) {
    notify({ type: 'warning', message: t('Documentation.saveFirst') })

    return
  }
  const shape = pages.value.filter(item => !item.archived_at).map(item => ({ id: item.id, parent_id: item.revision.parent_id, position: item.revision.position }))
  const changes = movePage(shape, page.id, direction)
  if (!changes.length)
    return
  const updated = await run(() => api<Page[]>(cmsUrl(`editions/${editionId.value}/pages/reorder`), { method: 'POST', body: { pages: changes } }), t('Documentation.treeSaved'))
  if (!updated)
    return
  pages.value = updated

  const current = updated.find(item => item.id === selectedPage.value?.id)
  if (current && current.current_revision_id !== selectedPage.value?.current_revision_id)
    await run(() => selectPage(current))
}

async function archive(page: Page) {
  if (!await confirm(t(page.archived_at ? 'Documentation.confirmUnarchive' : 'Documentation.confirmArchive'), 'warning'))
    return
  await run(async () => {
    await api(cmsUrl(`pages/${page.id}/archive`), { method: 'PATCH', body: { archived: !page.archived_at } })
    await loadPages()

    const fresh = pages.value.find(item => item.id === page.id)
    if (fresh && selectedPage.value?.id === page.id)
      await selectPage(fresh)
  }, t(page.archived_at ? 'Documentation.unarchived' : 'Documentation.archivedDone'))
}

async function restoreRevision(revision: Revision) {
  if (!selectedPage.value || !await mayLeave() || !await confirm(t('Documentation.confirmRestore')))
    return
  const page = await run(() => api<Page>(cmsUrl(`pages/${selectedPage.value!.id}/revisions/${revision.id}/restore`), { method: 'POST', body: { expected_revision_id: selectedPage.value!.current_revision_id } }), t('Documentation.restored'))
  if (!page)
    return
  saved.value = JSON.stringify(form)
  clearDraft()
  await run(async () => {
    await loadPages()
    await selectPage(page)
  })
}

async function uploadFiles(files: File[], insert = true) {
  if (!can('media') || !collectionId.value)
    return
  uploading.value = true
  try {
    for (const file of files) {
      const body = new FormData()

      body.append('image', file)
      body.append('alt', file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' '))

      const image = await run(() => api<CmsImage>(cmsUrl(`collections/${collectionId.value}/images`), { method: 'POST', body }))
      if (image && insert)
        editor.value?.insertImage(image.id, image.alt || image.name)
    }
    await loadImages()
  }
  finally {
    uploading.value = false
  }
}

watch(() => form.markdown, () => view.value !== 'write' && refreshPreview())
watch(view, value => value !== 'write' && refreshPreview())

async function publish() {
  if (dirty.value) {
    notify({ type: 'warning', message: t('Documentation.saveFirst') })

    return
  }
  if (!await confirm(t('Documentation.confirmPublish')))
    return
  const done = await run(() => api(cmsUrl(`editions/${editionId.value}/publish`), { method: 'POST' }), t('Documentation.queued'))
  if (done !== undefined)
    await context.load(true)
}

function keys(event: KeyboardEvent) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    savePage()
  }
  if (event.key === 'Escape' && focusMode.value && !document.querySelector('.v-overlay--active'))
    focusMode.value = false
}

const warnUnload = (event: BeforeUnloadEvent) => {
  if (dirty.value) {
    event.preventDefault()
    event.returnValue = ''
  }
}

onMounted(() => {
  // Panels overlay the text on small screens: start with the writing area only.
  if (!display.lgAndUp.value) {
    treeOpen.value = false
    side.value = ''
  }
  window.addEventListener('keydown', keys)
  window.addEventListener('beforeunload', warnUnload)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', keys)
  window.removeEventListener('beforeunload', warnUnload)
  context.busy = false
})
watch(busy, value => {
  context.busy = value
})
watch([collectionId, editionId], () => ready.value && run(loadEdition))

onMounted(() => run(async () => {
  await context.load()

  const [settings] = await Promise.all([api<{ content: { theme?: { code_theme?: string } } }>(cmsUrl('site-settings')).catch(() => null), loadEdition()])

  codeTheme.value = settings?.content.theme?.code_theme || 'auto'
  ready.value = true
}))
</script>

<template>
  <div
    class="cms-editor"
    :class="{ 'is-focus': focusMode, 'no-tree': !showTree, 'no-side': !showSide }"
  >
    <header class="cms-editor__bar">
      <VBtn
        :icon="showTree ? 'tabler-layout-sidebar-left-collapse' : 'tabler-layout-sidebar-left-expand'"
        variant="text"
        size="small"
        :aria-label="t('Documentation.togglePages')"
        :title="t('Documentation.togglePages')"
        @click="togglePanel('tree')"
      />
      <div class="cms-editor__crumbs">
        <span class="cms-editor__crumb text-medium-emphasis">{{ collection?.title || t('Documentation.chooseContext') }}</span>
        <VIcon
          icon="tabler-chevron-right"
          size="14"
          class="text-disabled"
        />
        <VChip
          v-if="edition"
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-versions"
          class="flex-shrink-0"
        >
          {{ edition.title }}
        </VChip>
        <template v-if="selectedPage">
          <VIcon
            icon="tabler-chevron-right"
            size="14"
            class="text-disabled"
          />
          <strong class="cms-editor__crumb">{{ form.title || t('Documentation.untitled') }}</strong>
        </template>
      </div>
      <span
        v-if="selectedPage"
        class="cms-editor__status"
        :class="{ 'is-dirty': dirty }"
        data-test="save-status"
        :title="busy ? t('Documentation.saving') : dirty ? t('Documentation.unsavedLabel') : t('Documentation.savedAt', { time: dayjs(savedAt).format('HH:mm') })"
      >
        <VIcon
          :icon="busy ? 'tabler-loader-2' : dirty ? 'tabler-point-filled' : 'tabler-cloud-check'"
          size="16"
          :class="{ spin: busy }"
        />
        <span class="cms-editor__status-text">{{ busy ? t('Documentation.saving') : dirty ? t('Documentation.unsavedLabel') : t('Documentation.savedAt', { time: dayjs(savedAt).format('HH:mm') }) }}</span>
      </span>
      <CmsSegmented
        v-if="selectedPage"
        v-model="view"
        icon-only
        class="d-none d-md-inline-flex"
        :label="t('Documentation.viewMode')"
        :items="[{ value: 'write', icon: 'tabler-pencil', title: t('Documentation.viewWrite'), testId: 'view-write' }, { value: 'split', icon: 'tabler-layout-columns', title: t('Documentation.viewSplit'), testId: 'view-split' }, { value: 'preview', icon: 'tabler-eye', title: t('Documentation.preview'), testId: 'view-preview' }]"
      />
      <CmsSegmented
        v-if="selectedPage && view !== 'preview'"
        v-model="editorMode"
        :icon-only="!display.xlAndUp.value"
        :label="t('Documentation.editMode')"
        :items="[{ value: 'visual', icon: 'tabler-typography', label: t('Documentation.visual'), testId: 'mode-visual' }, { value: 'markdown', icon: 'tabler-markdown', label: 'Markdown', testId: 'mode-markdown' }]"
      />
      <VBtn
        :icon="focusMode ? 'tabler-minimize' : 'tabler-maximize'"
        variant="text"
        size="small"
        :aria-label="t(focusMode ? 'Documentation.exitFocus' : 'Documentation.focusMode')"
        :title="t(focusMode ? 'Documentation.exitFocus' : 'Documentation.focusMode')"
        @click="focusMode = !focusMode"
      />
      <VBtn
        :icon="showSide ? 'tabler-layout-sidebar-right-collapse' : 'tabler-layout-sidebar-right-expand'"
        variant="text"
        size="small"
        :aria-label="t('Documentation.togglePanel')"
        :title="t('Documentation.togglePanel')"
        @click="togglePanel('side')"
      />
      <VBtn
        v-if="selectedPage && editable && !selectedPage.archived_at"
        color="primary"
        prepend-icon="tabler-device-floppy"
        :disabled="busy || !dirty || !!pathError"
        :title="`${t('Documentation.save')} (Ctrl+S)`"
        data-test="save-page"
        class="flex-shrink-0"
        @click="savePage"
      >
        {{ t('Documentation.save') }}
      </VBtn>
      <VBtn
        v-if="can('publish') && edition && display.lgAndUp.value"
        color="success"
        variant="tonal"
        prepend-icon="tabler-rocket"
        :disabled="busy"
        data-test="publish"
        @click="publish"
      >
        {{ t('Documentation.publishShort') }}
      </VBtn>
      <VBtn
        v-else-if="can('publish') && edition"
        color="success"
        variant="tonal"
        icon="tabler-rocket"
        size="small"
        :aria-label="t('Documentation.publishShort')"
        :title="t('Documentation.publishShort')"
        :disabled="busy"
        data-test="publish"
        @click="publish"
      />
    </header>
    <VProgressLinear
      :active="busy || uploading"
      indeterminate
      height="2"
      color="primary"
      class="cms-editor__progress"
    />

    <div class="cms-editor__body">
      <aside
        v-show="showTree"
        class="cms-editor__tree"
      >
        <PageTree
          :pages="pages"
          :selected-id="selectedPage?.id"
          :editable="editable"
          :busy="busy"
          :dirty-ids="dirty && selectedPage ? [selectedPage.id] : []"
          @open="openPage"
          @create="openCreate"
          @move="move"
          @archive="archive"
        />
      </aside>

      <main class="cms-editor__main">
        <div
          v-if="!edition"
          class="cms-editor__empty"
        >
          <VIcon
            icon="tabler-books"
            size="56"
          />
          <h2>{{ t('Documentation.noEditionSelected') }}</h2>
          <p>{{ t('Documentation.startHint') }}</p>
          <VBtn :to="context.location('/documentation/collections')">
            {{ t('Documentation.manageCollections') }}
          </VBtn>
        </div>
        <div
          v-else-if="!selectedPage"
          class="cms-editor__empty"
        >
          <VIcon
            icon="tabler-file-pencil"
            size="56"
          />
          <h2>{{ pages.length ? t('Documentation.selectPage') : t('Documentation.emptyPages') }}</h2>
          <VBtn
            v-if="editable"
            prepend-icon="tabler-plus"
            @click="openCreate(null)"
          >
            {{ t('Documentation.addPage') }}
          </VBtn>
        </div>
        <template v-else>
          <VAlert
            v-if="recovered"
            type="warning"
            variant="tonal"
            density="compact"
            class="ma-3"
            icon="tabler-history"
          >
            {{ t('Documentation.draftFound', { date: dayjs(recovered.at).format('DD/MM/YYYY HH:mm') }) }}
            <template #append>
              <VBtn
                size="small"
                variant="text"
                @click="discardDraft"
              >
                {{ t('Documentation.discard') }}
              </VBtn>
              <VBtn
                size="small"
                color="warning"
                variant="elevated"
                data-test="restore-draft"
                @click="restoreDraft"
              >
                {{ t('Documentation.restoreDraft') }}
              </VBtn>
            </template>
          </VAlert>
          <VAlert
            v-if="selectedPage.archived_at"
            type="info"
            variant="tonal"
            density="compact"
            class="ma-3"
            icon="tabler-archive"
          >
            {{ t('Documentation.pageArchived') }}
            <template #append>
              <VBtn
                v-if="editable"
                size="small"
                variant="elevated"
                @click="archive(selectedPage)"
              >
                {{ t('Documentation.unarchive') }}
              </VBtn>
            </template>
          </VAlert>

          <div class="page-head">
            <input
              v-model="form.title"
              class="page-head__title"
              :placeholder="t('Documentation.untitled')"
              :readonly="locked"
              :aria-label="t('Documentation.pageTitle')"
              maxlength="200"
              data-test="page-title"
            >
            <div class="page-head__meta">
              <button
                type="button"
                class="page-head__chip"
                :class="{ 'is-error': !!pathError }"
                @click="settingsOpen = !settingsOpen"
              >
                <VIcon
                  icon="tabler-link"
                  size="14"
                />/{{ form.path || '…' }}
              </button>
              <span class="page-head__chip is-static"><VIcon
                icon="tabler-clock"
                size="14"
              />{{ t('Documentation.readingTime', { minutes: stats.minutes }) }}</span>
              <span class="page-head__chip is-static"><VIcon
                icon="tabler-text-size"
                size="14"
              />{{ t('Documentation.wordCount', { count: stats.words }) }}</span>
              <button
                type="button"
                class="page-head__chip"
                data-test="page-settings"
                @click="settingsOpen = !settingsOpen"
              >
                <VIcon
                  icon="tabler-settings"
                  size="14"
                />{{ t('Documentation.pageSettings') }}
                <VIcon
                  :icon="settingsOpen ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                  size="14"
                />
              </button>
            </div>
            <VExpandTransition>
              <div
                v-show="settingsOpen"
                class="page-settings"
              >
                <VRow dense>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.path"
                      :label="t('Documentation.path')"
                      :readonly="locked"
                      :error-messages="pathError"
                      :hint="pagePublicUrl"
                      persistent-hint
                      density="compact"
                      prefix="/"
                      data-test="page-path"
                    >
                      <template #append-inner>
                        <VBtn
                          v-if="!locked"
                          icon="tabler-wand"
                          size="x-small"
                          variant="text"
                          :title="t('Documentation.pathFromTitle')"
                          @click="form.path = slugify(form.title)"
                        />
                      </template>
                    </VTextField>
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VSelect
                      v-model="form.parent_id"
                      :items="parentOptions"
                      clearable
                      density="compact"
                      :label="t('Documentation.parent')"
                      :disabled="locked"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="2"
                  >
                    <VTextField
                      v-model.number="form.position"
                      type="number"
                      min="0"
                      density="compact"
                      :label="t('Documentation.position')"
                      :readonly="locked"
                    />
                  </VCol>
                </VRow>
              </div>
            </VExpandTransition>
          </div>

          <div
            class="cms-editor__panes"
            :class="`view-${view}`"
          >
            <div
              v-show="view !== 'preview'"
              class="cms-editor__write"
            >
              <DocumentationEditor
                :key="editorKey"
                ref="editor"
                v-model="form.markdown"
                v-model:mode="editorMode"
                :readonly="locked"
                :code-theme="codeTheme"
                @pick-image="pickerOpen = true"
                @upload-files="uploadFiles"
              />
            </div>
            <div
              v-if="view !== 'write'"
              class="cms-editor__preview"
              data-test="preview"
            >
              <div class="preview-badge">
                <VIcon
                  icon="tabler-eye"
                  size="14"
                /> {{ t('Documentation.livePreview') }}
                <VProgressCircular
                  v-if="previewing"
                  indeterminate
                  size="12"
                  width="2"
                />
              </div>
              <MarkdownPreview
                :html="html"
                :title="form.title"
                :code-theme="codeTheme"
                :loading="previewing"
              />
            </div>
          </div>
        </template>
      </main>

      <aside
        v-if="showSide"
        class="cms-editor__side"
      >
        <VTabs
          v-model="side"
          density="compact"
          grow
          class="flex-grow-0"
        >
          <VTab
            value="outline"
            :title="t('Documentation.outline')"
          >
            <VIcon icon="tabler-list-tree" />
          </VTab>
          <VTab
            value="history"
            :title="t('Documentation.history')"
            data-test="history-tab"
          >
            <VIcon icon="tabler-history" />
          </VTab>
          <VTab
            value="info"
            :title="t('Documentation.pageInfo')"
          >
            <VIcon icon="tabler-info-circle" />
          </VTab>
        </VTabs>
        <div class="cms-editor__side-body">
          <template v-if="side === 'outline'">
            <p class="side-title">
              {{ t('Documentation.outline') }}
            </p>
            <button
              v-for="(heading, index) in headings"
              :key="index"
              type="button"
              class="outline-item"
              :style="{ '--level': heading.level }"
              @click="editor?.focusHeading(index)"
            >
              {{ heading.text }}
            </button>
            <p
              v-if="!headings.length"
              class="text-body-2 text-medium-emphasis"
            >
              {{ t('Documentation.outlineEmpty') }}
            </p>
          </template>
          <template v-else-if="side === 'history'">
            <p class="side-title">
              {{ t('Documentation.history') }}
            </p>
            <VTextField
              v-if="selectedPage && editable && dirty"
              v-model="note"
              density="compact"
              :label="t('Documentation.revisionNote')"
              :hint="t('Documentation.revisionNoteHint')"
              persistent-hint
              maxlength="255"
              class="mb-4"
              data-test="revision-note"
            />
            <RevisionPanel
              :revisions="revisions"
              :current-id="selectedPage?.current_revision_id"
              :current="form.markdown"
              :can-restore="can('restore') && editable && !selectedPage?.archived_at"
              :busy="busy"
              @restore="restoreRevision"
            />
          </template>
          <template v-else>
            <p class="side-title">
              {{ t('Documentation.pageInfo') }}
            </p>
            <dl
              v-if="selectedPage"
              class="info-list"
            >
              <dt>{{ t('Documentation.status') }}</dt>
              <dd>
                <VChip
                  size="x-small"
                  :color="selectedPage.archived_at ? 'secondary' : edition?.published_at ? 'success' : 'warning'"
                  variant="tonal"
                >
                  {{ selectedPage.archived_at ? t('Documentation.archived') : edition?.published_at ? t('Documentation.editionPublished') : t('Documentation.draft') }}
                </VChip>
              </dd>
              <dt>{{ t('Documentation.words') }}</dt><dd>{{ stats.words }}</dd>
              <dt>{{ t('Documentation.characters') }}</dt><dd>{{ stats.characters }}</dd>
              <dt>{{ t('Documentation.codeBlocks') }}</dt><dd>{{ stats.codeBlocks }}</dd>
              <dt>{{ t('Documentation.revisionsCount') }}</dt><dd>{{ revisions.length }}</dd>
              <dt>{{ t('Documentation.publicUrl') }}</dt>
              <dd class="text-truncate">
                <a
                  v-if="edition?.published_at"
                  :href="pagePublicUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                >{{ form.path }}.html</a><span v-else>—</span>
              </dd>
            </dl>
            <p class="side-title mt-4">
              {{ t('Documentation.imagesInPage') }}
            </p>
            <div class="info-images">
              <img
                v-for="image in usedImages"
                :key="image.id"
                :src="`/api/v1/documentation/images/${image.id}/file`"
                :alt="image.alt || image.name"
                :title="image.name"
              >
            </div>
            <p
              v-if="!usedImages.length"
              class="text-body-2 text-medium-emphasis"
            >
              {{ t('Documentation.noImagesInPage') }}
            </p>
            <p class="side-title mt-4">
              {{ t('Documentation.shortcuts') }}
            </p>
            <ul class="shortcut-list">
              <li><kbd>Ctrl</kbd>+<kbd>S</kbd> {{ t('Documentation.save') }}</li>
              <li><kbd>/</kbd> {{ t('Documentation.insertBlock') }}</li>
              <li><kbd>Ctrl</kbd>+<kbd>K</kbd> {{ t('Documentation.link') }}</li>
              <li><kbd>Ctrl</kbd>+<kbd>B</kbd> / <kbd>I</kbd> / <kbd>E</kbd> {{ t('Documentation.formatting') }}</li>
              <li><kbd>Ctrl</kbd>+<kbd>V</kbd> {{ t('Documentation.pasteImage') }}</li>
            </ul>
          </template>
        </div>
      </aside>
    </div>

    <MediaPicker
      v-model="pickerOpen"
      :images="images"
      :can-upload="can('media')"
      :uploading="uploading"
      @select="image => editor?.insertImage(image.id, image.alt || image.name)"
      @upload="files => uploadFiles(files, false)"
    />

    <VDialog
      v-model="createDialog"
      max-width="560"
    >
      <VCard :title="t('Documentation.newPage')">
        <VCardText class="d-flex flex-column gap-4">
          <VTextField
            v-model="creation.title"
            :label="t('Documentation.pageTitle')"
            autofocus
            maxlength="200"
            data-test="new-page-title"
            @keydown.enter="creation.title && isValidPath(creation.path) && createPage()"
          />
          <VTextField
            v-model="creation.path"
            :label="t('Documentation.path')"
            prefix="/"
            :error-messages="creation.path && !isValidPath(creation.path) ? t('Documentation.invalidPath') : ''"
            @input="creation.pathTouched = true"
          />
          <VSelect
            v-model="creation.parent_id"
            :items="pages.filter(page => !page.archived_at).map(page => ({ title: page.revision.title, value: page.id }))"
            clearable
            :label="t('Documentation.parent')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="createDialog = false">
            {{ t('Documentation.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :disabled="busy || !creation.title || !isValidPath(creation.path)"
            data-test="create-page"
            @click="createPage"
          >
            {{ t('Documentation.create') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.cms-editor { display: flex; flex-direction: column; height: calc(100dvh - 150px); min-height: 620px; overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 14px; background: rgb(var(--v-theme-surface)); box-shadow: 0 2px 10px rgba(var(--v-shadow-key-umbra-color), .06); }
.cms-editor.is-focus { position: fixed; inset: 0; z-index: 1005; height: auto; border-radius: 0; }
.cms-editor__bar { display: flex; flex-wrap: nowrap; align-items: center; gap: 8px; min-height: 56px; padding: 8px 12px; border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.cms-editor__crumbs { display: flex; flex: 1 1 auto; align-items: center; gap: 6px; min-width: 0; overflow: hidden; font-size: 14px; }
.cms-editor__crumb { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cms-editor__bar > :not(.cms-editor__crumbs) { flex-shrink: 0; }
.cms-editor__status { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; color: rgba(var(--v-theme-on-surface), .6); white-space: nowrap; }
.cms-editor__status.is-dirty { color: rgb(var(--v-theme-warning)); }
.cms-editor__progress { position: relative; margin-top: -2px; }
.cms-editor__body { display: grid; grid-template-columns: 270px minmax(0, 1fr) 300px; flex: 1; min-height: 0; }
.no-tree .cms-editor__body { grid-template-columns: minmax(0, 1fr) 300px; }
.no-side .cms-editor__body { grid-template-columns: 270px minmax(0, 1fr); }
.no-tree.no-side .cms-editor__body { grid-template-columns: minmax(0, 1fr); }
.cms-editor__tree { padding: 12px; overflow-y: auto; border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); background: rgba(var(--v-theme-on-surface), .015); }
.cms-editor__main { display: flex; flex-direction: column; min-width: 0; overflow-y: auto; }
.cms-editor__side { display: flex; flex-direction: column; min-height: 0; border-inline-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.cms-editor__side-body { flex: 1; padding: 14px; overflow-y: auto; }
.cms-editor__empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; flex: 1; padding: 40px; text-align: center; color: rgba(var(--v-theme-on-surface), .6); }
.cms-editor__empty h2 { font-size: 1.2rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), .85); }
.page-head { max-width: 820px; width: 100%; margin: 0 auto; padding: 28px clamp(16px, 5vw, 56px) 4px; }
.page-head__title { width: 100%; border: 0; outline: none; background: transparent; color: rgb(var(--v-theme-on-surface)); font-size: 2.3rem; font-weight: 750; letter-spacing: -.025em; line-height: 1.2; }
.page-head__title::placeholder { color: rgba(var(--v-theme-on-surface), .3); }
.page-head__meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.page-head__chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; background: rgba(var(--v-theme-on-surface), .05); color: rgba(var(--v-theme-on-surface), .7); font-size: 12px; }
.page-head__chip:not(.is-static):hover { background: rgba(var(--v-theme-primary), .1); color: rgb(var(--v-theme-primary)); }
.page-head__chip.is-error { background: rgba(var(--v-theme-error), .1); color: rgb(var(--v-theme-error)); }
.page-settings { margin-top: 14px; padding: 16px 16px 4px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 12px; background: rgba(var(--v-theme-on-surface), .015); }
.cms-editor__panes { display: grid; flex: 1; min-height: 0; }
.cms-editor__panes.view-split { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
.cms-editor__panes.view-split .cms-editor__preview { border-inline-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.cms-editor__preview { position: relative; min-width: 0; background: rgba(var(--v-theme-on-surface), .012); }
.preview-badge { position: sticky; top: 8px; z-index: 1; display: flex; align-items: center; gap: 6px; width: fit-content; margin: 8px 12px 0 auto; padding: 2px 10px; border-radius: 999px; background: rgba(var(--v-theme-surface), .9); font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: rgba(var(--v-theme-on-surface), .55); box-shadow: 0 1px 4px rgba(0, 0, 0, .08); }
.side-title { margin-bottom: 10px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: rgba(var(--v-theme-on-surface), .55); }
.outline-item { display: block; width: 100%; padding: 5px 8px 5px calc(8px + (var(--level) - 1) * 12px); border-inline-start: 2px solid transparent; border-radius: 0 6px 6px 0; text-align: start; font-size: 13px; color: rgba(var(--v-theme-on-surface), .75); }
.outline-item:hover { border-color: rgb(var(--v-theme-primary)); background: rgba(var(--v-theme-primary), .06); color: rgb(var(--v-theme-primary)); }
.info-list { display: grid; grid-template-columns: auto 1fr; gap: 8px 12px; font-size: 13px; }
.info-list dt { color: rgba(var(--v-theme-on-surface), .6); }
.info-list dd { margin: 0; text-align: end; }
.info-images { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.info-images img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 6px; }
.shortcut-list { padding: 0; list-style: none; font-size: 13px; }
.shortcut-list li { padding: 3px 0; color: rgba(var(--v-theme-on-surface), .7); }
kbd { padding: 0 5px; border: 1px solid rgba(var(--v-border-color), .3); border-radius: 4px; font: 11px ui-monospace, monospace; }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 1439px) { .cms-editor__status-text { display: none; } }
@media (max-width: 1279px) {
  .cms-editor__body, .no-tree .cms-editor__body, .no-side .cms-editor__body { grid-template-columns: minmax(0, 1fr); }
  .cms-editor__tree, .cms-editor__side { position: absolute; z-index: 5; top: 110px; bottom: 0; width: min(300px, 86vw); background: rgb(var(--v-theme-surface)); box-shadow: 0 10px 40px rgba(0, 0, 0, .2); }
  .cms-editor { position: relative; }
  .cms-editor__side { inset-inline-end: 0; }
  .cms-editor__panes.view-split { grid-template-columns: minmax(0, 1fr); }
}
</style>
