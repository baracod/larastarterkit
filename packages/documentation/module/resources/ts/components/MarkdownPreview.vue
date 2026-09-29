<script setup lang="ts">
import { useTheme } from 'vuetify'
import { enhanceCode } from '../../../publishing/.vitepress/theme/code'
import { renderDiagrams } from '../../../publishing/.vitepress/theme/mermaid'
import '../../../publishing/.vitepress/theme/content.css'

const props = defineProps<{ html: string; title?: string; codeTheme?: string; loading?: boolean }>()
const { t } = useI18n()
const theme = useTheme()
const root = ref<HTMLElement | null>(null)
const dark = computed(() => theme.current.value.dark)

async function enhance() {
  await nextTick()
  enhanceCode(root.value, { copy: t('Documentation.copy'), copied: t('Documentation.copied'), copyAria: t('Documentation.copy'), selectCode: t('Documentation.copy') })
  await renderDiagrams(root.value, dark.value)
}
watch(() => props.html, enhance, { immediate: true })
</script>

<template>
  <article
    class="markdown-preview"
    :class="[`cms-code-${codeTheme || 'auto'}`, { dark, 'is-loading': loading }]"
  >
    <h1
      v-if="title && !/^\s*<h1[\s>]/.test(html)"
      class="preview-title"
    >
      {{ title }}
    </h1>
    <!-- The API strips raw HTML and unsafe links before rendering Markdown. -->
    <div
      ref="root"
      v-html="html"
    />
    <p
      v-if="!html && !loading"
      class="preview-empty"
    >
      {{ t('Documentation.previewEmpty') }}
    </p>
  </article>
</template>

<style scoped>
.markdown-preview { --vp-c-brand-1: rgb(var(--v-theme-primary)); max-width: 820px; margin: 0 auto; padding: 32px clamp(16px, 5vw, 56px) 80px; font-size: 16px; line-height: 1.75; overflow-wrap: anywhere; color: rgba(var(--v-theme-on-surface), .9); transition: opacity .2s; }
.markdown-preview.is-loading { opacity: .55; }
.preview-title, .markdown-preview :deep(h1) { font-size: 2.1rem; line-height: 1.25; font-weight: 700; letter-spacing: -.02em; margin: 0 0 .6em; }
.markdown-preview :deep(h2) { font-size: 1.55rem; font-weight: 650; margin: 1.8em 0 .6em; padding-top: .8em; border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.markdown-preview :deep(h3) { font-size: 1.25rem; font-weight: 600; margin: 1.5em 0 .5em; }
.markdown-preview :deep(h4) { font-size: 1.05rem; font-weight: 600; margin: 1.3em 0 .5em; }
.markdown-preview :deep(p), .markdown-preview :deep(ul), .markdown-preview :deep(ol) { margin: .9em 0; }
.markdown-preview :deep(ul), .markdown-preview :deep(ol) { padding-inline-start: 1.6rem; }
.markdown-preview :deep(li:has(> input[type=checkbox])) { list-style: none; margin-inline-start: -1.4rem; }
.markdown-preview :deep(a) { color: rgb(var(--v-theme-primary)); text-underline-offset: 3px; }
.markdown-preview :deep(code:not(pre code)) { padding: .15em .4em; border-radius: 5px; background: rgba(var(--v-theme-primary), .1); color: rgb(var(--v-theme-primary)); font-size: .88em; }
.markdown-preview :deep(blockquote:not(.cms-callout)) { padding: 4px 18px; border-inline-start: 4px solid rgba(var(--v-theme-on-surface), .2); color: rgba(var(--v-theme-on-surface), .7); }
.markdown-preview :deep(img) { display: block; max-width: 100%; margin: 1.2em auto; border-radius: 10px; box-shadow: 0 6px 24px rgba(0, 0, 0, .1); }
.markdown-preview :deep(table) { display: block; overflow-x: auto; border-collapse: collapse; margin: 1.2em 0; }
.markdown-preview :deep(th) { background: rgba(var(--v-theme-on-surface), .04); text-align: start; }
.markdown-preview :deep(td), .markdown-preview :deep(th) { padding: 8px 12px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.markdown-preview :deep(hr) { margin: 2em 0; border: 0; border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.markdown-preview :deep(.cms-diagram) { display: flex; justify-content: center; padding: 16px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 12px; overflow: auto; }
.preview-empty { color: rgba(var(--v-theme-on-surface), .5); font-style: italic; }
</style>
