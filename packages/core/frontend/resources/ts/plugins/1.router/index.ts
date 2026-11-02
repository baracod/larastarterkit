import { setupLayouts } from 'virtual:generated-layouts'
import type { App } from 'vue'
import type { RouteRecordRaw } from 'vue-router/auto'

import { createRouter, createWebHistory } from 'vue-router/auto'
import { canNavigate } from '@/@layouts/plugins/casl'
import { applyPageOverrides, resolveRedirect, withQuery } from '@/extension/runtime'
import useNavigationStore from '@/stores'
import { useAuthStore } from '@auth/stores'
import type { IUser } from '@auth/types/entities'

// import { useAbility } from '@casl/vue'

function recursiveLayouts(route: RouteRecordRaw): RouteRecordRaw {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])

    return route
  }

  return setupLayouts([route])[0]
}

const router = createRouter({
  // history: createWebHistory(import.meta.env.BASE_URL),
  history: createWebHistory('/'),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },

  // Page overrides from starter.ts are applied before layouts so that a layout override is honoured.
  extendRoutes: pages => [
    ...applyPageOverrides([...pages]).map(route => recursiveLayouts(route)),
  ],
})

router.beforeEach(async to => {
  const navigation = useNavigationStore()

  await navigation.initNavigationStore()

  const userData = useCookie<IUser | null>('userData')
  const isLoggedIn = !!userData.value
  const authStore = isLoggedIn ? useAuthStore() : null
  const user = authStore?.user ?? userData.value ?? null

  const moduleName = navigation.moduleNames.find(name => name.toLowerCase() === to.path.split('/')[1])
  if (moduleName && !navigation.isEnabled(moduleName))
    return resolveRedirect('home', user)

  if (to.meta.public)
    return

  if (to.meta.unauthenticatedOnly) {
    if (isLoggedIn)
      return resolveRedirect('home', user)
    else
      return undefined
  }

  // Rediriger vers la page de changement de mot de passe si requis
  if (isLoggedIn && (authStore?.user?.mustChangePassword ?? userData.value?.mustChangePassword)) {
    const target = router.resolve(resolveRedirect('mustChangePassword', user))

    if (to.path !== target.path)
      return target
  }

  if (to.meta.authenticatedOnly)
    return isLoggedIn ? undefined : withQuery(resolveRedirect('login'), { to: to.fullPath })

  const can = canNavigate(to)

  if (!can && to.matched.length) {
    if (isLoggedIn)
      return resolveRedirect('notAuthorized', user)

    return withQuery(resolveRedirect('login'), {
      ...to.query as Record<string, string>,
      to: to.fullPath !== '/' ? to.path : undefined,
    })
  }
})

export { router }

export default function (app: App) {
  app.use(router)
}
