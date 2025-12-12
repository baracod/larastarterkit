// src/stores/auth/user.ts
import { type AppAbility, buildAbility } from '@/abilities/ability'
import { http } from '@/api/http'
import type { LoginPayload, LoginResponse, Permission } from '@auth/types/entities'
import { useIdle, useIntervalFn, useNetwork, useStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'

export const useUserStore = defineStore('auth/user', () => {
  // --- State (persistés via VueUse)
  const user = useStorage<AuthUser | null>('auth.user', null) // localStorage sous le capot
  const token = useStorage<string | null>('auth.token', null)
  const ability = ref<AppAbility>(buildAbility([]))
  const loading = ref(false)
  const lastRefreshAt = useStorage<number | null>('auth.lastRefreshAt', null)

  // --- Contexte VueUse
  const { idle } = useIdle(15 * 60 * 1000) // 15 minutes d'inactivité
  const { isOnline } = useNetwork()

  // --- Getters
  const isAuthenticated = computed(() => !!user.value?.id)
  const roles = computed(() => user.value?.roles ?? [])

  const permissions = computed<Permission[]>(() => {
    const map = new Map<string, Permission>()

    roles.value.forEach(r => (r.permissions ?? []).forEach(p => map.set(`${p.action}:${p.subject}`, p)))

    return Array.from(map.values())
  })

  const displayName = computed(() => user.value?.name ?? user.value?.email ?? 'Utilisateur')

  // --- Helpers
  function rebuildAbility() {
    ability.value = buildAbility(permissions.value)
  }
  function hasRole(name: string) {
    return roles.value.some(r => r.name.toLowerCase() === name.toLowerCase())
  }
  function can(action: string, subject: string) {
    return ability.value.can(action, subject)
  }

  // --- Actions
  async function login(payload: LoginPayload) {
    loading.value = true
    try {
      const res = await http<LoginResponse>('/api/login', { method: 'POST', body: payload })

      user.value = res.user_data
      token.value = res.access_token ?? null
      lastRefreshAt.value = Date.now()
      rebuildAbility()

      return true
    }
    finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    loading.value = true
    try {
      const me = await http<AuthUser>('/api/me', { method: 'GET' })

      user.value = me
      rebuildAbility()

      return me
    }
    finally {
      loading.value = false
    }
  }

  async function refresh() {
    // Exemples possibles :
    // 1) Endpoint refresh dédié :
    // const res = await http<{ access_token: string }>('/api/refresh', { method: 'POST' })
    // token.value = res.access_token

    // 2) Pas de refresh côté API → ping /me pour valider la session
    await fetchMe()
    lastRefreshAt.value = Date.now()

    return true
  }

  async function logout() {
    try { await http('/api/logout', { method: 'POST' }) }
    catch { }
    hardLogout()
  }

  function hardLogout() {
    user.value = null
    token.value = null
    ability.value = buildAbility([])
    lastRefreshAt.value = null
  }

  function setToken(t: string | null) {
    token.value = t
  }

  // --- Réactivité VueUse

  // Rebuild ability dès que les permissions changent
  watch(permissions, rebuildAbility, { immediate: true })

  // Auto "soft-logout" si idle
  watch(idle, isIdle => {
    if (isIdle && isAuthenticated.value) {
      // au choix : lock UI + demander le mdp, ou logout direct
      hardLogout()
    }
  })

  // Si on passe offline, on évite les refresh ; quand on revient online on resynchronise
  watch(isOnline, async online => {
    if (online && isAuthenticated.value) {
      try { await fetchMe() }
      catch { /* noop */ }
    }
  })

  // Refresh périodique (ex: toutes les 12 min)
  // Ajuste selon TTL de ton token (JWT exp - now - marge)
  const { pause, resume } = useIntervalFn(async () => {
    if (!isAuthenticated.value)
      return
    try { await refresh() }
    catch { /* noop */ }
  }, 12 * 60 * 1000, { immediate: false })

  // Démarre/stoppe le timer en fonction de l’auth
  watch(isAuthenticated, ok => (ok ? resume() : pause()), { immediate: true })

  return {
    // state
    user,
    token,
    ability,
    loading,
    lastRefreshAt,

    // getters
    isAuthenticated,
    roles,
    permissions,
    displayName,

    // helpers
    hasRole,
    can,

    // actions
    login,
    fetchMe,
    refresh,
    logout,
    hardLogout,
    setToken,
  }
})
