import type { ISetting } from '../types/entities'

interface SettingPayload {
  key: string
  value: any
  type?: 'system' | 'module' | 'user'
  module?: string | null
  userId?: number | null
  valueType?: string | null
  label?: string | null
  description?: string | null
  inputType?: string | null
  options?: Record<string, any> | null
  defaultValue?: any | null
  isPublic?: boolean | null
}

const unwrap = <T>(response: any): T => {
  return (response?.data ?? response) as T
}

const toPayload = (setting: Partial<SettingPayload>) => ({
  key: setting.key,
  value: setting.value,
  type: setting.type,
  module: setting.module ?? null,
  user_id: setting.userId ?? null,
  value_type: setting.valueType ?? null,
  label: setting.label ?? null,
  description: setting.description ?? null,
  input_type: setting.inputType ?? null,
  options: setting.options ?? null,
  default_value: setting.defaultValue ?? null,
  is_public: setting.isPublic ?? null,
})

export const SettingAPI = {
  async getSystem(): Promise<ISetting[]> {
    const res = await $api('/admin/settings/system', { method: 'GET' })

    return unwrap<ISetting[]>(res)
  },

  async getModules(moduleName?: string | null): Promise<ISetting[]> {
    const res = await $api('/admin/settings/modules', {
      method: 'GET',
      params: moduleName ? { module: moduleName } : undefined,
    })

    return unwrap<ISetting[]>(res)
  },

  async getUsers(userId?: number | null): Promise<ISetting[]> {
    const res = await $api('/admin/settings/users', {
      method: 'GET',
      params: userId ? { user_id: userId } : undefined,
    })

    return unwrap<ISetting[]>(res)
  },

  async getModulesList(): Promise<string[]> {
    const res = await $api('/admin/settings/modules-list', { method: 'GET' })

    return unwrap<string[]>(res)
  },

  async getAvailableModules(): Promise<string[]> {
    const res = await $api('/admin/settings/available-modules', { method: 'GET' })

    return unwrap<string[]>(res)
  },

  async getSetting(key: string, module?: string | null, userId?: number | null): Promise<{ key: string; value: any }> {
    const res = await $api(`/admin/settings/${key}`, {
      method: 'GET',
      params: {
        module: module ?? undefined,
        user_id: userId ?? undefined,
      },
    })

    return unwrap<{ key: string; value: any }>(res)
  },

  async create(setting: Partial<SettingPayload>): Promise<ISetting> {
    const res = await $api('/admin/settings', {
      method: 'POST',
      body: toPayload(setting),
    })

    return unwrap<ISetting>(res)
  },

  async update(id: number, setting: Partial<SettingPayload>): Promise<ISetting> {
    const res = await $api(`/admin/settings/${id}`, {
      method: 'PUT',
      body: toPayload(setting as SettingPayload),
    })

    return unwrap<ISetting>(res)
  },

  async delete(payload: { key: string; type?: string; module?: string | null; userId?: number | null }): Promise<void> {
    await $api('/admin/settings', {
      method: 'DELETE',
      body: {
        key: payload.key,
        type: payload.type ?? 'system',
        module: payload.module ?? null,
        user_id: payload.userId ?? null,
      },
    })
  },

  async bulkUpdate(settings: SettingPayload[]): Promise<ISetting[]> {
    const res = await $api('/admin/settings/bulk', {
      method: 'POST',
      body: {
        settings: settings.map(toPayload),
      },
    })

    return unwrap<ISetting[]>(res)
  },

  async clearCache(): Promise<void> {
    await $api('/admin/settings/clear-cache', { method: 'POST' })
  },
}
