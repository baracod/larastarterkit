import { Extension } from '@tiptap/core'
import type { Node as ProseMirrorNode } from '@tiptap/pm/model'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'
import { Prism, grammarFor, parseCodeInfo } from '../../../publishing/.vitepress/theme/prism'

type Token = string | { type: string; alias?: string | string[]; content: Token | Token[]; length: number }

function flatten(tokens: Token[], classes: string[] = []): { text: string; classes: string[] }[] {
  return tokens.flatMap(token => {
    if (typeof token === 'string')
      return [{ text: token, classes }]
    const aliases = Array.isArray(token.alias) ? token.alias : token.alias ? [token.alias] : []
    const nested = Array.isArray(token.content) ? token.content : [token.content]

    return flatten(nested, [...classes, 'token', token.type, ...aliases])
  })
}

function decorate(doc: ProseMirrorNode) {
  const decorations: Decoration[] = []

  doc.descendants((node, position) => {
    if (node.type.name !== 'codeBlock')
      return true
    const found = grammarFor(parseCodeInfo(node.attrs.language || '').language)
    if (!found)
      return false
    let from = position + 1
    for (const part of flatten(Prism.tokenize(node.textContent, found.grammar) as Token[])) {
      const to = from + part.text.length
      if (part.classes.length)
        decorations.push(Decoration.inline(from, to, { class: [...new Set(part.classes)].join(' ') }))
      from = to
    }

    return false
  })

  return DecorationSet.create(doc, decorations)
}

/** Live syntax highlighting inside the editor, with the same Prism grammars as the portal. */
export const CodeHighlight = Extension.create({
  name: 'codeHighlight',
  addProseMirrorPlugins() {
    const key = new PluginKey('codeHighlight')

    return [new Plugin({
      key,
      state: {
        init: (_, { doc }) => decorate(doc),
        apply: (transaction, set) => transaction.docChanged ? decorate(transaction.doc) : set.map(transaction.mapping, transaction.doc),
      },
      props: { decorations: state => key.getState(state) },
    })]
  },
})
