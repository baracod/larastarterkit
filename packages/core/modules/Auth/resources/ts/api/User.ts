// import { ofetch as $api } from 'ofetch' // par défaut $api est une instance de ofetch exposée dans l'application
import type { IBioEditable, IUser } from '../types/entities'

export interface IUserNotification {
  id: number
  type: string
  title: string
  message?: string | null
  data?: Record<string, any> | null
  readAt?: string | null
  createdAt?: string | null
}

export interface IUserSetting {
  id?: number
  key: string
  value: any
  valueType?: string | null
  label?: string | null
  description?: string | null
  inputType?: string | null
  options?: Record<string, any> | null
  category?: string | null
}

export interface IUserModuleSetting { module: string; key: string; value: Record<string, unknown> | unknown[] | null }
export interface IUserModuleSettingsPayload { settings: IUserModuleSetting[] }
const unwrap = <T>(response: any): T => (response?.data ?? response) as T
const normalizeModulePayload = (payload: any): IUserModuleSettingsPayload => ({ settings: payload.settings ?? [] })

const baseUrl = 'auth/users'

export const UserAPI = {
  async getAll(): Promise<IUser[]> {
    return await $api<IUser[]>(baseUrl)
  },

  async getById(id: number): Promise<IUser | null> {
    return await $api<IUser>(`${baseUrl}/${id}`)
  },

  async create(data: Partial<IUser> & { email_locale?: 'fr' | 'en' }): Promise<IUser> {
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
          // @ts-expect-error FormData accepte les valeurs scalaires convertibles en champ multipart.
          formData.append(key, value)
        }
      }
    }

    if (image)
      formData.append('avatar_file', image, image.name)

    const response = await $api<{ data: IUser } | IUser>(`${baseUrl}/update-profile/${id}`, {
      method: 'POST', // Use PATCH for FormData
      body: formData,
    })

    return unwrap<IUser>(response)
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
  async changePassword(payload: {
    userId: number | string
    currentPassword?: string
    newPassword: string
    newPasswordConfirmation: string
    emailLocale?: 'fr' | 'en'
  }) {
    await $api(`${baseUrl}/${payload.userId}/security/password`, {
      method: 'PUT',
      body: {
        current_password: payload.currentPassword,
        new_password: payload.newPassword,
        new_password_confirmation: payload.newPasswordConfirmation,
        email_locale: payload.emailLocale,
      },
    })
  },

  async getUserSettings(userId: number): Promise<IUserSetting[]> {
    const response = await $api(`${baseUrl}/${userId}/settings`, {
      method: 'GET',
    })

    return unwrap<IUserSetting[]>(response)
  },

  async updateUserSettings(userId: number, settings: IUserSetting[]): Promise<IUserSetting[]> {
    const response = await $api(`${baseUrl}/${userId}/settings`, {
      method: 'PUT',
      body: {
        settings: settings.map(setting => ({
          key: setting.key,
          value: setting.value,
          value_type: setting.valueType ?? 'string',
          label: setting.label ?? null,
          description: setting.description ?? null,
          input_type: setting.inputType ?? 'text',
          options: setting.options ?? null,
        })),
      },
    })

    return unwrap<IUserSetting[]>(response)
  },

  async getUserModules(userId: number): Promise<IUserModuleSettingsPayload> {
    const response = await $api(`${baseUrl}/${userId}/modules`, {
      method: 'GET',
    })

    return normalizeModulePayload(unwrap(response))
  },

  async updateUserModules(userId: number, settings: IUserModuleSettingsPayload['settings']): Promise<IUserModuleSettingsPayload> {
    const response = await $api(`${baseUrl}/${userId}/modules`, {
      method: 'PUT',
      body: { settings },
    })

    return normalizeModulePayload(unwrap(response))
  },

  async getNotifications(
    userId: number,
    params?: { status?: 'all' | 'read' | 'unread'; sort?: 'newest' | 'oldest'; page?: number; perPage?: number },
  ): Promise<{ notifications: IUserNotification[]; pagination: { currentPage: number; lastPage: number; perPage: number; total: number }; unreadCount: number }> {
    const response = await $api(`${baseUrl}/${userId}/notifications`, {
      method: 'GET',
      params: {
        status: params?.status,
        sort: params?.sort,
        page: params?.page,
        per_page: params?.perPage,
      },
    })

    return unwrap<{
      notifications: IUserNotification[]
      pagination: { currentPage: number; lastPage: number; perPage: number; total: number }
      unreadCount: number
    }>(response)
  },

  async markNotificationAsRead(userId: number, notificationId: number): Promise<void> {
    await $api(`${baseUrl}/${userId}/notifications/${notificationId}/read`, {
      method: 'POST',
    })
  },

  async markAllNotificationsAsRead(userId: number): Promise<void> {
    await $api(`${baseUrl}/${userId}/notifications/read-all`, {
      method: 'POST',
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

  async forceChangePassword(payload: {
    userId: number
    emailLocale?: 'fr' | 'en'
  }): Promise<void> {
    await $api(`${baseUrl}/force-change-password`, {
      method: 'POST',
      body: {
        user_id: payload.userId,
        email_locale: payload.emailLocale,
      },
    })
  },

  async sendLoginInstructions(userId: number, emailLocale?: 'fr' | 'en'): Promise<void> {
    await $api(`${baseUrl}/${userId}/login-instructions`, { method: 'POST', body: { email_locale: emailLocale } })
  },
}

export default UserAPI
