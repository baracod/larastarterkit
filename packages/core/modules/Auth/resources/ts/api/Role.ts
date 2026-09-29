// import { ofetch as $api } from 'ofetch' // par défaut $api est une instance de ofetch exposée dans l'application
import type { IPermission, IRole } from '../types/entities'

const baseUrl = 'auth/roles'

export const RoleAPI = {
  async getAll(): Promise<IRole[]> {
    return await $api<IRole[]>(baseUrl)
  },

  async getById(id: number): Promise<IRole | null> {
    return await $api<IRole>(`${baseUrl}/${id}`)
  },

  async create(data: Partial<IRole>): Promise<IRole> {
    return await $api<IRole>(baseUrl, {
      method: 'POST',
      body: data,
    })
  },

  async update(id: number, data: Partial<IRole>): Promise<IRole> {
    return await $api<IRole>(`${baseUrl}/${id}`, {
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

  async getPermissions(id: number): Promise<IPermission[]> {
    return await $api<IPermission[]>(`${baseUrl}/${id}/permissions`)
  },

  async getCommonPermissions(roleIds: number[]): Promise<IPermission[]> {
    return await $api<IPermission[]>(`${baseUrl}/${roleIds.join(',')}/permissions`, {
      method: 'GET',
    })
  },

  async attachPermissions(roleIds: number[], permissionIds: number[]): Promise<void> {
    await $api(`${baseUrl}/${roleIds.join(',')}/permissions`, {
      method: 'POST',
      body: {
        permissionIds,
      },
    })
  },

  async detachPermissions(roleIds: number[], permissionIds: number[]): Promise<void> {
    await $api(`${baseUrl}/${roleIds.join(',')}/permissions`, {
      method: 'DELETE',
      body: {
        permissionIds,
      },
    })
  },
}

export default RoleAPI
