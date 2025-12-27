// import { ofetch as $api } from 'ofetch' // par défaut $api est une instance de ofetch exposée dans l'application
import type { IPermission } from '../types/entities'

const baseUrl = 'auth/permissions'

export const PermissionAPI = {
  async getAll(): Promise<IPermission[]> {
    return await $api<IPermission[]>(baseUrl)
  },

  async getById(id: number): Promise<IPermission | null> {
    return await $api<IPermission>(`${baseUrl}/${id}`)
  },

  async create(data: Partial<IPermission>): Promise<IPermission> {
    return await $api<IPermission>(baseUrl, {
      method: 'POST',
      body: data,
    })
  },

  async update(id: number, data: Partial<IPermission>): Promise<IPermission> {
    return await $api<IPermission>(`${baseUrl}/${id}`, {
      method: 'PUT',
      body: data,
    })
  },

  async delete(id: number): Promise<void> {
    await $api(`${baseUrl}/${id}`, {
      method: 'DELETE',
    })
  },

  async deleteMultiple(ids: Array<any>): Promise<void> {
    await $api(`${baseUrl}/delete-multiple`, {
      method: 'DELETE',
      body: ids,
    })
  },

}

export default PermissionAPI
