import { getI18n } from '@/plugins/i18n'

// src/plugins/api-events.ts

import { useNotification } from '@/composables/useNotification'
import { useAuthStore } from '@auth/stores'

export default function () {
  const { showNotification } = useNotification()

  window.addEventListener('api:unauthorized', async () => {
    const { unauthorizedRequest, logout } = useAuthStore()

    showNotification({
      type: 'error',
      message: 'Votre session a expiré. Veuillez vous reconnecter.',
    })

    unauthorizedRequest()
    await logout()
  })

  window.addEventListener('api:suspended', async () => {
    const { unauthorizedRequest, logout } = useAuthStore()

    showNotification({
      type: 'error',
      message: 'Votre compte a été suspendu. Veuillez contacter l\'administrateur.',
      duration: 7000,
    })

    unauthorizedRequest()
    await logout()
  })

  window.addEventListener('api:forbidden', () => {
    showNotification({
      type: 'error',
      message: getI18n().global.t('auditFixes.accessDenied'),
      duration: 7000,
    })
  })
}
