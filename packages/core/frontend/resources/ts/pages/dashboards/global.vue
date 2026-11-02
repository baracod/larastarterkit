<script setup lang="ts">
import Home from '../index.vue'

definePage({ meta: { authenticatedOnly: true } })
</script>

<template>
  <StarterSlot name="dashboard-start" />
  <Home />
  <StarterSlot name="dashboard-end" />
</template>
