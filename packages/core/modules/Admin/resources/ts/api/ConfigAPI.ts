import type { IModuleConfig } from '../types/entities'

export const ConfigAPI = {
  async getByModule(moduleName: string): Promise<IModuleConfig[]> {
    return await $api(`/admin/configs/${moduleName}`, { method: 'GET' })
  },

  async update(moduleName: string, configs: any[]): Promise<any> {
    return await $api(`/admin/configs/${moduleName}`, { method: 'PUT', body: { configs } })
  },
}
