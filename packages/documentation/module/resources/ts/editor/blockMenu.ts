import type { EditorState } from '@tiptap/pm/state'

export function shouldOpenBlockMenu(state: EditorState, event: Pick<KeyboardEvent, 'key' | 'isComposing'>): boolean {
  const { selection } = state
  const parent = selection.$from.parent

  return event.key === '/' && !event.isComposing && selection.empty
    && parent.type.name === 'paragraph' && parent.content.size === 0
}
