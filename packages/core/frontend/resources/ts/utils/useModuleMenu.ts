import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

/** Cache singleton pour les menus de tous les modules */
let menuCache: Record<string, any> | null = null

export function useModuleMenu() {
  const moduleMenus = ref<any[]>([])

  const route = useRoute()

  // On garde en mémoire la clé du module actuel pour éviter les rechargements inutiles
  const currentModuleKey = ref('')

  /** Extrait le nom du module depuis l'URL (premier segment après /) */
  function getModuleKeyFromPath(path: string): string {
    const segments = path.split('/').filter(Boolean)

    return segments[0] || ''
  }

  /** Charge les menus de tous les modules une seule fois, puis met en cache */
  async function loadMenu(): Promise<Record<string, any>> {
    if (!menuCache) {
      try {
        menuCache = await menuItems()
      }
      catch (error) {
        console.error('Erreur lors du chargement des menus :', error)
        menuCache = {}
      }
    }

    return menuCache
  }

  // Watch sur la route pour détecter un changement de module
  watch(
    () => route.path,
    async newPath => {
      const newModuleKey = getModuleKeyFromPath(newPath)

      if (newModuleKey && newModuleKey !== currentModuleKey.value) {
        currentModuleKey.value = newModuleKey

        const menuData = await loadMenu()

        moduleMenus.value = menuData[currentModuleKey.value] || []
      }
    },
    { immediate: true },
  )

  return computed(() => moduleMenus.value.map(({ preserveQuery, ...item }) => {
    if (!Array.isArray(preserveQuery) || !item.to || typeof item.to !== 'object')
      return item

    const query = Object.fromEntries(preserveQuery.filter(key => route.query[key] !== undefined).map(key => [key, route.query[key]]))

    return { ...item, to: { ...item.to, query: { ...item.to.query, ...query } } }
  }))
}
