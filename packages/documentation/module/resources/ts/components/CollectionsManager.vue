<script setup lang="ts">
import dayjs from 'dayjs'
import type { Collection, Edition } from '../types/cms'
import { useDocumentationContext } from '../stores/context'
import { cmsUrl, publicSiteUrl, useCms } from '../composables/useCms'
import { slugify } from '../editor/text'

const { t, api, busy, can, confirm, run, fieldErrors } = useCms()
const context = useDocumentationContext()
const { collections, collection, collectionId, editionId } = storeToRefs(context)
const search = ref('')
const showArchived = ref(false)
const collectionDialog = ref(false)
const editionDialog = ref(false)
const collectionForm = reactive({ id: null as number | null, title: '', slug: '', description: '', default_edition_id: null as number | null, slugTouched: false })
const editionForm = reactive({ id: null as number | null, title: '', slug: '', source_id: null as number | null, slugTouched: false })

const visible = computed(() => collections.value.filter(item => (showArchived.value || !item.archived_at) && `${item.title} ${item.slug} ${item.description || ''}`.toLowerCase().includes(search.value.toLowerCase())))
const editions = computed(() => (collection.value?.editions || []).filter(item => showArchived.value || !item.archived_at))
const editing = computed(() => collectionForm.id ? collections.value.find(item => item.id === collectionForm.id) : undefined)
const editingEdition = computed(() => editionForm.id ? collection.value?.editions.find(item => item.id === editionForm.id) : undefined)

watch(() => collectionForm.title, title => {
  if (!collectionForm.id && !collectionForm.slugTouched)
    collectionForm.slug = slugify(title)
})
watch(() => editionForm.title, title => {
  if (!editionForm.id && !editionForm.slugTouched)
    editionForm.slug = slugify(title).replace(/^v-(\d)/, 'v$1')
})

function status(item: { published_at: string | null; archived_at: string | null }) {
  if (item.archived_at)
    return { color: 'secondary', label: t('Documentation.archived'), icon: 'tabler-archive' }
  if (item.published_at)
    return { color: 'success', label: t('Documentation.published'), icon: 'tabler-world' }

  return { color: 'warning', label: t('Documentation.draft'), icon: 'tabler-pencil' }
}

// Explicit entry points: a click handler must never receive the DOM event as an item.
function newCollection() {
  openCollection()
}
function newEdition() {
  openEdition()
}
function openCollection(item?: Collection) {
  Object.assign(collectionForm, item ? { ...item, description: item.description || '', slugTouched: true } : { id: null, title: '', slug: '', description: '', default_edition_id: null, slugTouched: false })
  fieldErrors.value = {}
  collectionDialog.value = true
}
async function saveCollection() {
  const { slugTouched: _, ...body } = collectionForm
  const item = await run(() => api<Collection>(cmsUrl(`collections${collectionForm.id ? `/${collectionForm.id}` : ''}`), { method: collectionForm.id ? 'PUT' : 'POST', body }), t('Documentation.collectionSaved'))
  if (!item)
    return
  collectionDialog.value = false
  await context.load(true)
  if (!collectionForm.id)
    await context.select(item.id)
}

function openEdition(item?: Edition, duplicate = false) {
  Object.assign(editionForm, item && !duplicate
    ? { ...item, source_id: null, slugTouched: true }
    : { id: null, title: '', slug: '', source_id: duplicate && item ? item.id : null, slugTouched: false })
  fieldErrors.value = {}
  editionDialog.value = true
}
async function saveEdition() {
  const { slugTouched: _, ...body } = editionForm
  const item = await run(() => api<Edition>(cmsUrl(`collections/${collectionId.value}/editions${editionForm.id ? `/${editionForm.id}` : ''}`), { method: editionForm.id ? 'PUT' : 'POST', body }), t('Documentation.editionSaved'))
  if (!item)
    return
  editionDialog.value = false
  await context.load(true)
  if (!editionForm.id)
    await context.select(item.collection_id, item.id)
}
async function setDefault(item: Edition) {
  if (!collection.value)
    return
  const { editions: _, ...current } = collection.value

  await run(async () => {
    await api(cmsUrl(`collections/${current.id}`), { method: 'PUT', body: { ...current, description: current.description || '', default_edition_id: item.id } })
    await context.load(true)
  }, t('Documentation.defaultSaved'))
}
async function archive(kind: 'collections' | 'editions', item: { id: number; archived_at: string | null }) {
  if (!await confirm(t(item.archived_at ? 'Documentation.confirmUnarchive' : 'Documentation.confirmArchive'), 'warning'))
    return
  await run(async () => {
    await api(cmsUrl(`${kind}/${item.id}/archive`), { method: 'PATCH', body: { archived: !item.archived_at } })
    await context.load(true)
  }, t(item.archived_at ? 'Documentation.unarchived' : 'Documentation.archivedDone'))
}

onMounted(() => run(() => context.load()))
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center gap-3 mb-6">
      <div class="page-title">
        <h1 class="text-h4 font-weight-bold">
          {{ t('Documentation.collectionsTitle') }}
        </h1>
        <p class="text-medium-emphasis mb-0">
          {{ t('Documentation.collectionsSubtitle') }}
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
        v-if="can('edit')"
        prepend-icon="tabler-plus"
        :disabled="busy"
        data-test="new-collection"
        @click="newCollection"
      >
        {{ t('Documentation.newCollection') }}
      </VBtn>
    </div>

    <div class="d-flex flex-wrap align-center gap-4 mb-4">
      <VTextField
        v-model="search"
        density="compact"
        prepend-inner-icon="tabler-search"
        :placeholder="t('Documentation.searchCollections')"
        clearable
        hide-details
        style="max-width: 340px"
      />
      <VSwitch
        v-model="showArchived"
        color="primary"
        density="compact"
        hide-details
        :label="t('Documentation.showArchived')"
      />
    </div>

    <VProgressLinear
      :active="busy || context.loading"
      indeterminate
      class="mb-3"
    />

    <VCard
      v-if="!visible.length && !context.loading"
      variant="outlined"
      class="empty-state"
    >
      <VIcon
        icon="tabler-books"
        size="56"
        color="primary"
      />
      <h2 class="text-h5">
        {{ t('Documentation.noCollections') }}
      </h2>
      <p class="text-medium-emphasis">
        {{ t('Documentation.startHint') }}
      </p>
      <VBtn
        v-if="can('edit')"
        prepend-icon="tabler-plus"
        @click="newCollection"
      >
        {{ t('Documentation.newCollection') }}
      </VBtn>
    </VCard>

    <div class="collection-grid">
      <VCard
        v-for="doc in visible"
        :key="doc.id"
        class="collection-card"
        :class="{ 'is-selected': doc.id === collectionId, 'is-archived': !!doc.archived_at }"
        :data-test="`collection-${doc.slug}`"
        @click="context.select(doc.id)"
      >
        <div class="collection-card__cover">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded="lg"
            size="46"
          >
            <VIcon
              icon="tabler-book"
              size="26"
            />
          </VAvatar>
          <VChip
            size="small"
            :color="status(doc).color"
            variant="tonal"
            :prepend-icon="status(doc).icon"
          >
            {{ status(doc).label }}
          </VChip>
        </div>
        <VCardItem class="pt-2">
          <VCardTitle>{{ doc.title }}</VCardTitle>
          <VCardSubtitle>/{{ doc.slug }}</VCardSubtitle>
        </VCardItem>
        <VCardText>
          <p class="collection-card__description">
            {{ doc.description || t('Documentation.noDescription') }}
          </p>
          <div class="d-flex flex-wrap gap-2">
            <VChip
              size="small"
              variant="outlined"
              prepend-icon="tabler-versions"
            >
              {{ t('Documentation.editionCount', { count: doc.editions.filter(item => !item.archived_at).length }) }}
            </VChip>
            <VChip
              v-if="doc.updated_at"
              size="small"
              variant="text"
              prepend-icon="tabler-clock"
            >
              {{ dayjs(doc.updated_at).format('DD/MM/YYYY') }}
            </VChip>
          </div>
        </VCardText>
        <VCardActions @click.stop>
          <VBtn
            v-if="doc.editions.some(item => !item.archived_at)"
            variant="tonal"
            prepend-icon="tabler-writing"
            :to="{ path: '/documentation/editor', query: { doc: doc.id } }"
          >
            {{ t('Documentation.openEditor') }}
          </VBtn>
          <VSpacer />
          <VMenu v-if="can('edit')">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon="tabler-dots-vertical"
                variant="text"
                size="small"
                :aria-label="t('Documentation.rowActions')"
              />
            </template>
            <VList density="compact">
              <VListItem
                v-if="!doc.archived_at"
                prepend-icon="tabler-edit"
                :title="t('Documentation.settings')"
                @click="openCollection(doc)"
              />
              <VListItem
                :prepend-icon="doc.archived_at ? 'tabler-archive-off' : 'tabler-archive'"
                :title="t(doc.archived_at ? 'Documentation.unarchive' : 'Documentation.archive')"
                @click="archive('collections', doc)"
              />
            </VList>
          </VMenu>
        </VCardActions>
      </VCard>
    </div>

    <VCard
      v-if="collection"
      class="mt-6"
    >
      <VCardItem>
        <template #prepend>
          <VIcon
            icon="tabler-versions"
            color="primary"
          />
        </template>
        <VCardTitle>{{ t('Documentation.editionsOf', { title: collection.title }) }}</VCardTitle>
        <VCardSubtitle>{{ t('Documentation.editionsHint') }}</VCardSubtitle>
        <template #append>
          <VBtn
            v-if="can('edit') && !collection.archived_at"
            prepend-icon="tabler-plus"
            :disabled="busy"
            data-test="new-edition"
            @click="newEdition"
          >
            {{ t('Documentation.newEdition') }}
          </VBtn>
        </template>
      </VCardItem>
      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th>{{ t('Documentation.edition') }}</th>
            <th>{{ t('Documentation.slug') }}</th>
            <th>{{ t('Documentation.status') }}</th>
            <th class="text-end">
              {{ t('Documentation.rowActions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="version in editions"
            :key="version.id"
            :class="{ 'row-active': version.id === editionId }"
          >
            <td>
              <div class="d-flex align-center gap-2">
                <strong>{{ version.title }}</strong>
                <VChip
                  v-if="version.id === collection.default_edition_id"
                  size="x-small"
                  color="primary"
                  prepend-icon="tabler-star-filled"
                >
                  {{ t('Documentation.default') }}
                </VChip>
              </div>
            </td>
            <td><code>{{ collection.slug }}/{{ version.slug }}</code></td>
            <td>
              <VChip
                size="small"
                :color="status(version).color"
                variant="tonal"
                :prepend-icon="status(version).icon"
              >
                {{ status(version).label }}
              </VChip>
            </td>
            <td class="text-end">
              <VBtn
                v-if="!version.archived_at"
                size="small"
                variant="tonal"
                prepend-icon="tabler-writing"
                :to="{ path: '/documentation/editor', query: { doc: collection.id, edition: version.id } }"
              >
                {{ t('Documentation.openEditor') }}
              </VBtn>
              <VMenu v-if="can('edit') && !collection.archived_at">
                <template #activator="{ props: menuProps }">
                  <VBtn
                    v-bind="menuProps"
                    icon="tabler-dots-vertical"
                    variant="text"
                    size="small"
                    :aria-label="t('Documentation.rowActions')"
                  />
                </template>
                <VList density="compact">
                  <VListItem
                    v-if="!version.archived_at"
                    prepend-icon="tabler-edit"
                    :title="t('Documentation.editEdition')"
                    @click="openEdition(version)"
                  />
                  <VListItem
                    v-if="!version.archived_at"
                    prepend-icon="tabler-copy"
                    :title="t('Documentation.duplicateEdition')"
                    @click="openEdition(version, true)"
                  />
                  <VListItem
                    v-if="!version.archived_at && version.id !== collection.default_edition_id"
                    prepend-icon="tabler-star"
                    :title="t('Documentation.makeDefault')"
                    @click="setDefault(version)"
                  />
                  <VListItem
                    :prepend-icon="version.archived_at ? 'tabler-archive-off' : 'tabler-archive'"
                    :title="t(version.archived_at ? 'Documentation.unarchive' : 'Documentation.archive')"
                    @click="archive('editions', version)"
                  />
                </VList>
              </VMenu>
            </td>
          </tr>
          <tr v-if="!editions.length">
            <td
              colspan="4"
              class="text-center text-medium-emphasis py-6"
            >
              {{ t('Documentation.noEditions') }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <VDialog
      v-model="collectionDialog"
      max-width="620"
    >
      <VCard>
        <VCardItem>
          <VCardTitle>{{ collectionForm.id ? t('Documentation.settings') : t('Documentation.newCollection') }}</VCardTitle>
        </VCardItem>
        <VCardText class="d-flex flex-column gap-4">
          <VTextField
            v-model="collectionForm.title"
            :label="t('Documentation.collectionName')"
            :error-messages="fieldErrors.title"
            autofocus
            data-test="collection-title"
          />
          <VTextField
            v-model="collectionForm.slug"
            :label="t('Documentation.slug')"
            :hint="t('Documentation.slugHint')"
            persistent-hint
            :prefix="`${publicSiteUrl().replace(/\/$/, '')}/`"
            :readonly="!!editing?.published_at"
            :error-messages="fieldErrors.slug"
            @input="collectionForm.slugTouched = true"
          />
          <VTextarea
            v-model="collectionForm.description"
            :label="t('Documentation.description')"
            rows="3"
            auto-grow
            counter="5000"
          />
          <VSelect
            v-if="collectionForm.id"
            v-model="collectionForm.default_edition_id"
            :items="editing?.editions.filter(item => !item.archived_at) || []"
            item-title="title"
            item-value="id"
            :label="t('Documentation.defaultEdition')"
            clearable
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="collectionDialog = false">
            {{ t('Documentation.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :disabled="busy || !collectionForm.title || !collectionForm.slug"
            data-test="save-collection"
            @click="saveCollection"
          >
            {{ t('Documentation.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="editionDialog"
      max-width="620"
    >
      <VCard>
        <VCardItem>
          <VCardTitle>{{ editionForm.id ? t('Documentation.editEdition') : editionForm.source_id ? t('Documentation.duplicateEdition') : t('Documentation.newEdition') }}</VCardTitle>
          <VCardSubtitle>{{ collection?.title }}</VCardSubtitle>
        </VCardItem>
        <VCardText class="d-flex flex-column gap-4">
          <VTextField
            v-model="editionForm.title"
            :label="t('Documentation.editionName')"
            placeholder="v2.0"
            :error-messages="fieldErrors.title"
            autofocus
            data-test="edition-title"
          />
          <VTextField
            v-model="editionForm.slug"
            :label="t('Documentation.slug')"
            :prefix="`${collection?.slug}/`"
            :readonly="!!editingEdition?.published_at"
            :error-messages="fieldErrors.slug"
            @input="editionForm.slugTouched = true"
          />
          <VSelect
            v-if="!editionForm.id"
            v-model="editionForm.source_id"
            :items="collection?.editions.filter(item => !item.archived_at) || []"
            item-title="title"
            item-value="id"
            :label="t('Documentation.duplicateFrom')"
            :hint="t('Documentation.duplicateHint')"
            persistent-hint
            clearable
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="editionDialog = false">
            {{ t('Documentation.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :disabled="busy || !editionForm.title || !editionForm.slug"
            data-test="save-edition"
            @click="saveEdition"
          >
            {{ t('Documentation.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.page-title { flex: 1 1 320px; min-width: 0; }
.collection-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.collection-card { display: flex; flex-direction: column; cursor: pointer; border: 1px solid transparent; transition: transform .2s, border-color .2s, box-shadow .2s; }
.collection-card:hover { transform: translateY(-3px); }
.collection-card.is-selected { border-color: rgb(var(--v-theme-primary)); box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), .15); }
.collection-card.is-archived { opacity: .65; }
.collection-card__cover { display: flex; align-items: flex-start; justify-content: space-between; padding: 20px 20px 0; }
.collection-card__description { display: -webkit-box; min-height: 3em; margin-bottom: 14px; overflow: hidden; color: rgba(var(--v-theme-on-surface), .7); -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.collection-card .v-card-actions { margin-top: auto; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 48px 24px; text-align: center; border-style: dashed; }
.row-active { background: rgba(var(--v-theme-primary), .05); }
</style>
