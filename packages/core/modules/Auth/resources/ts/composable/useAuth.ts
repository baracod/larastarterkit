export function useAuth() {
  const userData = useCookie('userData')
  const ability = useAbility()
  const router = useRouter()
  const isLoggedIn = computed(() => !!userData.value)
  const storedUserAbilityRules = useStorage('userAbilityRules', [])

  const isNoAuthorizedRequest = ref<boolean>(false)

  const logout = async () => {
    userData.value = null
    useCookie('accessToken').value = null
    await nextTick(() => {
      router.replace({ name: 'auth-login' })
    })
    useAbility().update([])
    storedUserAbilityRules.value = null
  }

  const a = computed(() => isNoAuthorizedRequest.value)

  return { isLoggedIn, userData, ability, logout, isNoAuthorizedRequest, a }
}
