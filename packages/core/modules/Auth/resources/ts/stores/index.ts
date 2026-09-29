import { stopRealtime } from '@/utils/realtime'
import { AuthAPI } from '@auth/api/Auth'
import type { IPermission, IRole, IUser } from '@auth/types/entities'
import type { IAbilityRuler } from '@auth/types/auth'

export const useAuthStore = defineStore('auth/user',
  () => {
    // state
    const router = useRouter()
    const status = ref<'idle' | 'loading' | 'authenticated'>('idle')
    const user = ref<IUser | null>(null)
    const roles = ref<IRole[]>([])
    const permissions = ref<IPermission[]>([])
    const abilities = ref<string[]>([])
    const unauthorized = ref<boolean>(false)

    // Reactive storage for CASL ability rules — defined at store level so the
    // CASL plugin's watcher picks up updates instead of creating a new ref each call.
    const storedAbilityRules = useStorage<{ action?: string | null; subject?: string | null }[]>('userAbilityRules', [])

    // getters/helpers
    const isAuthenticated = computed(() => !!user.value)
    const isNotAuthorized = computed(() => unauthorized.value)
    const mustChangePassword = computed(() => !!user.value?.mustChangePassword)

    function hasRole(key: string) {
      return roles.value.some(r => r.name === key)
    }
    function can(action: string, subject: string) {
      return hasRole('administrator') || storedAbilityRules.value.some(p => (p.action === action && (p.subject === subject || p.subject === 'Any')))
    }
    function hasAbility(a: string) {
      return hasRole('administrator') || abilities.value.includes(a)
    }

    // actions
    async function unauthorizedRequest() {
      unauthorized.value = true
    }

    async function hydrate({
      user: u,
      roles: rs = [],
      permissions: ps = [],
      abilityRules,
    }: {
      user: IUser
      roles?: IRole[]
      permissions?: IPermission[]
      abilityRules?: IAbilityRuler[]
    }) {
      status.value = 'loading'

      user.value = u

      roles.value = Array.isArray(rs) && rs.length ? rs : (u.roles ?? [])

      permissions.value = Array.isArray(ps) ? ps : []

      // Preserve public and always-allowed rules supplied by the server.
      const caslRules = abilityRules ?? permissions.value.map(p => ({
        action: p.action,
        subject: p.subject,
      }))

      storedAbilityRules.value = hasRole('administrator') ? [{ action: 'manage', subject: 'all' }] : caslRules
      abilities.value = storedAbilityRules.value.map(p => `${p.action}:${p.subject}`)

      status.value = 'authenticated'

      return true
    }

    function markPasswordChanged() {
      if (user.value) {
        user.value = { ...user.value, mustChangePassword: false }
        useCookie<IUser>('userData').value = user.value
      }
    }

    function updateCurrentUser(updated: Partial<IUser>) {
      if (!user.value || (updated.id !== undefined && updated.id !== user.value.id))
        return

      user.value = {
        ...user.value,
        ...updated,
        roles: updated.roles ?? user.value.roles,
      }
      useCookie<IUser>('userData').value = user.value
    }

    let logoutInProgress = false

    async function logout() {
      if (logoutInProgress)
        return

      logoutInProgress = true
      try {
        await AuthAPI.logout().catch(() => { })
      }
      finally {
        stopRealtime()

        // Nettoyer les règles CASL
        storedAbilityRules.value = []

        // Nettoyer TOUS les cookies liés à l'auth
        useCookie('userData').value = null
        useCookie('userAbilityRules').value = null
        useCookie('accessToken').value = null

        // Nettoyer le localStorage aussi (pinia persist)
        if (typeof localStorage !== 'undefined')
          localStorage.removeItem('auth/user')

        user.value = null
        roles.value = []
        permissions.value = []
        abilities.value = []
        status.value = 'idle'
        unauthorized.value = false
        logoutInProgress = false
        await nextTick()
        await router.replace({ name: 'auth-login' })
      }
    }

    // return the state, getters, and actions

    return {
      // state
      status,
      user,
      roles,
      permissions,
      abilities,
      unauthorized,

      // getters/helpers
      isAuthenticated,
      hasRole,
      can,
      hasAbility,
      isNotAuthorized,
      mustChangePassword,
      unauthorizedRequest,

      // actions
      hydrate,
      updateCurrentUser,
      markPasswordChanged,
      logout,
    }
  },
  {
    persist: true,
  },
)
