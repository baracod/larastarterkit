// import { ofetch as $api } from 'ofetch' // par défaut $api est une instance de ofetch exposée dans l'application
import type { IAdminSetting } from '../types/entities'

const baseUrl = 'admin/admin-settings'

export const AdminSettingAPI = {
  async getAll(): Promise<IAdminSetting[]> {
    return await $api<IAdminSetting[]>(baseUrl)
  },

  async getById(id: number): Promise<IAdminSetting | null> {
    return await $api<IAdminSetting>(`${baseUrl}/${id}`)
  },

  async create(data: Partial<IAdminSetting>): Promise<IAdminSetting> {
    return await $api<IAdminSetting>(baseUrl, {
      method: 'POST',
      body: data,
    })
  },

  async update(id: number, data: Partial<IAdminSetting>): Promise<IAdminSetting> {
    return await $api<IAdminSetting>(`${baseUrl}/${id}`, {
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

export default AdminSettingAPI
