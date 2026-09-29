<script setup lang="ts">
import { NodeViewContent, NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3'
import { formatCodeInfo, parseCodeInfo } from '../../../../publishing/.vitepress/theme/prism'

const props = defineProps(nodeViewProps)
const { t } = useI18n()
const languages = ['text', 'bash', 'shell', 'php', 'javascript', 'typescript', 'jsx', 'tsx', 'vue', 'html', 'css', 'json', 'yaml', 'toml', 'ini', 'sql', 'python', 'go', 'rust', 'java', 'csharp', 'c', 'docker', 'nginx', 'powershell', 'diff', 'markdown', 'mermaid']
const meta = computed(() => parseCodeInfo(props.node.attrs.language || 'text'))
const copied = ref(false)
const metaOpen = ref(false)

// Always visible once a title or highlighted lines exist, even when set from Markdown mode.
const hasMeta = computed(() => !!meta.value.title || !!meta.value.lines)
const showMeta = computed(() => metaOpen.value || hasMeta.value)

function update(patch: Partial<ReturnType<typeof parseCodeInfo>>) {
  props.updateAttributes({ language: formatCodeInfo({ ...meta.value, ...patch }) })
}
async function copy() {
  try {
    await navigator.clipboard.writeText(props.node.textContent)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 1500)
  }
  catch { /* Clipboard may be unavailable over plain HTTP. */ }
}
</script>

<template>
  <NodeViewWrapper
    class="code-view"
    :class="{ 'is-mermaid': meta.language === 'mermaid' }"
  >
    <div
      class="code-view__header"
      contenteditable="false"
    >
      <VIcon
        :icon="meta.language === 'mermaid' ? 'tabler-schema' : meta.language === 'bash' || meta.language === 'shell' ? 'tabler-terminal-2' : 'tabler-code'"
        size="16"
      />
      <select
        class="code-view__language"
        :value="meta.language"
        :disabled="!editor.isEditable"
        :aria-label="t('Documentation.codeLanguage')"
        @change="update({ language: ($event.target as HTMLSelectElement).value })"
      >
        <option
          v-for="lang in languages.includes(meta.language) ? languages : [meta.language, ...languages]"
          :key="lang"
          :value="lang"
        >
          {{ lang }}
        </option>
      </select>
      <input
        v-if="showMeta && meta.language !== 'mermaid'"
        class="code-view__input"
        :value="meta.title"
        :placeholder="t('Documentation.codeTitle')"
        :aria-label="t('Documentation.codeTitle')"
        :readonly="!editor.isEditable"
        @change="update({ title: ($event.target as HTMLInputElement).value })"
      >
      <input
        v-if="showMeta && meta.language !== 'mermaid'"
        class="code-view__input code-view__input--small"
        :value="meta.lines"
        placeholder="1,3-5"
        :aria-label="t('Documentation.codeLines')"
        :title="t('Documentation.codeLines')"
        :readonly="!editor.isEditable"
        @change="update({ lines: ($event.target as HTMLInputElement).value })"
      >
      <VSpacer />
      <button
        v-if="meta.language !== 'mermaid' && editor.isEditable && !hasMeta"
        type="button"
        class="code-view__action"
        :aria-pressed="showMeta"
        :title="t('Documentation.codeOptions')"
        @click="metaOpen = !metaOpen"
      >
        <VIcon
          icon="tabler-adjustments-horizontal"
          size="15"
        />
      </button>
      <button
        type="button"
        class="code-view__action"
        :title="t('Documentation.copy')"
        @click="copy"
      >
        <VIcon
          :icon="copied ? 'tabler-check' : 'tabler-copy'"
          size="15"
        />
      </button>
    </div>
    <pre class="code-view__body"><NodeViewContent as="code" /></pre>
    <div
      v-if="meta.language === 'mermaid'"
      class="code-view__hint"
      contenteditable="false"
    >
      {{ t('Documentation.mermaidHint') }}
    </div>
  </NodeViewWrapper>
</template>
