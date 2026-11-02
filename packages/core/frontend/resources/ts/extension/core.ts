// Pure helpers behind the starter.ts extension points; runtime.ts binds them to the application's extension.
import type { Component } from 'vue'
import type { RouteLocationRaw, RouteRecordRaw } from 'vue-router/auto'
import type { StarterPages, StarterRedirectName, StarterRedirects, StarterSlotContribution, StarterSlotValue } from './define'
import type { IUser } from '@auth/types/entities'

// Defaults reproduce the behaviour that existed before redirects became configurable.
export const defaultRedirects: Record<StarterRedirectName, RouteLocationRaw> = {
  home: '/',
  afterLogin: '/',
  afterLogout: { name: 'auth-login' },
  login: { name: 'auth-login' },
  notAuthorized: { name: 'not-authorized' },
  mustChangePassword: { name: 'auth-change-password' },
}

export function redirectFrom(redirects: StarterRedirects | undefined, name: StarterRedirectName, user: IUser | null = null): RouteLocationRaw {
  const target = redirects?.[name] ?? defaultRedirects[name]

  return typeof target === 'function' ? target(user) : target
}

export function withQuery(target: RouteLocationRaw, query: Record<string, string | undefined>): RouteLocationRaw {
  if (typeof target === 'string')
    return { path: target, query }

  return { ...target, query: { ...('query' in target ? target.query : {}), ...query } } as RouteLocationRaw
}

function isContribution(value: unknown): value is StarterSlotContribution<any> {
  return typeof value === 'object' && value !== null && 'component' in value
}

export interface ResolvedSlot {
  replacement?: Component
  contributions: { key: number; component: Component; props: Record<string, unknown> }[]
}

export function resolveSlot(value: StarterSlotValue<any> | undefined, context: Record<string, unknown>): ResolvedSlot {
  if (!value)
    return { contributions: [] }
  if (!Array.isArray(value) && !isContribution(value))
    return { replacement: value, contributions: [] }

  const contributions = (Array.isArray(value) ? value : [value])
    .map((contribution, key) => ({ contribution, key }))
    .filter(({ contribution }) => contribution.when?.(context) ?? true)
    .sort((a, b) => (a.contribution.order ?? 0) - (b.contribution.order ?? 0))

  return {
    contributions: contributions.map(({ contribution, key }) => ({
      key,
      component: contribution.component,
      props: typeof contribution.props === 'function' ? contribution.props(context) : (contribution.props ?? {}),
    })),
  }
}

/** Returns the routes with overridden components and layouts, and the override names that matched no route. */
export function overridePages(routes: RouteRecordRaw[], overrides: StarterPages): { routes: RouteRecordRaw[]; unknown: string[] } {
  const pending = new Set<string>(Object.keys(overrides))

  const visit = (route: RouteRecordRaw): RouteRecordRaw => {
    const override = typeof route.name === 'string' ? (overrides as Record<string, StarterPages[keyof StarterPages]>)[route.name] : undefined
    let result = route
    if (override) {
      pending.delete(route.name as string)

      const { component, layout } = typeof override === 'object' && ('component' in override || 'layout' in override)
        ? override as { component?: Component; layout?: string }
        : { component: override as Component, layout: undefined }

      result = { ...route, ...(component ? { component } : {}), meta: { ...route.meta, ...(layout ? { layout } : {}) } } as RouteRecordRaw
    }

    return result.children ? { ...result, children: result.children.map(visit) } as RouteRecordRaw : result
  }

  return { routes: routes.map(visit), unknown: [...pending] }
}
