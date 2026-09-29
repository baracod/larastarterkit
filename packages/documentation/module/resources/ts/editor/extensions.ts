import type { AnyExtension, NodeViewRenderer } from '@tiptap/core'
import { CodeBlock } from '@tiptap/extension-code-block'
import { StarterKit } from '@tiptap/starter-kit'
import { Image } from '@tiptap/extension-image'
import { Markdown } from '@tiptap/markdown'
import { TableKit } from '@tiptap/extension-table'
import { TaskList } from '@tiptap/extension-task-list'
import { TaskItem } from '@tiptap/extension-task-item'
import { Placeholder } from '@tiptap/extension-placeholder'
import { Callout } from './callout'
import { CodeHighlight } from './codeHighlight'

// Stored attributes keep doc-image IDs; only the DOM gets the authenticated URL.
const PrivateImage = Image.extend({
  renderHTML({ HTMLAttributes }) {
    const src = String(HTMLAttributes.src || '')
    const match = src.match(/^doc-image:(\d+)$/)
    const safe = match ? `/api/v1/documentation/images/${match[1]}/file` : /^https?:\/\//i.test(src) ? src : ''

    return ['img', { ...HTMLAttributes, src: safe, loading: 'lazy' }]
  },
})

export interface EditorExtensionOptions {

  /** Vue node view for code blocks (header with language, title and copy); omitted in tests. */
  codeBlockView?: () => NodeViewRenderer
  highlight?: boolean
}

export function editorExtensions(placeholder: () => string = () => '', options: EditorExtensionOptions = {}): AnyExtension[] {
  return [
    StarterKit.configure({
      underline: false,
      heading: { levels: [1, 2, 3, 4] },
      codeBlock: false,
      link: { openOnClick: false, protocols: ['http', 'https', 'mailto'] },
    }),
    options.codeBlockView ? CodeBlock.extend({ addNodeView: options.codeBlockView }) : CodeBlock,
    Markdown,
    PrivateImage,
    TableKit.configure({ table: { resizable: false } }),
    TaskList,
    TaskItem.configure({ nested: true }),
    Callout,
    ...(options.highlight ? [CodeHighlight] : []),
    Placeholder.configure({ placeholder, includeChildren: false }),
  ]
}
