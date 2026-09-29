export const useAuthMailLanguage = createSharedComposable(() => {
  const { locale } = useI18n()

  const language = useCookie<'fr' | 'en'>('STARTER-mail-language', {
    default: () => locale.value.startsWith('en') ? 'en' : 'fr',
    sameSite: 'lax',
  })

  if (!['fr', 'en'].includes(language.value))
    language.value = 'fr'

  return language
})
