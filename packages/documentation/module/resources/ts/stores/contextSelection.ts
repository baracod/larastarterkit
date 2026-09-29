import type { Collection } from '../types/cms'

export function resolveContext(collections: Collection[], previous: { collectionId: number | null; editionId: number | null }, query: Record<string, unknown>) {
  const requestedCollection = query.doc !== undefined ? Number(query.doc) : previous.collectionId

  const collection = collections.find(item => item.id === requestedCollection)
    || (query.doc === undefined ? collections.find(item => !item.archived_at) : undefined)

  const requestedEdition = query.edition !== undefined ? Number(query.edition) : previous.editionId

  const edition = collection?.editions.find(item => item.id === requestedEdition)
    || (query.edition === undefined ? collection?.editions.find(item => item.id === collection.default_edition_id && !item.archived_at) || collection?.editions.find(item => !item.archived_at) : undefined)

  return { collectionId: collection?.id ?? null, editionId: edition?.id ?? null }
}
