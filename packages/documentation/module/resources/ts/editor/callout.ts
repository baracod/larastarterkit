import { Node, mergeAttributes } from '@tiptap/core'

export const calloutTypes = ['note', 'tip', 'important', 'warning', 'caution'] as const
export type CalloutType = (typeof calloutTypes)[number]

const marker = /^ {0,3}> ?\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*([^\n]*)/i

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    callout: {
      setCallout: (type: CalloutType) => ReturnType
      updateCallout: (type: CalloutType, title?: string) => ReturnType
      unsetCallout: () => ReturnType
    }
  }
}

/**
 * GitHub-style alert ("> [!TIP]") edited as a real block, stored as standard Markdown
 * and rendered by the server as a VitePress-like callout.
 */
export const Callout = Node.create({
  name: 'callout',
  group: 'block',
  content: 'block+',
  defining: true,

  addAttributes() {
    return {
      type: {
        default: 'note',
        parseHTML: element => element.getAttribute('data-callout') || 'note',
        renderHTML: attributes => ({ 'data-callout': attributes.type }),
      },

      // Optional custom title written after the marker: "> [!TIP] My title".
      title: {
        default: '',
        parseHTML: element => element.getAttribute('data-title') || '',
        renderHTML: attributes => attributes.title ? { 'data-title': attributes.title } : {},
      },
    }
  },

  parseHTML() {
    return [{ tag: 'div[data-callout]' }]
  },

  renderHTML({ node, HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { class: `cms-callout cms-callout-${node.attrs.type}` }), 0]
  },

  markdownTokenizer: {
    name: 'callout',
    level: 'block',
    start: (src: string) => {
      const match = src.match(/^ {0,3}> ?\[!(?:NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]/im)

      return match?.index ?? -1
    },
    tokenize(src, _tokens, lexer) {
      const head = src.match(marker)
      if (!head)
        return undefined
      const lines = src.split('\n')
      let count = 0
      while (count < lines.length && /^ {0,3}>/.test(lines[count]))
        count++
      const raw = lines.slice(0, count).join('\n')
      const body = lines.slice(1, count).map(line => line.replace(/^ {0,3}> ?/, '')).join('\n')

      return { type: 'callout', raw: count < lines.length ? `${raw}\n` : raw, calloutType: head[1].toLowerCase(), calloutTitle: head[2].trim(), tokens: lexer.blockTokens(body) }
    },
  },

  parseMarkdown(token, helpers) {
    const children = helpers.parseChildren(token.tokens || [])

    return helpers.createNode('callout', { type: token.calloutType || 'note', title: token.calloutTitle || '' }, children.length ? children : [helpers.createNode('paragraph', undefined, [])])
  },

  renderMarkdown(node, helpers) {
    const body = (node.content || []).map((child: any, index: number) => helpers.renderChild?.(child, index) ?? helpers.renderChildren([child])).join('\n\n')
    const lines = body.split('\n').map((line: string) => line.trim() === '' ? '>' : `> ${line}`)

    const title = String(node.attrs?.title || '').replace(/\s+/g, ' ').trim()

    return [`> [!${String(node.attrs?.type || 'note').toUpperCase()}]${title ? ` ${title}` : ''}`, ...lines].join('\n')
  },

  addCommands() {
    return {
      setCallout: type => ({ commands }) => commands.wrapIn(this.name, { type }),
      updateCallout: (type, title) => ({ commands }) => commands.updateAttributes(this.name, title === undefined ? { type } : { type, title }),
      unsetCallout: () => ({ commands }) => commands.lift(this.name),
    }
  },
})
