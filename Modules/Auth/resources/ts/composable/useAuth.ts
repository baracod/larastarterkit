export function useAuth() {
  const userData = useCookie('userData')
  const accessToken = useCookie('accessToken')
  const ability = useAbility()
  const router = useRouter()
  const isLoggedIn = computed(() => !!(userData.value && accessToken.value))
  const storedUserAbilityRules = useStorage('userAbilityRules', [])

  const isNoAuthorizedRequest = ref<boolean>(false)

  const logout = async () => {
    userData.value = null
    accessToken.value = null
    await nextTick(() => {
      router.replace({ name: 'auth-login' })
    })
    useAbility().update([])
    storedUserAbilityRules.value = null
  }

  const a = computed(() => isNoAuthorizedRequest.value)

  return { isLoggedIn, userData, accessToken, ability, logout, isNoAuthorizedRequest, a }
}
