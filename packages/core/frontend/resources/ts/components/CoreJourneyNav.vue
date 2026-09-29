<script setup lang="ts">
withDefaults(defineProps<{ items?: Array<{ title: string; to: string; icon?: string }> }>(), { items: () => [] })
</script>

<template>
  <nav
    v-if="items.length"
    class="d-flex flex-wrap ga-2 mb-4"
  >
    <VBtn
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      :prepend-icon="item.icon"
      variant="text"
    >
      {{ item.title }}
    </VBtn>
  </nav>
</template>
