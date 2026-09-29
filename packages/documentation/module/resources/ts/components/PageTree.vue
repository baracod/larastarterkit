<script setup lang="ts">
import type { Page } from '../types/cms'
import { canMove, flattenTree } from '../editor/tree'
import type { TreeMove } from '../editor/tree'

const props = defineProps<{ pages: Page[]; selectedId?: number | null; editable: boolean; busy: boolean; dirtyIds?: number[] }>()
const emit = defineEmits<{ open: [page: Page]; create: [parentId: number | null]; move: [page: Page, move: TreeMove]; archive: [page: Page] }>()
const { t } = useI18n()
const search = ref('')
const showArchived = ref(false)
const collapsed = ref(new Set<number>())

const active = computed(() => props.pages.filter(page => !page.archived_at))
const flat = computed(() => props.pages.filter(page => showArchived.value || !page.archived_at).map(page => ({ page, id: page.id, parent_id: page.revision.parent_id, position: page.revision.position })))
const shape = computed(() => active.value.map(page => ({ id: page.id, parent_id: page.revision.parent_id, position: page.revision.position })))

const nodes = computed(() => {
  const term = search.value.trim().toLowerCase()
  const all = flattenTree(flat.value)
  if (term)
    return all.filter(node => `${node.item.page.revision.title} ${node.item.page.revision.path}`.toLowerCase().includes(term)).map(node => ({ ...node, depth: 0 }))
  const hidden = new Set<number>()

  return all.filter(node => {
    if (node.item.parent_id && (hidden.has(node.item.parent_id) || collapsed.value.has(node.item.parent_id))) {
      hidden.add(node.item.id)

      return false
    }

    return true
  })
})

function toggle(id: number) {
  const next = new Set(collapsed.value)

  next.has(id) ? next.delete(id) : next.add(id)
  collapsed.value = next
}

const moves: { move: TreeMove; icon: string }[] = [
  { move: 'up', icon: 'tabler-arrow-up' },
  { move: 'down', icon: 'tabler-arrow-down' },
  { move: 'indent', icon: 'tabler-indent-increase' },
  { move: 'outdent', icon: 'tabler-indent-decrease' },
]
</script>

<template>
  <nav
    class="page-tree"
    :aria-label="t('Documentation.pages')"
  >
    <div class="page-tree__head">
      <span class="page-tree__title">{{ t('Documentation.pages') }} <VChip
        size="x-small"
        variant="tonal"
      >{{ active.length }}</VChip></span>
      <VBtn
        v-if="editable"
        size="small"
        variant="tonal"
        color="primary"
        prepend-icon="tabler-plus"
        :disabled="busy"
        data-test="new-page"
        @click="emit('create', null)"
      >
        {{ t('Documentation.addPage') }}
      </VBtn>
    </div>
    <VTextField
      v-model="search"
      density="compact"
      variant="solo-filled"
      flat
      prepend-inner-icon="tabler-search"
      :placeholder="t('Documentation.searchPages')"
      clearable
      hide-details
      class="mb-2"
    />
    <ul
      class="page-tree__list"
      role="tree"
    >
      <li
        v-for="node in nodes"
        :key="node.item.id"
        role="treeitem"
        :aria-selected="node.item.id === selectedId"
        class="page-tree__item"
        :class="{ 'is-active': node.item.id === selectedId, 'is-archived': !!node.item.page.archived_at }"
        :style="{ '--depth': node.depth }"
      >
        <button
          v-if="node.children && !search"
          type="button"
          class="page-tree__toggle"
          :aria-label="t(collapsed.has(node.item.id) ? 'Documentation.expand' : 'Documentation.collapse')"
          @click="toggle(node.item.id)"
        >
          <VIcon
            :icon="collapsed.has(node.item.id) ? 'tabler-chevron-right' : 'tabler-chevron-down'"
            size="14"
          />
        </button>
        <span
          v-else
          class="page-tree__toggle"
        />
        <button
          type="button"
          class="page-tree__link"
          :disabled="busy"
          :title="node.item.page.revision.path"
          @click="emit('open', node.item.page)"
        >
          <VIcon
            :icon="node.item.page.archived_at ? 'tabler-archive' : node.children ? 'tabler-folder' : 'tabler-file-text'"
            size="16"
            class="flex-shrink-0"
          />
          <span class="text-truncate">{{ node.item.page.revision.title }}</span>
          <span
            v-if="dirtyIds?.includes(node.item.id)"
            class="page-tree__dirty"
            :title="t('Documentation.unsavedLabel')"
          />
        </button>
        <VMenu
          v-if="editable && !node.item.page.archived_at"
          location="end"
        >
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              icon="tabler-dots-vertical"
              size="x-small"
              variant="text"
              class="page-tree__menu"
              :aria-label="t('Documentation.rowActions')"
              :disabled="busy"
            />
          </template>
          <VList density="compact">
            <VListItem
              prepend-icon="tabler-file-plus"
              :title="t('Documentation.addChild')"
              @click="emit('create', node.item.id)"
            />
            <VDivider />
            <VListItem
              v-for="entry in moves"
              :key="entry.move"
              :prepend-icon="entry.icon"
              :title="t(`Documentation.moves.${entry.move}`)"
              :disabled="!!search || !canMove(shape, node.item.id, entry.move)"
              @click="emit('move', node.item.page, entry.move)"
            />
            <VDivider />
            <VListItem
              prepend-icon="tabler-archive"
              :title="t('Documentation.archive')"
              @click="emit('archive', node.item.page)"
            />
          </VList>
        </VMenu>
      </li>
    </ul>
    <div
      v-if="!nodes.length"
      class="page-tree__empty"
    >
      <VIcon
        icon="tabler-file-off"
        size="28"
      />
      <p>{{ search ? t('Documentation.noResults') : t('Documentation.emptyPages') }}</p>
    </div>
    <VSwitch
      v-model="showArchived"
      density="compact"
      color="primary"
      hide-details
      :label="t('Documentation.showArchived')"
      class="mt-2"
    />
  </nav>
</template>

<style scoped>
.page-tree { display: flex; flex-direction: column; min-height: 0; }
.page-tree__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
.page-tree__title { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: rgba(var(--v-theme-on-surface), .6); }
.page-tree__list { margin: 0; padding: 0; list-style: none; overflow-y: auto; }
.page-tree__item { position: relative; display: flex; align-items: center; gap: 2px; padding-inline-start: calc(var(--depth) * 14px); border-radius: 8px; }
.page-tree__item:hover { background: rgba(var(--v-theme-on-surface), .04); }
.page-tree__item.is-active { background: rgba(var(--v-theme-primary), .12); }
.page-tree__item.is-active .page-tree__link { color: rgb(var(--v-theme-primary)); font-weight: 600; }
.page-tree__item.is-archived { opacity: .55; }
.page-tree__toggle { display: grid; place-items: center; flex: none; width: 20px; height: 20px; border-radius: 4px; color: rgba(var(--v-theme-on-surface), .5); }
.page-tree__link { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; padding: 7px 4px; text-align: start; font-size: 14px; color: rgba(var(--v-theme-on-surface), .85); }
.page-tree__dirty { flex: none; width: 7px; height: 7px; border-radius: 50%; background: rgb(var(--v-theme-warning)); }
.page-tree__menu { opacity: 0; transition: opacity .15s; }
.page-tree__item:hover .page-tree__menu, .page-tree__item.is-active .page-tree__menu, .page-tree__menu:focus-visible, .page-tree__menu[aria-expanded=true] { opacity: 1; }
.page-tree__empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 24px 8px; text-align: center; font-size: 13px; color: rgba(var(--v-theme-on-surface), .55); }
</style>
