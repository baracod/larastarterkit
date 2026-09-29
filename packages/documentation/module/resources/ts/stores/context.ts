import { $api } from '@/utils/api'
import type { Collection } from '../types/cms'
import { resolveContext } from './contextSelection'

export const useDocumentationContext = defineStore('documentation-context', () => {
  const route = useRoute()
  const router = useRouter()
  const collections = ref<Collection[]>([])
  const collectionId = ref<number | null>(null)
  const editionId = ref<number | null>(null)
  const loaded = ref(false)
  const loading = ref(false)
  const busy = ref(false)
  const error = ref('')
  let pending: Promise<void> | null = null
  const collection = computed(() => collections.value.find(item => item.id === collectionId.value))
  const edition = computed(() => collection.value?.editions.find(item => item.id === editionId.value))
  function syncRoute() {
    if (!route.path.startsWith('/documentation') || !loaded.value)
      return
    const selected = resolveContext(collections.value, { collectionId: collectionId.value, editionId: editionId.value }, route.query)

    collectionId.value = selected.collectionId
    editionId.value = selected.editionId
  }
  watch(() => route.fullPath, syncRoute)
  async function load(force = false): Promise<void> {
    if (pending)
      return pending
    if (loaded.value && !force) {
      syncRoute()

      return
    }
    pending = (async () => {
      loading.value = true
      error.value = ''
      try {
        collections.value = await $api<Collection[]>('documentation/collections')
        loaded.value = true
        syncRoute()
      }
      catch (failure: any) {
        error.value = failure.data?.message || failure.message
        throw failure
      }
      finally {
        loading.value = false
        pending = null
      }
    })()

    return pending
  }
  function location(path: string, extra: Record<string, string | number> = {}) {
    return { path, query: { ...(collectionId.value ? { doc: collectionId.value } : {}), ...(editionId.value ? { edition: editionId.value } : {}), ...extra } }
  }
  async function select(docId: number, versionId?: number | null) {
    const doc = collections.value.find(item => item.id === docId)
    const version = versionId === undefined ? doc?.editions.find(item => item.id === doc.default_edition_id && !item.archived_at) || doc?.editions.find(item => !item.archived_at) : doc?.editions.find(item => item.id === versionId)

    // Commit context only after route guards accept the navigation.
    return router.push({ path: route.path, query: { doc: docId, ...(version ? { edition: version.id } : {}) } })
  }

  return { collections, collectionId, editionId, collection, edition, loading, loaded, busy, error, load, location, select }
})
