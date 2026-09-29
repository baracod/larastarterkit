import assert from 'node:assert/strict'
import { test } from 'node:test'
import { diffSummary, isValidPath, lineDiff, outline, slugify, textStats } from '../../resources/ts/editor/text'
import { canMove, flattenTree, movePage } from '../../resources/ts/editor/tree'

test('slugs and paths match the server validation', () => {
  assert.equal(slugify('  Démarrage rapide : Étape 1 !'), 'demarrage-rapide-etape-1')
  assert.equal(isValidPath('guide/installation-docker'), true)
  for (const path of ['Guide', 'a//b', '-a', 'index', 'assets', 'a b'])
    assert.equal(isValidPath(path), false, path)
})

test('outline and statistics ignore fenced code', () => {
  const markdown = '# Titre\n\nUn deux trois.\n\n```bash\n# pas un titre\necho ok\n```\n\n## Section **deux** ##\n#### Détail'

  assert.deepEqual(outline(markdown), [{ level: 1, text: 'Titre', line: 0 }, { level: 2, text: 'Section deux', line: 9 }, { level: 4, text: 'Détail', line: 10 }])
  assert.equal(textStats(markdown).codeBlocks, 1)
  assert.equal(textStats(markdown).words, 7)
  assert.equal(textStats('').minutes, 1)
})

test('line diff reports additions and removals in document order', () => {
  const diff = lineDiff('a\nb\nc', 'a\nc\nd')

  assert.deepEqual(diff.map(line => `${line.type}:${line.text}`), ['same:a', 'removed:b', 'same:c', 'added:d'])
  assert.deepEqual(diffSummary(diff), { added: 1, removed: 1 })
  assert.deepEqual(diffSummary(lineDiff('x', 'x')), { added: 0, removed: 0 })
})

const pages = [
  { id: 1, parent_id: null, position: 0 },
  { id: 2, parent_id: null, position: 1 },
  { id: 3, parent_id: 2, position: 0 },
  { id: 4, parent_id: null, position: 2 },
]

test('tree flattening orders siblings and keeps orphans visible', () => {
  assert.deepEqual(flattenTree(pages).map(node => [node.item.id, node.depth]), [[1, 0], [2, 0], [3, 1], [4, 0]])
  assert.deepEqual(flattenTree([...pages, { id: 9, parent_id: 99, position: 0 }]).at(-1)?.item.id, 9)
})

test('tree moves only return pages whose parent or position changes', () => {
  assert.deepEqual(movePage(pages, 2, 'up'), [{ id: 2, parent_id: null, position: 0 }, { id: 1, parent_id: null, position: 1 }])
  assert.deepEqual(movePage(pages, 4, 'indent'), [{ id: 4, parent_id: 2, position: 1 }])
  assert.deepEqual(movePage(pages, 3, 'outdent'), [{ id: 3, parent_id: null, position: 2 }, { id: 4, parent_id: null, position: 3 }])
  assert.equal(canMove(pages, 1, 'up'), false)
  assert.equal(canMove(pages, 1, 'indent'), false)
  assert.equal(canMove(pages, 1, 'outdent'), false)
  assert.deepEqual(movePage(pages, 1, 'up'), [])
})
