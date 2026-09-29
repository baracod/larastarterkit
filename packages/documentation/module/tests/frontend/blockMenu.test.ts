import assert from 'node:assert/strict'
import { test } from 'node:test'
import { getSchema } from '@tiptap/vue-3'
import { EditorState, TextSelection } from '@tiptap/pm/state'
import { editorExtensions } from '../../resources/ts/editor/extensions'
import { shouldOpenBlockMenu } from '../../resources/ts/editor/blockMenu'

const schema = getSchema(editorExtensions())
const slash = { key: '/', isComposing: false }

function stateFor(nodeType: 'paragraph' | 'codeBlock' | 'heading', text = '') {
  const block = schema.nodes[nodeType].create(null, text ? schema.text(text) : undefined)

  return EditorState.create({ schema, doc: schema.nodes.doc.create(null, block) })
}

test('slash inserts a block only inside an empty ordinary paragraph', () => {
  assert.equal(shouldOpenBlockMenu(stateFor('paragraph'), slash), true)
  assert.equal(shouldOpenBlockMenu(stateFor('paragraph', 'Text'), slash), false)
  assert.equal(shouldOpenBlockMenu(stateFor('heading'), slash), false)
  assert.equal(shouldOpenBlockMenu(stateFor('paragraph'), { key: 'a', isComposing: false }), false)
  assert.equal(shouldOpenBlockMenu(stateFor('paragraph'), { ...slash, isComposing: true }), false)
})

test('slash stays available for comments and paths in code blocks', () => {
  for (const code of ['', '// Comment', '/path'])
    assert.equal(shouldOpenBlockMenu(stateFor('codeBlock', code), slash), false)
})

test('slash does not replace a selection spanning empty paragraphs', () => {
  const doc = schema.nodes.doc.create(null, [schema.nodes.paragraph.create(), schema.nodes.paragraph.create()])
  const state = EditorState.create({ schema, doc, selection: TextSelection.create(doc, 1, 3) })

  assert.equal(shouldOpenBlockMenu(state, slash), false)
})
