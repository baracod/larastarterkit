export function useNotification() {
  const showNotification = (options: {
    type: 'success' | 'error' | 'warning' | 'info'
    message: string
    duration?: number
  }) => {
    // Utiliser CoreNotify via événement personnalisé
    const event = new CustomEvent('core-notify', {
      detail: {
        type: options.type,
        message: options.message,
        duration: options.duration ?? 5000,
      },
    })

    window.dispatchEvent(event)
  }

  return { showNotification }
}
