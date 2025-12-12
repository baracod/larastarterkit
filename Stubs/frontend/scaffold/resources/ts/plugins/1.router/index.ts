import { setupLayouts } from 'virtual:generated-layouts'
import type { App } from 'vue'
import type { RouteRecordRaw } from 'vue-router/auto'

import { canNavigate } from '@/@layouts/plugins/casl'
import { createRouter, createWebHistory } from 'vue-router/auto'

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
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(route)),
  ],
})

router.beforeEach(to => {
  if (to.meta.public)
    return

  const isLoggedIn = !!(useCookie('userData').value && useCookie('accessToken').value)

  if (to.meta.unauthenticatedOnly) {
    if (isLoggedIn)
      return '/'
    else
      return undefined
  }
  const ability = useAbility()
  const can = canNavigate(to)

  if (!can && to.matched.length) {
    return isLoggedIn
      ? { name: 'not-authorized' }
      : {
        name: 'auth-login',
        query: {
          ...to.query,
          to: to.fullPath !== '/' ? to.path : undefined,
        },
      }
  }
})

export { router }

export default function (app: App) {
  app.use(router)
}
