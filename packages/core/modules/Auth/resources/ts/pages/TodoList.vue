<route lang="yaml">
meta:
  public: true
</route>

<script setup lang="ts">
import { ref } from 'vue'

const newTask = ref('')

const tasks = ref([
  { id: 1, title: 'Faire les courses', done: false },
  { id: 2, title: 'Terminer le rapport', done: true },
  { id: 3, title: 'Appeler le client', done: false },
])

const addTask = () => {
  if (newTask.value.trim() === '')
    return
  tasks.value.unshift({
    id: Date.now(),
    title: newTask.value,
    done: false,
  })
  newTask.value = ''
}

const removeTask = (id: number) => {
  tasks.value = tasks.value.filter(task => task.id !== id)
}
</script>

<template>
  <VCard title="Liste de Tâches">
    <VCardText>
      <VTextField
        v-model="newTask"
        label="Nouvelle tâche"
        placeholder="Ajouter une nouvelle tâche..."
        @keydown.enter="addTask"
      >
        <template #append>
          <VBtn @click="addTask">
            Ajouter
          </VBtn>
        </template>
      </VTextField>

      <VList lines="two">
        <VListItem
          v-for="task in tasks"
          :key="task.id"
          :class="{ 'text-decoration-line-through': task.done }"
        >
          <template #prepend>
            <VCheckbox v-model="task.done" />
          </template>

          <VListItemTitle>
            {{ task.title }}
          </VListItemTitle>

          <template #append>
            <VBtn
              color="error"
              icon="mdi-delete-outline"
              variant="text"
              @click="removeTask(task.id)"
            />
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.text-decoration-line-through {
  color: #9E9E9E;
  text-decoration: line-through;
}
</style>
