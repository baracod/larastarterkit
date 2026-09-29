import assert from 'node:assert/strict'
import { test } from 'node:test'
import { MarkdownManager } from '@tiptap/markdown'
import { editorExtensions } from '../../resources/ts/editor/extensions'

const manager = new MarkdownManager({ extensions: editorExtensions() })

test('visual editing preserves fenced code, Mermaid source and private image identities', () => {
  const source = '# Guide\n\n**Important** and *italic*.\n\n```php\necho "<script>literal</script>";\n```\n\n```mermaid\nflowchart LR\nA[Start] --> B[End]\n```\n\n![Screenshot](doc-image:42)'
  const roundtrip = manager.serialize(manager.parse(source))

  assert.match(roundtrip, /```php\necho "<script>literal<\/script>";/)
  assert.match(roundtrip, /```mermaid\nflowchart LR\nA\[Start\] --> B\[End\]/)
  assert.match(roundtrip, /!\[Screenshot\]\(doc-image:42\)/)
  assert.match(roundtrip, /\*\*Important\*\*/)
  assert.deepEqual(manager.parse(roundtrip), manager.parse(source))
})

test('tables, nested lists, tasks and links survive a visual / Markdown roundtrip', () => {
  const source = '| Name | Value |\n| --- | --- |\n| Token | `value` |\n\n- [x] Done\n- [ ] Todo\n\n1. First\n2. Second\n\n> A quote\n\n[Guide](/guide/v1/start.html)\n\n---'
  const roundtrip = manager.serialize(manager.parse(source))

  assert.match(roundtrip, /\| Name\s+\| Value\s+\|/)
  assert.match(roundtrip, /\[x\] Done/)
  assert.match(roundtrip, /\[ \] Todo/)
  assert.match(roundtrip, /\[Guide\]\(\/guide\/v1\/start.html\)/)
  assert.deepEqual(manager.parse(roundtrip), manager.parse(source))
})
