import { AuthAPI } from '@auth/api/Auth'
import type { IPermission, IRole, IUser } from '@auth/types/entities'

export const useAuthStore = defineStore('auth/user',
  () => {
    // state
    const status = ref<'idle' | 'loading' | 'authenticated'>('idle')
    const token = useLocalStorage<string | null>('auth:token', null)
    const user = ref<IUser | null>(null)
    const roles = ref<IRole[]>([])
    const permissions = ref<IPermission[]>([])
    const abilities = ref<string[]>([])
    const unauthorized = ref<boolean>(false)

    // getters/helpers
    const isAuthenticated = computed(() => !!token.value && !!user.value)
    const isNotAuthorized = computed(() => unauthorized.value)

    function hasRole(key: string) {
      return roles.value.some(r => r.name === key)
    }
    function can(action: string, subject: string) {
      return permissions.value.some(p => (p.action === action && (p.subject === subject || p.subject === 'Any')))
    }
    function hasAbility(a: string) {
      return abilities.value.includes(a)
    }

    // actions
    async function unauthorizedRequest() {
      unauthorized.value = true
    }

    async function hydrate({
      user: u,
      token: t,
      roles: rs = [],
      permissions: ps = [],
    }: {
      user: IUser
      token: string
      roles?: IRole[]
      permissions?: IPermission[]
    }) {
      status.value = 'loading'

      token.value = t ?? null
      user.value = u

      roles.value = Array.isArray(rs) && rs.length ? rs : (u.roles ?? [])

      permissions.value = Array.isArray(ps) ? ps : []

      abilities.value = permissions.value.map(p => `${p.action}:${p.subject}`)

      status.value = 'authenticated'

      return true
    }

    async function logout() {
      console.log('Logging out...', token.value)
      useCookie('accessToken').value = null
      router.replace({ name: 'auth-login' })
      try {
        if (token.value)
          await AuthAPI.logout().catch(() => { })
      }
      finally {
        token.value = null
        user.value = null
        roles.value = []
        permissions.value = []
        abilities.value = []
        status.value = 'idle'
      }
    }

    // return the state, getters, and actions

    return {
      // state
      status,
      token,
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
      unauthorizedRequest,

      // actions
      hydrate,
      logout,
    }
  },
  {
    persist: true,
  },
)
