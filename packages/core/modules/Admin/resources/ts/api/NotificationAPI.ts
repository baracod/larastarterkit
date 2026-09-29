import type { INotification, IPaginatedResponse } from '../types/entities'

export const NotificationAPI = {
  async getAll(page = 1): Promise<IPaginatedResponse<INotification>> {
    return await $api('/admin/notifications', { method: 'GET', params: { page } })
  },

  async unreadCount(): Promise<{ count: number }> {
    return await $api('/admin/notifications/unread-count', { method: 'GET' })
  },

  async markAsRead(id: number): Promise<any> {
    return await $api(`/admin/notifications/${id}/read`, { method: 'POST' })
  },

  async markAllAsRead(): Promise<any> {
    return await $api('/admin/notifications/read-all', { method: 'POST' })
  },
}
