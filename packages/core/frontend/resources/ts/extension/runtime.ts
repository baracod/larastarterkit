import { applicationExtension } from 'virtual:larastarterkit'
import type { RouteLocationRaw, RouteRecordRaw } from 'vue-router/auto'
import { overridePages, redirectFrom } from './core'
import type { StarterRedirectName } from './define'
import type { IUser } from '@auth/types/entities'

export { resolveSlot, withQuery } from './core'

export function resolveRedirect(name: StarterRedirectName, user: IUser | null = null): RouteLocationRaw {
  return redirectFrom(applicationExtension.redirects, name, user)
}

export function applyPageOverrides(routes: RouteRecordRaw[]): RouteRecordRaw[] {
  const result = overridePages(routes, applicationExtension.pages ?? {})

  if (import.meta.env.DEV && result.unknown.length)
    console.warn(`[larastarterkit] starter.ts pages: unknown route names ${result.unknown.join(', ')}`)

  return result.routes
}
