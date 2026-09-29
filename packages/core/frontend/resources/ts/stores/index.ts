import { initialModuleStatuses, moduleCatalog } from 'virtual:larastarterkit'
import { $api } from '@/utils/api'
import type { Module } from '@/types/module'

export default defineStore('core', () => {
  const moduleStatuses = ref<Record<string, boolean>>(initialModuleStatuses)

  const getModules = (): Module[] => moduleCatalog.filter(module =>
    !module.module || moduleStatuses.value[module.module] === true,
  )

  const modules = ref<Module[]>(getModules())
  const selectedModule = ref<Module>(modules.value[0])

  const initNavigationStore = async () => {
    try {
      moduleStatuses.value = await $api<Record<string, boolean>>('modules')
    }
    catch {
      moduleStatuses.value = Object.fromEntries(Object.keys(initialModuleStatuses).map(name => [name, ['Auth', 'Admin'].includes(name)]))
    }
    modules.value = getModules()
    selectedModule.value = modules.value[0]
  }

  const setSelectedModule = (module: Module) => {
    selectedModule.value = module
  }

  const isEnabled = (name: string) => moduleStatuses.value[name] === true

  const moduleNames = computed(() => Object.keys(moduleStatuses.value))

  return { moduleNames, isEnabled, initNavigationStore, modules, setSelectedModule, selectedModule }
})
