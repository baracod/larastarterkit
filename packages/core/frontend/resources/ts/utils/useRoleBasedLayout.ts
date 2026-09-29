import { themeConfig } from '@themeConfig'

export function useRoleBasedLayout() {
  return {
    layoutName: computed(() => 'default'),
    hasCustomLayout: computed(() => false),
    layoutTitle: computed(() => themeConfig.app.title),
    layoutPrimaryColor: computed(() => 'primary'),
    layoutIcon: computed(() => 'bx-home'),
  }
}
