// --- Types ---
export type NotificationType = 'success' | 'error' | 'warning' | 'info'

export interface Notification {
  id: number
  type: NotificationType
  title?: string
  message: string
}

// --- État global (singleton au niveau du module) ---
const notifications = ref<Notification[]>([])

// Délai avant fermeture automatique (en ms)
const NOTIFICATION_TIMEOUT = 5000

/**
 * Composable pour gérer l'affichage de notifications globales.
 */
export function useNotify() {
  /**
   * Retire une notification de la liste par son ID.
   */
  const remove = (notificationId: number) => {
    notifications.value = notifications.value.filter(n => n.id !== notificationId)
  }

  /**
   * Affiche une nouvelle notification.
   * C'est la seule fonction que vous utiliserez dans votre application.
   */
  const notify = (options: { type?: NotificationType; title?: string; message: string }) => {
    const id = Date.now() + Math.random()

    notifications.value.push({
      id,
      type: options.type || 'info',
      title: options.title,
      message: options.message,
    })

    // Déclenche la suppression automatique après un délai
    setTimeout(() => {
      remove(id)
    }, NOTIFICATION_TIMEOUT)
  }

  return {
    // Liste des notifications en lecture seule pour le composant d'affichage
    notifications: readonly(notifications),

    // Fonction de suppression pour le composant d'affichage
    remove,

    // La fonction publique pour créer des notifications
    notify,
  }
}
