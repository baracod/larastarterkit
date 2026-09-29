export function useAuthForm() {
  const { t } = useI18n()
  const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
  const loading = ref(false)
  const errorMessage = ref('')
  const required = (value: string) => !!value || t('Auth.access.required')
  const emailRules = [required, (value: string) => /^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/.test(value) || t('Auth.access.emailInvalid')]

  const passwordRules = [
    required,
    (value: string) => value.length >= 8 || t('Auth.security.validation.minLength'),
    (value: string) => (/\p{Ll}/u.test(value) && /\p{Lu}/u.test(value)) || t('Auth.security.validation.mixedCase'),
    (value: string) => /\p{N}/u.test(value) || t('Auth.security.validation.number'),
    (value: string) => /[\p{Z}\p{S}\p{P}]/u.test(value) || t('Auth.security.validation.symbol'),
  ]

  async function beginSubmit() {
    if (loading.value)
      return false
    loading.value = true
    errorMessage.value = ''

    const result = await formRef.value?.validate()
    if (!result?.valid) {
      loading.value = false
      await nextTick()
      document.querySelector<HTMLElement>('.auth-access .v-input--error input')?.focus()

      return false
    }

    return true
  }

  function showError(error: { status?: number; statusCode?: number; data?: { message?: string } }) {
    const status = error.status ?? error.statusCode

    errorMessage.value = t(status === 429 ? 'Auth.access.tooManyAttempts' : status === 423 ? 'Auth.access.suspended' : status === 401 ? 'Auth.access.invalidCredentials' : status === 419 ? 'Auth.access.expiredSession' : 'Auth.access.requestError')
  }

  return { formRef, loading, errorMessage, required, emailRules, passwordRules, beginSubmit, showError }
}
