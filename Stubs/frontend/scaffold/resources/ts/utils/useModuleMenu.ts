import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'

export function useModuleMenu() {
  // Référence pour stocker le menu du module courant
  const moduleMenus = ref([])

  // On récupère la route courante
  const route = useRoute()

  // On garde en mémoire la clé du module actuel pour éviter les rechargements inutiles
  const currentModuleKey = ref('')

  // Fonction utilitaire pour extraire le module depuis l'URL (le premier segment de l'URL)
  function getModuleKeyFromPath(path: string): string {
    // Suppression des segments vides et récupération du premier segment
    const segments = path.split('/').filter(Boolean)

    return segments[0] || ''
  }

  // Watch sur la route, afin de détecter un changement de module
  watch(
    () => route.fullPath,
    async newPath => {
      const newModuleKey = getModuleKeyFromPath(newPath)

      // Si le module a changé, on recharge le menu
      if (newModuleKey && newModuleKey !== currentModuleKey.value) {
        currentModuleKey.value = newModuleKey

        try {
          // Chargement dynamique du fichier de menus pour le module courant.
          // Ici on suppose que le fichier se trouve dans Modules/<ModuleKey>/menus.json
          const menuData = await menuItems()

          moduleMenus.value = menuData[currentModuleKey.value ?? 'default']
        }
        catch (error) {
          console.error(`Erreur lors du chargement du menu pour le module "${newModuleKey}":`, error)
          moduleMenus.value = []
        }
      }
    },
    { immediate: true },
  )

  return moduleMenus
}
