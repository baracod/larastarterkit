import type { IModule } from '../types/entities'

export const ModuleAPI = {
  async getAll(): Promise<IModule[]> {
    return await $api('/admin/modules', { method: 'GET' })
  },

  async toggle(name: string): Promise<any> {
    return await $api(`/admin/modules/${name}/toggle`, { method: 'POST' })
  },
}
