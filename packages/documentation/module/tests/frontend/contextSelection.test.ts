import assert from 'node:assert/strict'
import { test } from 'node:test'
import { resolveContext } from '../../resources/ts/stores/contextSelection'
import type { Collection } from '../../resources/ts/types/cms'

const collections: Collection[] = [
  {
    id: 1,
    title: 'First',
    slug: 'first',
    description: '',
    default_edition_id: 12,
    published_at: null,
    archived_at: null,
    editions: [
      { id: 11, collection_id: 1, title: 'v1', slug: 'v1', published_at: null, archived_at: null },
      { id: 12, collection_id: 1, title: 'v2', slug: 'v2', published_at: null, archived_at: null },
    ],
  },
  {
    id: 2,
    title: 'Second',
    slug: 'second',
    description: '',
    default_edition_id: null,
    published_at: null,
    archived_at: null,
    editions: [
      { id: 21, collection_id: 2, title: 'v1', slug: 'v1', published_at: null, archived_at: null },
    ],
  },
]

const empty = { collectionId: null, editionId: null }

test('initial context uses the default edition; menu navigation keeps the selected context', () => {
  assert.deepEqual(resolveContext(collections, empty, {}), { collectionId: 1, editionId: 12 })
  assert.deepEqual(resolveContext(collections, { collectionId: 2, editionId: 21 }, {}), { collectionId: 2, editionId: 21 })
})
test('URL context survives refresh and never resolves an edition from a different documentation', () => {
  assert.deepEqual(resolveContext(collections, empty, { doc: '2', edition: '21' }), { collectionId: 2, editionId: 21 })
  assert.deepEqual(resolveContext(collections, empty, { doc: '2', edition: '11' }), { collectionId: 2, editionId: null })
  assert.deepEqual(resolveContext(collections, empty, { doc: '999' }), empty)
})
test('switching documentation chooses an edition belonging to the new context', () => {
  assert.deepEqual(resolveContext(collections, { collectionId: 1, editionId: 12 }, { doc: '2' }), { collectionId: 2, editionId: 21 })
  assert.deepEqual(resolveContext([], empty, {}), empty)
})
