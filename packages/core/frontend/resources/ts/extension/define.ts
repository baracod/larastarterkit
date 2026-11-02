// Public extension API of resources/ts/starter.ts. Everything exported here is covered by semver.
// This file must not import runtime code: the application's starter.ts imports it.
import type { App, Component } from 'vue'
import type { RouteLocationRaw } from 'vue-router/auto'
import type { RouteNamedMap } from 'vue-router/auto-routes'
import type { Module } from '@/types/module'
import type { IUser } from '@auth/types/entities'

/** Context passed to each slot's `when` and `props` callbacks. Applications and modules can augment this interface. */
export interface StarterSlotContexts {
  'navbar-start': Record<string, never>
  'navbar-end': Record<string, never>
  'user-menu': { user: IUser | null }
  'footer': Record<string, never>
  'auth-login-before-form': Record<string, never>
  'auth-login-after-form': Record<string, never>
  'auth-register-end': Record<string, never>
  'auth-forgot-password-after-form': Record<string, never>
  'dashboard-start': Record<string, never>
  'dashboard-end': Record<string, never>
  'profile-end': { user: IUser | null }
  'users-list-actions': Record<string, never>
}

export type StarterSlotName = keyof StarterSlotContexts

export interface StarterSlotContribution<Context = Record<string, unknown>> {

  /** Component to render. Wrap lazy imports with defineAsyncComponent. */
  component: Component

  /** Ascending order; contributions without order keep their declaration order. */
  order?: number
  when?: (context: Context) => boolean
  props?: Record<string, unknown> | ((context: Context) => Record<string, unknown>)
}

/** A bare component replaces the slot's default content (historical behaviour); contributions are added after it. */
export type StarterSlotValue<Context = Record<string, unknown>> = Component | StarterSlotContribution<Context> | StarterSlotContribution<Context>[]

export type StarterSlots = { [Name in StarterSlotName]?: StarterSlotValue<StarterSlotContexts[Name]> }

export type StarterRedirectTarget = RouteLocationRaw | ((user: IUser | null) => RouteLocationRaw)

export interface StarterRedirects {
  home?: StarterRedirectTarget
  afterLogin?: StarterRedirectTarget
  afterLogout?: StarterRedirectTarget
  login?: StarterRedirectTarget
  notAuthorized?: StarterRedirectTarget
  mustChangePassword?: StarterRedirectTarget
}

export type StarterRedirectName = keyof StarterRedirects

export type StarterRouteName = keyof RouteNamedMap

type PageComponent = Component | (() => Promise<unknown>)

/** Replaces the component of an existing route; its name, path, meta and guards are kept. */
export type StarterPageOverride = PageComponent | { component?: PageComponent; layout?: string }

export type StarterPages = { [Name in StarterRouteName]?: StarterPageOverride }

export interface StarterExtension {
  menus?: Record<string, any[]>
  catalog?: Module[]
  slots?: StarterSlots
  redirects?: StarterRedirects
  pages?: StarterPages
  configure?: (app: App) => void
}

export function defineStarter(extension: StarterExtension): StarterExtension {
  return extension
}
