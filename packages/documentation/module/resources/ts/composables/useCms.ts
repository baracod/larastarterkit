import { useAbility } from '@casl/vue'
import { $api } from '@/utils/api'

export const cmsUrl = (path: string) => `documentation/${path}`

export function imageUrl(id: number) {
  return `/api/v1/documentation/images/${id}/file`
}

export function publicSiteUrl() {
  try {
    return JSON.parse(document.getElementById('starter-config')?.textContent || '{}').documentationUrl || '/docs/'
  }
  catch {
    return '/docs/'
  }
}

/** Shared error handling, notifications and permission checks for the CMS screens. */
export function useCms() {
  const { t } = useI18n()
  const { notify } = useNotify()
  const { confirmDialog } = useDialog()
  const ability = useAbility()
  const busy = ref(false)
  const error = ref('')
  const fieldErrors = ref<Record<string, string[]>>({})

  const can = (action: string) => ability.can(action, 'documentation')
  const confirm = (message: string, color: string = 'primary') => confirmDialog({ title: t('Documentation.title'), message, persistent: true, color: color as any })

  function describe(failure: any) {
    const status = failure?.status ?? failure?.statusCode ?? failure?.response?.status
    if (status === 409)
      return failure.data?.message && !/^Conflict$/i.test(failure.data.message) ? `${t('Documentation.conflict')} (${failure.data.message})` : t('Documentation.conflict')
    const errors = failure?.data?.errors as Record<string, string[]> | undefined

    return errors ? Object.values(errors).flat().join(' ') : failure?.data?.message || t('Documentation.failed')
  }

  async function run<T>(action: () => Promise<T>, success?: string): Promise<T | undefined> {
    busy.value = true
    error.value = ''
    fieldErrors.value = {}
    try {
      const result = await action()
      if (success)
        notify({ type: 'success', message: success })

      return result
    }
    catch (failure: any) {
      fieldErrors.value = failure?.data?.errors || {}
      error.value = describe(failure)
      notify({ type: 'error', message: error.value })

      return undefined
    }
    finally {
      busy.value = false
    }
  }

  return { t, api: $api, busy, error, fieldErrors, can, confirm, run, notify }
}
