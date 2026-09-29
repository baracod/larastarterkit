import { setupLayouts } from 'virtual:generated-layouts'
import type { App } from 'vue'
import type { RouteRecordRaw } from 'vue-router/auto'

import { createRouter, createWebHistory } from 'vue-router/auto'
import { canNavigate } from '@/@layouts/plugins/casl'
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
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(route)),
  ],
})

router.beforeEach(async to => {
  const navigation = useNavigationStore()

  await navigation.initNavigationStore()

  const moduleName = navigation.moduleNames.find(name => name.toLowerCase() === to.path.split('/')[1])
  if (moduleName && !navigation.isEnabled(moduleName))
    return '/'

  if (to.meta.public)
    return

  const isLoggedIn = !!useCookie('userData').value

  if (to.meta.unauthenticatedOnly) {
    if (isLoggedIn)
      return '/'
    else
      return undefined
  }

  // Rediriger vers la page de changement de mot de passe si requis
  if (isLoggedIn && to.name !== 'auth-change-password') {
    const userData = useCookie<IUser | null>('userData')
    const authStore = useAuthStore()
    const mustChange = authStore.user?.mustChangePassword ?? userData.value?.mustChangePassword

    if (mustChange)
      return { name: 'auth-change-password' }
  }

  if (to.meta.authenticatedOnly)
    return isLoggedIn ? undefined : { name: 'auth-login', query: { to: to.fullPath } }

  const can = canNavigate(to)

  if (!can && to.matched.length) {
    if (isLoggedIn)
      return { name: 'not-authorized' }

    return {
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
