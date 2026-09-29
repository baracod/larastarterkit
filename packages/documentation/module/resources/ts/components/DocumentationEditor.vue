<script setup lang="ts">
import { EditorContent, VueNodeViewRenderer, useEditor } from '@tiptap/vue-3'
import { BubbleMenu } from '@tiptap/vue-3/menus'
import { useTheme } from 'vuetify'
import { shouldOpenBlockMenu } from '../editor/blockMenu'
import { editorExtensions } from '../editor/extensions'
import { calloutTypes } from '../editor/callout'
import type { CalloutType } from '../editor/callout'
import { filterSlashCommands } from '../editor/slashCommands'
import { outline } from '../editor/text'
import type { SlashCommand } from '../editor/slashCommands'
import CmsSegmented from './ui/CmsSegmented.vue'
import CodeBlockView from './editor/CodeBlockView.vue'
import '../../../publishing/.vitepress/theme/content.css'

const props = defineProps<{ modelValue: string; readonly?: boolean; codeTheme?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string]; 'pickImage': []; 'uploadFiles': [files: File[]] }>()
const { t } = useI18n()
const vuetifyTheme = useTheme()
const mode = defineModel<'visual' | 'markdown'>('mode', { default: 'visual' })
const linkDialog = ref(false)
const linkUrl = ref('')
const linkError = ref(false)
const root = ref<HTMLElement>()
const source = ref<HTMLTextAreaElement>()
const slash = reactive({ open: false, from: 0, query: '', index: 0, top: 0, left: 0 })
let emitted = props.modelValue

const slashItems = computed(() => filterSlashCommands(slash.query, key => t(`Documentation.blocks.${key}`)))
const imageFiles = (files?: FileList | null) => Array.from(files || []).filter(file => file.type.startsWith('image/'))

const editor = useEditor({
  extensions: editorExtensions(() => t('Documentation.visualPlaceholder'), { highlight: true, codeBlockView: () => VueNodeViewRenderer(CodeBlockView as any) }),
  content: props.modelValue,
  contentType: 'markdown',
  editable: !props.readonly,
  editorProps: {
    attributes: { 'class': 'documentation-prose', 'aria-label': t('Documentation.visualEditor'), 'role': 'textbox', 'aria-multiline': 'true', 'spellcheck': 'true' },
    handleKeyDown: (_view, event) => {
      if (slash.open)
        return slashKey(event)
      if (editor.value && shouldOpenBlockMenu(editor.value.state, event)) {
        openSlash()

        return false
      }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        openLink()

        return true
      }

      return false
    },
    handlePaste: (_view, event) => {
      const files = imageFiles(event.clipboardData?.files)
      if (!files.length || props.readonly)
        return false
      emit('uploadFiles', files)

      return true
    },
    handleDrop: (_view, event) => {
      const files = imageFiles((event as DragEvent).dataTransfer?.files)
      if (!files.length || props.readonly)
        return false
      event.preventDefault()
      emit('uploadFiles', files)

      return true
    },
  },
  onUpdate: ({ editor: instance }) => {
    emitted = instance.getMarkdown()
    emit('update:modelValue', emitted)
    updateSlash()
  },
  onSelectionUpdate: () => updateSlash(),
  onBlur: () => setTimeout(() => {
    if (!root.value?.contains(document.activeElement))
      slash.open = false
  }, 150),
})

// The Markdown source is the reference while typing in it; the visual document is rebuilt once when switching back.
function syncFromSource(value: string) {
  if (value !== emitted) {
    emitted = value
    editor.value?.commands.setContent(value, { contentType: 'markdown', emitUpdate: false })
  }
}
watch(() => props.modelValue, value => mode.value === 'visual' && syncFromSource(value))
watch(mode, value => value === 'visual' && syncFromSource(props.modelValue))
watch(() => props.readonly, value => editor.value?.setEditable(!value, false))
onBeforeUnmount(() => editor.value?.destroy())

function openSlash() {
  const instance = editor.value
  if (!instance || !root.value)
    return
  const from = instance.state.selection.from
  const coords = instance.view.coordsAtPos(from)
  const box = root.value.getBoundingClientRect()

  Object.assign(slash, { open: true, from, query: '', index: 0, top: coords.bottom - box.top + 6, left: Math.min(coords.left - box.left, box.width - 320) })
}
function updateSlash() {
  const instance = editor.value
  if (!slash.open || !instance)
    return
  const { from } = instance.state.selection
  const text = from > slash.from ? instance.state.doc.textBetween(slash.from, from, '\n') : ''
  if (!text.startsWith('/') || text.length > 40 || text.includes('\n')) {
    slash.open = false

    return
  }
  slash.query = text.slice(1)
  slash.index = Math.min(slash.index, Math.max(0, slashItems.value.length - 1))
}
function slashKey(event: KeyboardEvent) {
  const count = slashItems.value.length
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    if (count)
      slash.index = (slash.index + (event.key === 'ArrowDown' ? 1 : -1) + count) % count
    event.preventDefault()

    return true
  }
  if (event.key === 'Enter' || event.key === 'Tab') {
    if (!count) {
      slash.open = false

      return false
    }
    event.preventDefault()
    runSlash(slashItems.value[slash.index])

    return true
  }
  if (event.key === 'Escape') {
    slash.open = false
    event.preventDefault()

    return true
  }

  return false
}
function runSlash(command: SlashCommand) {
  const instance = editor.value
  if (!instance)
    return
  slash.open = false
  instance.chain().focus().deleteRange({ from: slash.from, to: instance.state.selection.from }).run()
  insertBlock(command.key)
}

function insertBlock(key: string) {
  const chain = () => editor.value!.chain().focus()
  if (calloutTypes.includes(key as CalloutType)) {
    editor.value?.isActive('callout') ? chain().updateCallout(key as CalloutType).run() : chain().setCallout(key as CalloutType).run()

    return
  }

  const actions: Record<string, () => unknown> = {
    paragraph: () => chain().setParagraph().run(),
    heading1: () => chain().setHeading({ level: 1 }).run(),
    heading2: () => chain().setHeading({ level: 2 }).run(),
    heading3: () => chain().setHeading({ level: 3 }).run(),
    heading4: () => chain().setHeading({ level: 4 }).run(),
    bulletList: () => chain().toggleBulletList().run(),
    orderedList: () => chain().toggleOrderedList().run(),
    taskList: () => chain().toggleTaskList().run(),
    codeBlock: () => chain().setCodeBlock({ language: 'text' }).run(),
    terminal: () => chain().setCodeBlock({ language: 'bash' }).run(),
    mermaid: () => chain().insertContent({ type: 'codeBlock', attrs: { language: 'mermaid' }, content: [{ type: 'text', text: 'flowchart LR\n  A[Départ] --> B{Choix}\n  B -->|Oui| C[Arrivée]' }] }).run(),
    table: () => chain().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
    quote: () => chain().toggleBlockquote().run(),
    divider: () => chain().setHorizontalRule().run(),
    image: () => emit('pickImage'),
  }

  actions[key]?.()
}

const marks = computed(() => [
  { key: 'bold', icon: 'tabler-bold', active: 'bold', shortcut: 'Ctrl+B', run: () => editor.value?.chain().focus().toggleBold().run() },
  { key: 'italic', icon: 'tabler-italic', active: 'italic', shortcut: 'Ctrl+I', run: () => editor.value?.chain().focus().toggleItalic().run() },
  { key: 'strike', icon: 'tabler-strikethrough', active: 'strike', shortcut: 'Ctrl+Shift+S', run: () => editor.value?.chain().focus().toggleStrike().run() },
  { key: 'inlineCode', icon: 'tabler-code', active: 'code', shortcut: 'Ctrl+E', run: () => editor.value?.chain().focus().toggleCode().run() },
])

const lists = computed(() => [
  { key: 'bulletList', icon: 'tabler-list', active: 'bulletList' },
  { key: 'orderedList', icon: 'tabler-list-numbers', active: 'orderedList' },
  { key: 'taskList', icon: 'tabler-list-check', active: 'taskList' },
])

const blockStyle = computed(() => {
  for (const level of [1, 2, 3, 4]) {
    if (editor.value?.isActive('heading', { level }))
      return t('Documentation.heading', { level })
  }

  return t('Documentation.paragraph')
})

// Localized callout badges for the editor canvas (CSS reads them as custom properties).
const calloutLabels = computed(() => Object.fromEntries(calloutTypes.map(type => [`--callout-label-${type}`, JSON.stringify(t(`Documentation.blocks.${type}`))])))
const calloutIcons: Record<CalloutType, string> = { note: 'tabler-info-circle', tip: 'tabler-bulb', important: 'tabler-star', warning: 'tabler-alert-triangle', caution: 'tabler-alert-octagon' }

function setLink() {
  if (linkUrl.value && !/^(?:https?:\/\/|mailto:|\/(?!\/)|#)/i.test(linkUrl.value)) {
    linkError.value = true

    return
  }
  const chain = editor.value?.chain().focus().extendMarkRange('link')

  if (linkUrl.value)
    chain?.setLink({ href: linkUrl.value }).run()
  else
    chain?.unsetLink().run()
  linkDialog.value = false
}
function openLink() {
  if (props.readonly)
    return
  linkUrl.value = editor.value?.getAttributes('link').href || ''
  linkError.value = false
  linkDialog.value = true
}

/** Markdown mode: Tab indents instead of leaving the field. */
function sourceKey(event: KeyboardEvent) {
  if (event.key !== 'Tab' || props.readonly)
    return
  event.preventDefault()

  const area = event.target as HTMLTextAreaElement
  const { selectionStart: start, selectionEnd: end, value } = area

  area.value = `${value.slice(0, start)}  ${value.slice(end)}`
  area.selectionStart = area.selectionEnd = start + 2
  emit('update:modelValue', area.value)
}

function insertImage(id: number, alt: string) {
  const text = alt.replace(/[[\]\\]/g, '')
  if (mode.value === 'visual') {
    editor.value?.chain().focus().setImage({ src: `doc-image:${id}`, alt: text }).run()
  }
  else {
    const area = source.value
    const snippet = `\n![${text}](doc-image:${id})\n`
    const at = area?.selectionStart ?? props.modelValue.length

    emit('update:modelValue', `${props.modelValue.slice(0, at)}${snippet}${props.modelValue.slice(at)}`)
  }
}

/** Scrolls to the n-th heading of the document (used by the outline panel). */
function focusHeading(index: number) {
  if (mode.value === 'markdown') {
    // Same fence-aware heading list as the outline panel, so indices always match.
    const line = outline(props.modelValue)[index]?.line ?? -1

    source.value?.focus()
    if (line >= 0 && source.value) {
      const offset = props.modelValue.split('\n').slice(0, line).join('\n').length + (line ? 1 : 0)

      source.value.setSelectionRange(offset, offset)
      source.value.scrollTop = line * 22 - 40
    }

    return
  }

  const headings = root.value?.querySelectorAll('.documentation-prose > h1, .documentation-prose > h2, .documentation-prose > h3, .documentation-prose > h4')

  headings?.[index]?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}
defineExpose({ insertImage, focusHeading })
</script>

<template>
  <div
    ref="root"
    class="documentation-editor"
    :class="[`cms-code-${codeTheme || 'auto'}`, { 'dark': vuetifyTheme.current.value.dark, 'is-readonly': readonly }]"
    :style="calloutLabels"
  >
    <div
      v-if="mode === 'visual' && editor"
      class="editor-toolbar"
      role="toolbar"
      :aria-label="t('Documentation.formatting')"
    >
      <div class="toolbar-group">
        <VBtn
          icon="tabler-arrow-back-up"
          :aria-label="t('Documentation.undo')"
          :title="`${t('Documentation.undo')} (Ctrl+Z)`"
          size="small"
          variant="text"
          :disabled="readonly || !editor.can().undo()"
          @click="editor.chain().focus().undo().run()"
        />
        <VBtn
          icon="tabler-arrow-forward-up"
          :aria-label="t('Documentation.redo')"
          :title="`${t('Documentation.redo')} (Ctrl+Shift+Z)`"
          size="small"
          variant="text"
          :disabled="readonly || !editor.can().redo()"
          @click="editor.chain().focus().redo().run()"
        />
      </div>
      <div class="toolbar-group">
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              :disabled="readonly"
              variant="text"
              size="small"
              class="text-none block-style"
              append-icon="tabler-chevron-down"
            >
              {{ blockStyle }}
            </VBtn>
          </template>
          <VList density="compact">
            <VListItem
              prepend-icon="tabler-pilcrow"
              :title="t('Documentation.paragraph')"
              @click="insertBlock('paragraph')"
            />
            <VListItem
              v-for="level in [1, 2, 3, 4]"
              :key="level"
              :prepend-icon="`tabler-h-${level}`"
              :title="t('Documentation.heading', { level })"
              :active="editor.isActive('heading', { level })"
              @click="insertBlock(`heading${level}`)"
            />
          </VList>
        </VMenu>
      </div>
      <div class="toolbar-group">
        <VBtn
          v-for="tool in marks"
          :key="tool.key"
          :icon="tool.icon"
          :aria-label="t(`Documentation.${tool.key}`)"
          :title="`${t(`Documentation.${tool.key}`)} (${tool.shortcut})`"
          :aria-pressed="editor.isActive(tool.active)"
          :color="editor.isActive(tool.active) ? 'primary' : undefined"
          :variant="editor.isActive(tool.active) ? 'tonal' : 'text'"
          size="small"
          :disabled="readonly"
          @click="tool.run"
        />
        <VBtn
          icon="tabler-link"
          :aria-label="t('Documentation.link')"
          :title="`${t('Documentation.link')} (Ctrl+K)`"
          :color="editor.isActive('link') ? 'primary' : undefined"
          :variant="editor.isActive('link') ? 'tonal' : 'text'"
          size="small"
          :disabled="readonly"
          @click="openLink"
        />
      </div>
      <div class="toolbar-group">
        <VBtn
          v-for="tool in lists"
          :key="tool.key"
          :icon="tool.icon"
          :aria-label="t(`Documentation.${tool.key}`)"
          :title="t(`Documentation.${tool.key}`)"
          :aria-pressed="editor.isActive(tool.active)"
          :color="editor.isActive(tool.active) ? 'primary' : undefined"
          :variant="editor.isActive(tool.active) ? 'tonal' : 'text'"
          size="small"
          :disabled="readonly"
          @click="insertBlock(tool.key)"
        />
      </div>
      <div class="toolbar-group">
        <VBtn
          icon="tabler-blockquote"
          :aria-label="t('Documentation.quote')"
          :title="t('Documentation.quote')"
          :variant="editor.isActive('blockquote') ? 'tonal' : 'text'"
          :color="editor.isActive('blockquote') ? 'primary' : undefined"
          size="small"
          :disabled="readonly"
          @click="insertBlock('quote')"
        />
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              icon="tabler-info-square-rounded"
              :aria-label="t('Documentation.callout')"
              :title="t('Documentation.callout')"
              :variant="editor.isActive('callout') ? 'tonal' : 'text'"
              :color="editor.isActive('callout') ? 'primary' : undefined"
              size="small"
              :disabled="readonly"
            />
          </template>
          <VList density="compact">
            <VListItem
              v-for="type in calloutTypes"
              :key="type"
              :prepend-icon="calloutIcons[type]"
              :title="t(`Documentation.blocks.${type}`)"
              @click="insertBlock(type)"
            />
          </VList>
        </VMenu>
        <VBtn
          icon="tabler-source-code"
          :aria-label="t('Documentation.codeBlock')"
          :title="t('Documentation.codeBlock')"
          size="small"
          variant="text"
          :disabled="readonly"
          @click="insertBlock('codeBlock')"
        />
        <VBtn
          icon="tabler-terminal-2"
          :aria-label="t('Documentation.blocks.terminal')"
          :title="t('Documentation.blocks.terminal')"
          size="small"
          variant="text"
          :disabled="readonly"
          @click="insertBlock('terminal')"
        />
        <VBtn
          icon="tabler-schema"
          :aria-label="t('Documentation.mermaid')"
          :title="t('Documentation.mermaid')"
          size="small"
          variant="text"
          :disabled="readonly"
          @click="insertBlock('mermaid')"
        />
        <VBtn
          icon="tabler-table-plus"
          :aria-label="t('Documentation.insertTable')"
          :title="t('Documentation.insertTable')"
          size="small"
          variant="text"
          :disabled="readonly || editor.isActive('table')"
          @click="insertBlock('table')"
        />
        <VBtn
          icon="tabler-photo-plus"
          :aria-label="t('Documentation.insertImage')"
          :title="t('Documentation.insertImage')"
          size="small"
          variant="text"
          :disabled="readonly"
          @click="insertBlock('image')"
        />
        <VBtn
          icon="tabler-separator-horizontal"
          :aria-label="t('Documentation.divider')"
          :title="t('Documentation.divider')"
          size="small"
          variant="text"
          :disabled="readonly"
          @click="insertBlock('divider')"
        />
      </div>
    </div>
    <div
      v-if="mode === 'visual' && editor && !readonly && (editor.isActive('table') || editor.isActive('callout'))"
      class="context-toolbar"
    >
      <template v-if="editor.isActive('table')">
        <span class="context-label"><VIcon
          icon="tabler-table"
          size="16"
        /> {{ t('Documentation.table') }}</span>
        <VBtn
          size="x-small"
          variant="tonal"
          prepend-icon="tabler-row-insert-top"
          @click="editor.chain().focus().addRowBefore().run()"
        >
          {{ t('Documentation.rowAbove') }}
        </VBtn>
        <VBtn
          size="x-small"
          variant="tonal"
          prepend-icon="tabler-row-insert-bottom"
          @click="editor.chain().focus().addRowAfter().run()"
        >
          {{ t('Documentation.addRow') }}
        </VBtn>
        <VBtn
          size="x-small"
          variant="tonal"
          prepend-icon="tabler-column-insert-right"
          @click="editor.chain().focus().addColumnAfter().run()"
        >
          {{ t('Documentation.addColumn') }}
        </VBtn>
        <VBtn
          size="x-small"
          variant="text"
          prepend-icon="tabler-row-remove"
          @click="editor.chain().focus().deleteRow().run()"
        >
          {{ t('Documentation.deleteRow') }}
        </VBtn>
        <VBtn
          size="x-small"
          variant="text"
          prepend-icon="tabler-column-remove"
          @click="editor.chain().focus().deleteColumn().run()"
        >
          {{ t('Documentation.deleteColumn') }}
        </VBtn>
        <VBtn
          size="x-small"
          variant="text"
          color="error"
          prepend-icon="tabler-trash"
          @click="editor.chain().focus().deleteTable().run()"
        >
          {{ t('Documentation.deleteTable') }}
        </VBtn>
      </template>
      <template v-else>
        <span class="context-label"><VIcon
          icon="tabler-info-square-rounded"
          size="16"
        /> {{ t('Documentation.callout') }}</span>
        <CmsSegmented
          :model-value="editor.getAttributes('callout').type"
          size="small"
          :label="t('Documentation.callout')"
          :items="calloutTypes.map(type => ({ value: type, icon: calloutIcons[type], label: t(`Documentation.blocks.${type}`) }))"
          @update:model-value="editor.chain().focus().updateCallout($event).run()"
        />
        <input
          class="callout-title-input"
          :value="editor.getAttributes('callout').title"
          :placeholder="t('Documentation.calloutTitle')"
          :aria-label="t('Documentation.calloutTitle')"
          maxlength="120"
          data-test="callout-title"
          @input="editor.chain().updateCallout(editor.getAttributes('callout').type, ($event.target as HTMLInputElement).value).run()"
        >
        <VBtn
          size="x-small"
          variant="text"
          prepend-icon="tabler-x"
          @click="editor.chain().focus().unsetCallout().run()"
        >
          {{ t('Documentation.removeCallout') }}
        </VBtn>
      </template>
    </div>
    <div
      v-show="mode === 'visual'"
      class="editor-canvas"
    >
      <EditorContent :editor="editor" />
      <!-- Tiptap moves the menu element itself: keep it in a stable host Vue never re-orders. -->
      <div class="bubble-host">
        <BubbleMenu
          v-if="editor"
          :editor="editor"
          :should-show="({ editor: instance, state }) => !readonly && !state.selection.empty && !instance.isActive('codeBlock') && !instance.isActive('image')"
        >
          <div class="bubble-menu">
            <button
              v-for="tool in marks"
              :key="tool.key"
              type="button"
              :class="{ 'is-active': editor.isActive(tool.active) }"
              :aria-label="t(`Documentation.${tool.key}`)"
              :title="t(`Documentation.${tool.key}`)"
              @click="tool.run"
            >
              <VIcon
                :icon="tool.icon"
                size="17"
              />
            </button>
            <span class="bubble-divider" />
            <button
              type="button"
              :class="{ 'is-active': editor.isActive('link') }"
              :aria-label="t('Documentation.link')"
              :title="t('Documentation.link')"
              @click="openLink"
            >
              <VIcon
                icon="tabler-link"
                size="17"
              />
            </button>
            <button
              v-for="level in [2, 3]"
              :key="level"
              type="button"
              :class="{ 'is-active': editor.isActive('heading', { level }) }"
              :aria-label="t('Documentation.heading', { level })"
              :title="t('Documentation.heading', { level })"
              @click="editor.chain().focus().toggleHeading({ level: level as 2 | 3 }).run()"
            >
              <VIcon
                :icon="`tabler-h-${level}`"
                size="17"
              />
            </button>
          </div>
        </BubbleMenu>
      </div>
      <div
        v-if="slash.open"
        class="slash-menu"
        role="listbox"
        :aria-label="t('Documentation.insertBlock')"
        :style="{ top: `${slash.top}px`, left: `${Math.max(8, slash.left)}px` }"
        @mousedown.prevent
      >
        <p class="slash-title">
          {{ slash.query ? t('Documentation.slashFilter', { query: slash.query }) : t('Documentation.insertBlock') }}
        </p>
        <button
          v-for="(command, index) in slashItems"
          :key="command.key"
          type="button"
          role="option"
          class="slash-item"
          :class="{ 'is-active': index === slash.index }"
          :aria-selected="index === slash.index"
          @mouseenter="slash.index = index"
          @click="runSlash(command)"
        >
          <span class="slash-icon"><VIcon
            :icon="command.icon"
            size="18"
          /></span>
          <span>
            <strong>{{ t(`Documentation.blocks.${command.key}`) }}</strong>
            <small>{{ t(`Documentation.blockHints.${command.key}`) }}</small>
          </span>
        </button>
        <p
          v-if="!slashItems.length"
          class="slash-empty"
        >
          {{ t('Documentation.noBlock') }}
        </p>
      </div>
    </div>
    <textarea
      v-if="mode === 'markdown'"
      ref="source"
      class="markdown-source"
      :value="modelValue"
      :readonly="readonly"
      :aria-label="t('Documentation.markdown')"
      spellcheck="false"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
      @keydown="sourceKey"
    />
    <VDialog
      v-model="linkDialog"
      max-width="520"
    >
      <VCard :title="t('Documentation.link')">
        <VCardText>
          <CoreTextField
            v-model="linkUrl"
            md="12"
            autofocus
            :label="t('Documentation.linkUrl')"
            placeholder="https://… · /guide/v1/page.html · #section"
            :error-messages="linkError ? t('Documentation.invalidLink') : ''"
            @keydown.enter.prevent="setLink"
          />
        </VCardText>
        <VCardActions>
          <VBtn
            v-if="editor?.isActive('link')"
            color="error"
            variant="text"
            @click="linkUrl = ''; setLink()"
          >
            {{ t('Documentation.removeLink') }}
          </VBtn>
          <VSpacer />
          <VBtn @click="linkDialog = false">
            {{ t('Documentation.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            @click="setLink"
          >
            {{ t('Documentation.apply') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.documentation-editor { position: relative; display: flex; flex-direction: column; min-height: 100%; background: rgb(var(--v-theme-surface)); }
.editor-toolbar { position: sticky; top: 0; z-index: 3; display: flex; flex-wrap: wrap; align-items: center; gap: 2px; padding: 6px 10px; background: rgba(var(--v-theme-surface), .92); backdrop-filter: blur(8px); border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.toolbar-group { display: flex; align-items: center; gap: 1px; padding-inline: 4px; }
.toolbar-group + .toolbar-group { border-inline-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.block-style { min-width: 118px; justify-content: space-between; }
.context-toolbar { position: sticky; top: 49px; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 6px 14px; background: rgba(var(--v-theme-primary), .06); border-bottom: 1px solid rgba(var(--v-theme-primary), .18); }
.context-label { display: inline-flex; align-items: center; gap: 4px; margin-inline-end: 6px; font-size: 12px; font-weight: 600; color: rgb(var(--v-theme-primary)); }
.editor-canvas { position: relative; flex: 1; }
.markdown-source { flex: 1; width: 100%; min-height: 560px; padding: 28px clamp(16px, 5vw, 56px); border: 0; outline: none; resize: vertical; background: rgba(var(--v-theme-on-surface), .02); color: rgb(var(--v-theme-on-surface)); font: 14px/22px ui-monospace, SFMono-Regular, Menlo, monospace; tab-size: 2; }

.bubble-menu { display: flex; align-items: center; gap: 2px; padding: 4px; border-radius: 10px; background: #25293c; box-shadow: 0 10px 30px rgba(0, 0, 0, .3); }
.bubble-menu button { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 7px; color: #d0d4f1; }
.bubble-menu button:hover, .bubble-menu button.is-active { background: rgba(255, 255, 255, .12); color: #fff; }
.bubble-divider { width: 1px; height: 18px; margin-inline: 3px; background: rgba(255, 255, 255, .18); }

.callout-title-input { min-width: 140px; max-width: 240px; padding: 2px 8px; border: 1px solid rgba(var(--v-border-color), .3); border-radius: 6px; background: rgb(var(--v-theme-surface)); color: inherit; font-size: 12px; }
.slash-menu { position: absolute; z-index: 20; width: 310px; max-height: 360px; overflow: auto; padding: 6px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 12px; background: rgb(var(--v-theme-surface)); box-shadow: 0 16px 40px rgba(0, 0, 0, .18); }
.slash-title { margin: 4px 8px 6px; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: rgba(var(--v-theme-on-surface), .55); }
.slash-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 6px 8px; border-radius: 8px; text-align: start; }
.slash-item.is-active { background: rgba(var(--v-theme-primary), .1); }
.slash-item strong { display: block; font-size: 14px; font-weight: 600; }
.slash-item small { display: block; font-size: 12px; color: rgba(var(--v-theme-on-surface), .6); }
.slash-icon { display: grid; place-items: center; flex: none; width: 34px; height: 34px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 8px; background: rgba(var(--v-theme-on-surface), .03); }
.slash-item.is-active .slash-icon { border-color: rgba(var(--v-theme-primary), .5); color: rgb(var(--v-theme-primary)); }
.slash-empty { padding: 12px; font-size: 13px; color: rgba(var(--v-theme-on-surface), .6); }

:deep(.documentation-prose) { max-width: 820px; min-height: 560px; margin: 0 auto; padding: 32px clamp(16px, 5vw, 56px) 120px; outline: none; font-size: 16px; line-height: 1.75; overflow-wrap: anywhere; color: rgba(var(--v-theme-on-surface), .9); }
:deep(.documentation-prose > * + *) { margin-top: .9em; }
:deep(.documentation-prose h1) { font-size: 2.1rem; line-height: 1.25; font-weight: 700; letter-spacing: -.02em; margin-top: 1.2em; }
:deep(.documentation-prose h2) { font-size: 1.55rem; line-height: 1.3; font-weight: 650; margin-top: 1.8em; padding-top: .8em; border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
:deep(.documentation-prose h3) { font-size: 1.25rem; font-weight: 600; margin-top: 1.5em; }
:deep(.documentation-prose h4) { font-size: 1.05rem; font-weight: 600; margin-top: 1.3em; }
:deep(.documentation-prose > :first-child) { margin-top: 0; border-top: 0; padding-top: 0; }
:deep(.documentation-prose a) { color: rgb(var(--v-theme-primary)); text-decoration: underline; text-underline-offset: 3px; }
:deep(.documentation-prose code:not(pre code)) { padding: .15em .4em; border-radius: 5px; background: rgba(var(--v-theme-primary), .1); color: rgb(var(--v-theme-primary)); font-size: .88em; }
:deep(.documentation-prose ul), :deep(.documentation-prose ol) { padding-inline-start: 1.6rem; }
:deep(.documentation-prose li + li) { margin-top: .25em; }
:deep(.documentation-prose ul[data-type=taskList]) { list-style: none; padding: 0; }
:deep(.documentation-prose li[data-type=taskItem]) { display: flex; gap: 10px; align-items: baseline; }
:deep(.documentation-prose li[data-type=taskItem] > label input) { accent-color: rgb(var(--v-theme-primary)); }
:deep(.documentation-prose li[data-type=taskItem] > div) { flex: 1; }
:deep(.documentation-prose blockquote) { padding: 4px 18px; border-inline-start: 4px solid rgba(var(--v-theme-on-surface), .2); color: rgba(var(--v-theme-on-surface), .7); }
:deep(.documentation-prose .cms-callout) { position: relative; padding-top: 34px; }
:deep(.documentation-prose .cms-callout[data-title]::before) { content: attr(data-title); text-transform: none; letter-spacing: 0; font-size: 13px; }
:deep(.documentation-prose .cms-callout-note:not([data-title])::before) { content: var(--callout-label-note); }
:deep(.documentation-prose .cms-callout-tip:not([data-title])::before) { content: var(--callout-label-tip); }
:deep(.documentation-prose .cms-callout-important:not([data-title])::before) { content: var(--callout-label-important); }
:deep(.documentation-prose .cms-callout-warning:not([data-title])::before) { content: var(--callout-label-warning); }
:deep(.documentation-prose .cms-callout-caution:not([data-title])::before) { content: var(--callout-label-caution); }
:deep(.documentation-prose .cms-callout::before) { position: absolute; top: 10px; inset-inline-start: 18px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--callout); }
:deep(.documentation-prose img) { display: block; max-width: 100%; margin-inline: auto; border-radius: 10px; box-shadow: 0 6px 24px rgba(0, 0, 0, .1); }
:deep(.documentation-prose img.ProseMirror-selectednode) { outline: 3px solid rgb(var(--v-theme-primary)); }
:deep(.documentation-prose hr) { margin-block: 2em; border: 0; border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
:deep(.documentation-prose hr.ProseMirror-selectednode) { border-top-color: rgb(var(--v-theme-primary)); }
:deep(.documentation-prose .tableWrapper) { overflow-x: auto; }
:deep(.documentation-prose table) { border-collapse: collapse; width: 100%; font-size: .95em; }
:deep(.documentation-prose th) { background: rgba(var(--v-theme-on-surface), .04); font-weight: 600; text-align: start; }
:deep(.documentation-prose th), :deep(.documentation-prose td) { min-width: 80px; padding: 8px 12px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); vertical-align: top; }
:deep(.documentation-prose .selectedCell) { background: rgba(var(--v-theme-primary), .1); }
:deep(.documentation-prose .is-empty:first-child::before), :deep(.documentation-prose p.is-editor-empty:first-child::before) { content: attr(data-placeholder); float: inline-start; height: 0; pointer-events: none; color: rgba(var(--v-theme-on-surface), .38); }

:deep(.code-view) { margin-block: 1.2em; border: 1px solid var(--cms-code-border); border-radius: 12px; overflow: hidden; background: var(--cms-code-bg); color: var(--cms-code-text); }
:deep(.code-view__header) { display: flex; align-items: center; gap: 8px; padding: 5px 8px 5px 14px; background: var(--cms-code-header); border-bottom: 1px solid var(--cms-code-border); color: var(--cms-code-muted); font-size: 12px; }
:deep(.code-view__language) { padding: 2px 6px; border-radius: 6px; color: var(--cms-code-text); font-weight: 600; background: transparent; cursor: pointer; }
:deep(.code-view__language option) { color: initial; }
:deep(.code-view__input) { min-width: 0; flex: 1 1 160px; max-width: 280px; padding: 3px 8px; border: 1px solid var(--cms-code-border); border-radius: 6px; color: var(--cms-code-text); font-family: ui-monospace, monospace; }
:deep(.code-view__input--small) { flex: 0 1 90px; }
:deep(.code-view__action) { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 6px; color: var(--cms-code-muted); }
:deep(.code-view__action:hover), :deep(.code-view__action[aria-pressed=true]) { background: var(--cms-code-line); color: var(--cms-code-text); }
:deep(.code-view__body) { margin: 0; padding: 14px 20px; overflow-x: auto; }
:deep(.code-view__body code) { display: block; padding: 0; background: none; color: inherit; font: 14px/1.7 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre; }
:deep(.code-view__hint) { padding: 6px 14px; border-top: 1px dashed var(--cms-code-border); color: var(--cms-code-muted); font-size: 12px; }
:deep(.code-view .token.comment) { color: var(--cms-token-comment); font-style: italic; }
:deep(.code-view .token.keyword), :deep(.code-view .token.boolean), :deep(.code-view .token.atrule) { color: var(--cms-token-keyword); }
:deep(.code-view .token.string), :deep(.code-view .token.attr-value), :deep(.code-view .token.url) { color: var(--cms-token-string); }
:deep(.code-view .token.function), :deep(.code-view .token.class-name), :deep(.code-view .token.builtin) { color: var(--cms-token-function); }
:deep(.code-view .token.number), :deep(.code-view .token.constant), :deep(.code-view .token.variable), :deep(.code-view .token.property) { color: var(--cms-token-number); }
:deep(.code-view .token.operator), :deep(.code-view .token.punctuation) { color: var(--cms-token-punct); }
:deep(.code-view .token.tag), :deep(.code-view .token.selector), :deep(.code-view .token.attr-name) { color: var(--cms-token-tag); }
:deep(.code-view .token.deleted) { color: var(--cms-token-deleted); }
:deep(.code-view .token.inserted) { color: var(--cms-token-inserted); }
</style>
