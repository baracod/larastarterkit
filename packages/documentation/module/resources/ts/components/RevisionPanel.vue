<script setup lang="ts">
import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/fr'
import type { Revision } from '../types/cms'
import { diffSummary, lineDiff } from '../editor/text'
import CmsSegmented from './ui/CmsSegmented.vue'

const props = defineProps<{ revisions: Revision[]; currentId?: number | null; current: string; canRestore: boolean; busy: boolean }>()

const emit = defineEmits<{ restore: [revision: Revision] }>()

dayjs.extend(relativeTime)

const { t, locale } = useI18n()
const selected = ref<Revision | null>(null)
const view = ref<'diff' | 'source'>('diff')
const compareWith = ref<'current' | 'previous'>('current')

const previous = computed(() => selected.value ? props.revisions.find(item => item.id < selected.value!.id) : undefined)

const diff = computed(() => {
  if (!selected.value)
    return []

  return compareWith.value === 'current'
    ? lineDiff(selected.value.markdown, props.current)
    : lineDiff(previous.value?.markdown ?? '', selected.value.markdown)
})

const summary = computed(() => diffSummary(diff.value))
const ago = (date: string) => dayjs(date).locale(locale.value.startsWith('fr') ? 'fr' : 'en').fromNow()
const initials = (name?: string) => (name || '?').split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase()

function open(revision: Revision) {
  selected.value = revision
  view.value = 'diff'
  compareWith.value = revision.id === props.currentId ? 'previous' : 'current'
}
</script>

<template>
  <div class="revision-panel">
    <p
      v-if="!revisions.length"
      class="text-medium-emphasis text-body-2 pa-2"
    >
      {{ t('Documentation.noRevisions') }}
    </p>
    <ol class="revision-timeline">
      <li
        v-for="revision in revisions"
        :key="revision.id"
        :class="{ 'is-current': revision.id === currentId }"
      >
        <button
          type="button"
          class="revision-item"
          @click="open(revision)"
        >
          <VAvatar
            size="28"
            color="primary"
            variant="tonal"
            class="text-caption"
          >
            {{ initials(revision.author?.name) }}
          </VAvatar>
          <span class="revision-body">
            <strong>{{ revision.note || revision.title }}</strong>
            <small>{{ revision.author?.name || t('Documentation.system') }} · <time
              :datetime="revision.created_at"
              :title="dayjs(revision.created_at).format('DD/MM/YYYY HH:mm:ss')"
            >{{ ago(revision.created_at) }}</time></small>
          </span>
          <VChip
            v-if="revision.id === currentId"
            size="x-small"
            color="success"
            variant="tonal"
          >
            {{ t('Documentation.currentRevision') }}
          </VChip>
        </button>
      </li>
    </ol>

    <VDialog
      :model-value="!!selected"
      max-width="1100"
      scrollable
      @update:model-value="selected = null"
    >
      <VCard v-if="selected">
        <VCardItem>
          <VCardTitle>{{ t('Documentation.revisionTitle', { id: selected.id }) }} — {{ selected.title }}</VCardTitle>
          <VCardSubtitle>
            {{ selected.author?.name || t('Documentation.system') }} · {{ dayjs(selected.created_at).format('DD/MM/YYYY HH:mm') }}<template v-if="selected.note">
              · « {{ selected.note }} »
            </template>
          </VCardSubtitle>
          <template #append>
            <VBtn
              icon="tabler-x"
              variant="text"
              size="small"
              :aria-label="t('Documentation.close')"
              @click="selected = null"
            />
          </template>
        </VCardItem>
        <div class="d-flex flex-wrap align-center gap-3 px-6 pb-3">
          <CmsSegmented
            v-model="view"
            :items="[{ value: 'diff', icon: 'tabler-git-compare', label: t('Documentation.changes') }, { value: 'source', icon: 'tabler-markdown', label: t('Documentation.source') }]"
          />
          <VSelect
            v-if="view === 'diff'"
            v-model="compareWith"
            density="compact"
            hide-details
            style="max-width: 300px"
            :items="[{ title: t('Documentation.compareCurrent'), value: 'current' }, { title: t('Documentation.comparePrevious'), value: 'previous' }]"
          />
          <VSpacer />
          <span
            v-if="view === 'diff'"
            class="text-body-2"
          >
            <span class="text-success">+{{ summary.added }}</span> · <span class="text-error">−{{ summary.removed }}</span>
          </span>
        </div>
        <VDivider />
        <VCardText class="pa-0">
          <div
            v-if="view === 'diff'"
            class="diff"
            role="table"
          >
            <div
              v-for="(line, index) in diff"
              :key="index"
              class="diff-line"
              :class="`is-${line.type}`"
              role="row"
            >
              <span class="diff-number">{{ line.left ?? '' }}</span>
              <span class="diff-number">{{ line.right ?? '' }}</span>
              <span class="diff-sign">{{ line.type === 'added' ? '+' : line.type === 'removed' ? '−' : ' ' }}</span>
              <span class="diff-text">{{ line.text || ' ' }}</span>
            </div>
            <p
              v-if="!summary.added && !summary.removed"
              class="pa-6 text-medium-emphasis"
            >
              {{ t('Documentation.noDifference') }}
            </p>
          </div>
          <pre
            v-else
            class="revision-source"
          >{{ selected.markdown }}</pre>
        </VCardText>
        <VDivider />
        <VCardActions>
          <VSpacer />
          <VBtn @click="selected = null">
            {{ t('Documentation.close') }}
          </VBtn>
          <VBtn
            v-if="canRestore && selected.id !== currentId"
            color="primary"
            variant="elevated"
            prepend-icon="tabler-restore"
            :disabled="busy"
            @click="emit('restore', selected); selected = null"
          >
            {{ t('Documentation.restoreVersion') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.revision-timeline { margin: 0; padding: 0; list-style: none; }
.revision-timeline li { position: relative; }
.revision-timeline li + li::before { content: ''; position: absolute; top: -8px; inset-inline-start: 22px; height: 14px; border-inline-start: 2px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.revision-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px; border-radius: 10px; text-align: start; }
.revision-item:hover { background: rgba(var(--v-theme-on-surface), .04); }
.is-current .revision-item { background: rgba(var(--v-theme-success), .06); }
.revision-body { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.revision-body strong { overflow: hidden; font-size: 13px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.revision-body small { font-size: 12px; color: rgba(var(--v-theme-on-surface), .6); }
.diff { font: 13px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; }
.diff-line { display: grid; grid-template-columns: 44px 44px 20px 1fr; }
.diff-line.is-added { background: rgba(var(--v-theme-success), .12); }
.diff-line.is-removed { background: rgba(var(--v-theme-error), .12); }
.diff-number { padding-inline-end: 8px; text-align: end; color: rgba(var(--v-theme-on-surface), .4); user-select: none; }
.diff-sign { text-align: center; font-weight: 700; }
.is-added .diff-sign { color: rgb(var(--v-theme-success)); }
.is-removed .diff-sign { color: rgb(var(--v-theme-error)); }
.diff-text { white-space: pre-wrap; overflow-wrap: anywhere; padding-inline-end: 12px; }
.revision-source { margin: 0; padding: 20px 24px; white-space: pre-wrap; overflow-wrap: anywhere; font: 13px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; }
</style>
