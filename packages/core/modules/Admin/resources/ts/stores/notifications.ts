import { defineStore } from 'pinia'
import { subscribePrivateOnce } from '@/utils/realtimeSubscriptions'
import { NotificationAPI } from '../api/NotificationAPI'
import type { INotification } from '../types/entities'

declare global {
  interface Window {
    Echo: any
  }
}

export const useNotificationStore = defineStore('admin-notifications', {
  state: () => ({
    notifications: [] as INotification[],
    unreadCount: 0,
    loading: false,
    error: null as string | null,
    sessionVersion: 0,
    currentPage: 1,
    lastPage: 1,
  }),

  actions: {
    resetSession() {
      const version = this.sessionVersion + 1

      this.$reset()
      this.sessionVersion = version
    },
    async fetchNotifications(page = 1) {
      const version = this.sessionVersion

      this.loading = true
      try {
        const res = await NotificationAPI.getAll(page)

        if (version !== this.sessionVersion)
          return
        this.notifications = res.data
        this.currentPage = res.current_page
        this.lastPage = res.last_page
      }
      catch (err: any) {
        if (version === this.sessionVersion)
          this.error = err.message
      }
      finally {
        if (version === this.sessionVersion)
          this.loading = false
      }
    },

    async fetchUnreadCount() {
      const version = this.sessionVersion
      try {
        const res = await NotificationAPI.unreadCount()

        if (version === this.sessionVersion)
          this.unreadCount = res.count
      }
      catch (err) {
        console.error('Failed to fetch unread count', err)
      }
    },

    async markAsRead(id: number) {
      const version = this.sessionVersion

      await NotificationAPI.markAsRead(id)

      if (version !== this.sessionVersion)
        return
      const notification = this.notifications.find(n => n.id === id)
      if (notification && !notification.read_at) {
        notification.read_at = new Date().toISOString()
        this.unreadCount = Math.max(0, this.unreadCount - 1)
      }
    },

    async markAllAsRead() {
      const version = this.sessionVersion

      await NotificationAPI.markAllAsRead()
      if (version !== this.sessionVersion)
        return
      this.notifications.forEach(n => {
        if (!n.read_at)
          n.read_at = new Date().toISOString()
      })
      this.unreadCount = 0
    },

    // Méthode pour ajouter une notification reçue via WebSocket (Broadcasting)
    addReceivedNotification(notification: Pick<INotification, 'type' | 'title' | 'message'> & Partial<INotification>) {
      // System broadcasts have no admin_notifications row ID (push-only is valid).
      // Never insert an incomplete item that cannot be marked as read.
      if (typeof notification.id === 'number') {
        if (this.notifications.some(item => item.id === notification.id))
          return
        this.notifications.unshift(notification as INotification)
        if (!notification.read_at)
          this.unreadCount++
      }
      else {
        void this.fetchNotifications()
        void this.fetchUnreadCount()
      }

      // Trigger un toast de notification
      const { notify } = useNotify()
      const senderName = notification.sender?.name ? `${notification.sender.name}: ` : ''

      notify({
        type: notification.type,
        title: `${senderName}${notification.title}`,
        message: notification.message || '',
      })
    },

    setupWebsocketListener(userId: number) {
      if (!window.Echo)
        return

      subscribePrivateOnce(window.Echo, `user.${userId}`, '.notification.created', event => {
        this.addReceivedNotification(event.notification)
      })
    },
  },
})
