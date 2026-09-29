<script setup lang="ts">
import { navbarComponents } from 'virtual:larastarterkit'
import useNavigationStore from '@/stores'

// Optional modules can contribute a navbar without a dependency in the core layout.
const components = Object.fromEntries(Object.entries(navbarComponents).map(([name, loader]) => [name, defineAsyncComponent(loader)]))
const route = useRoute()
const navigation = useNavigationStore()

const extension = computed(() => {
  const key = route.path.split('/')[1]
  const module = navigation.modules.find(item => item.module?.toLowerCase() === key)

  return module ? components[key] : undefined
})
</script>

<template>
  <Component
    :is="extension"
    v-if="extension"
  />
</template>
