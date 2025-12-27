import type { Module } from '@/types/module'
import { defineStore } from 'pinia'

export default defineStore('core', () => {
  const modules = ref<Module[]>([])
  const selectedModule = ref<Module>()

  const getModules = () => {
    const navigationModules = import.meta.glob('@modules/*/navigation.ts', { eager: true }) as Record<string, { default: Module }>
    const _modules: Module[] = []

    for (const path in navigationModules) {
      // -caste chaque module au type `Module`
      const mod = navigationModules[path] ? navigationModules[path].default as Module : null

      // Si le module n'est pas valide, on passe au suivant
      if (!mod)
        continue

      _modules.push(mod)
    }

    return _modules
  }

  const initNavigationStore = () => {
    modules.value = getModules()
    selectedModule.value = modules.value[0]
  }

  const setSelectedModule = (module: Module) => {
    selectedModule.value = module
  }

  return { initNavigationStore, modules, setSelectedModule, selectedModule }
})
