<script lang="ts" setup>
const notifications = ref([
  {
    type: 'New for you',
    email: true,
    browser: false,
    app: false,
  },
  {
    type: 'Account activity',
    email: false,
    browser: true,
    app: true,
  },
  {
    type: 'A new browser used to sign in',
    email: true,
    browser: true,
    app: true,
  },
  {
    type: 'A new device is linked',
    email: false,
    browser: true,
    app: false,
  },
])
</script>

<template>
  <VCard
    class="user-tab-notification"
    title="Notifications"
  >
    <VCardText class="px-0">
      <VDivider />
      <h1 class="ma-5">
        Cette fonctionnalité est en cours de développement
      </h1>
      <VDivider />
    </VCardText>

    <VCardText class="d-flex flex-wrap gap-4">
      <VBtn>Save changes</VBtn>
      <VBtn
        color="secondary"
        variant="tonal"
      >
        Discard
      </VBtn>
    </VCardText>
  </VCard>
</template>
