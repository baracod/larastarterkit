<script setup lang="ts">
const { notifications, remove } = useNotify()
</script>

<template>
  <div class="notify-container">
    <TransitionGroup
      name="notify-list"
      tag="div"
    >
      <VAlert
        v-for="notification in notifications"
        :key="notification.id"
        :type="notification.type"
        :title="notification.title"
        :text="notification.message"
        variant="elevated"
        closable
        class="mb-4"
        max-width="400px"
        @click:close="remove(notification.id)"
      />
    </TransitionGroup>
  </div>
</template>

<style scoped>
.notify-container {
  position: fixed;
  top: 1.5rem;
  right: 1.5rem;
  z-index: 2000; /* Doit être au-dessus des autres éléments */
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

/* Animations pour la TransitionGroup */
.notify-list-enter-active,
.notify-list-leave-active {
  transition: all 0.4s ease-in-out;
}
.notify-list-enter-from,
.notify-list-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
.notify-list-move {
  transition: transform 0.3s ease;
}
</style>
