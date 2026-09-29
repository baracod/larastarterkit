<script setup lang="ts">
import useNavigationStore from '@/stores'
import { useAuthStore } from '@auth/stores'

const navigation = useNavigationStore()
const auth = useAuthStore()
const ability = useAbility()
const router = useRouter()

const modules = computed(() => navigation.modules.filter(module => {
  const target = module.to as { name?: string }

  return target.name && router.hasRoute(target.name)
    && (auth.hasRole('administrator') || ability.can(module.action, module.subject))
}))
</script>

<template>
  <AppModules :modules="modules" />
</template>
