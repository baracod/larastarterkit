import assert from 'node:assert/strict'
import { test } from 'node:test'
import { MarkdownManager } from '@tiptap/markdown'
import { editorExtensions } from '../../resources/ts/editor/extensions'
import { filterSlashCommands } from '../../resources/ts/editor/slashCommands'
import { formatCodeInfo, lineSet, parseCodeInfo } from '../../publishing/.vitepress/theme/prism'

const manager = new MarkdownManager({ extensions: editorExtensions(() => '', { highlight: true }) })
const roundtrip = (source: string) => manager.serialize(manager.parse(source))

test('callouts become editable blocks and keep their type, custom title and rich content', () => {
  const source = '> [!WARNING] Sauvegarde **requise**\n> Faites une copie.\n>\n> - base\n> - fichiers\n\n> Citation simple'
  const doc = manager.parse(source)

  assert.equal(doc.content?.[0].type, 'callout')
  assert.deepEqual(doc.content?.[0].attrs, { type: 'warning', title: 'Sauvegarde **requise**' })
  assert.equal(doc.content?.[1].type, 'blockquote')
  assert.equal(roundtrip(source), source)
  assert.equal(roundtrip('> [!TIP]\n> Astuce'), '> [!TIP]\n> Astuce')
})

test('code fences keep language, file title and highlighted lines through the visual editor', () => {
  const source = '```ts [src/main.ts] {1,3-4}\nconst a = 1\n```'

  assert.equal(roundtrip(source), source)
  assert.deepEqual(parseCodeInfo('ts [src/main.ts] {1,3-4}'), { language: 'ts', title: 'src/main.ts', lines: '1,3-4' })
  assert.equal(formatCodeInfo({ language: 'php', title: 'a[b].php', lines: '2, x5' }), 'php [ab.php] {2,5}')
  assert.deepEqual([...lineSet('1,3-5,9-8,0,x')], [1, 3, 4, 5])
  assert.equal(lineSet('1-100000').size, 501)
})

test('slash menu filters by label or keyword, accents and language insensitive', () => {
  const keys = (query: string) => filterSlashCommands(query).map(command => command.key)

  assert.ok(keys('').length > 15)
  assert.deepEqual(keys('mermaid'), ['mermaid'])
  assert.ok(keys('etapes').includes('orderedList'))
  assert.ok(keys('shell').includes('terminal'))
  assert.deepEqual(keys('callout attention'), ['warning'])
  assert.deepEqual(keys('zzz'), [])
})
