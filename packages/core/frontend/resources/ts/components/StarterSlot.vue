<script setup lang="ts">
import type { Component } from 'vue'

const props = defineProps<{ name: string }>()
const slots = inject<Record<string, Component>>('larastarterkit:slots', {})
</script>

<template>
  <Component
    :is="slots[props.name]"
    v-if="slots[props.name]"
  />
  <slot v-else />
</template>
