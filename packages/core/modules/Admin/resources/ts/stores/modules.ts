import { defineStore } from 'pinia'
import { ModuleAPI } from '../api/ModuleAPI'
import type { IModule } from '../types/entities'

export const useModuleStore = defineStore('admin-modules', {
  state: () => ({
    modules: [] as IModule[],
    loading: false,
    error: null as string | null,
  }),

  getters: {
    activeModules: state => state.modules.filter(m => m.enabled),
    secondaryModules: state => state.modules.filter(m => !m.is_primary),
  },

  actions: {
    async fetchModules() {
      this.loading = true
      try {
        this.modules = await ModuleAPI.getAll()
      }
      catch (err: any) {
        this.error = err.message
      }
      finally {
        this.loading = false
      }
    },

    async toggleModule(name: string) {
      const res = await ModuleAPI.toggle(name)
      const index = this.modules.findIndex(m => m.name === name)
      if (index !== -1)
        this.modules[index].enabled = res.data.enabled
    },
  },
})
