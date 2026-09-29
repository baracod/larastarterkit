import type { App } from 'vue'

import { abilitiesPlugin } from '@casl/vue'
import { ability } from './ability'
import type { Rule } from './ability'
import { useAuthStore } from '@auth/stores'

export default function (app: App) {
  const userAbilityRules = useStorage<Rule[]>('userAbilityRules', [])
  const auth = app.runWithContext(() => useAuthStore())

  const effectiveRules = computed(() => auth.hasRole('administrator')
    ? [{ action: 'manage', subject: 'all' }]
    : userAbilityRules.value ?? [])

  ability.update(effectiveRules.value)

  // Watch pour mettre à jour l'ability quand les règles changent
  watch(effectiveRules, newRules => {
    if (newRules && Array.isArray(newRules))
      ability.update(newRules)
  }, { deep: true })

  app.use(abilitiesPlugin, ability, {
    useGlobalProperties: true,
  })
}
