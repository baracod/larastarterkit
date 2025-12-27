// import { ofetch as $api } from 'ofetch' // par défaut $api est une instance de ofetch exposée dans l'application
import type { IBioEditable, IUser } from '../types/entities'

const baseUrl = 'auth/users'

export const UserAPI = {
  async getAll(): Promise<IUser[]> {
    return await $api<IUser[]>(baseUrl)
  },

  async getById(id: number): Promise<IUser | null> {
    return await $api<IUser>(`${baseUrl}/${id}`)
  },

  async create(data: Partial<IUser>): Promise<IUser> {
    return await $api<IUser>(baseUrl, {
      method: 'POST',
      body: data,
    })
  },

  async update(id: number, data: Partial<IUser>): Promise<IUser> {
    return await $api<IUser>(`${baseUrl}/${id}`, {
      method: 'PUT',
      body: data,
    })
  },

  async updateProfile(id: number, data: Partial<Omit<IBioEditable, 'avatarFile'>>, image?: File): Promise<IUser> {
    const formData = new FormData()

    // Append all fields from data to formData
    for (const key in data) {
      if (Object.prototype.hasOwnProperty.call(data, key)) {
        const value = data[key as keyof typeof data]

        if (typeof value === 'boolean') {
          formData.append(key, value ? '1' : '0')
        }
        else if (value !== null && value !== undefined) {
          // @ts-expect-error
          formData.append(key, value)
        }
      }
    }

    if (image)
      formData.append('avatarFile', image)

    return await $api<IUser>(`${baseUrl}/update-profile/${id}`, {
      method: 'POST', // Use PATCH for FormData
      body: formData,
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

  // auth
  async changePassword({ newPassword, userId }: { newPassword: string; userId: number | string }) {
    await $api(`${baseUrl}/change-password`, {
      method: 'PUT',
      body: { newPassword, newPasswordConfirmation: newPassword, userId },
    })
  },

  async assignRoles(userId: number, roleIds: number[]): Promise<IUser> {
    return await $api<IUser>(`${baseUrl}/${userId}/roles`, {
      method: 'POST',
      body: { roles: roleIds },
    })
  },

  async suspendUser(userId: number): Promise<void> {
    await $api(`${baseUrl}/${userId}/suspend-active`, {
      method: 'POST',
    })
  },
  async suspendUsers(userIds: number[]): Promise<void> {
    await $api(`${baseUrl}/suspend-multiple`, {
      method: 'PATCH',
      body: { userIds },
    })
  },
  async reactivateUsers(userIds: number[]): Promise<void> {
    await $api(`${baseUrl}/active-multiple`, {
      method: 'PATCH',
      body: { userIds },
    })
  },
}

export default UserAPI
