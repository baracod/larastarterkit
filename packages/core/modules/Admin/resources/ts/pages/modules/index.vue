<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useModuleStore } from '../../stores/modules'

const moduleStore = useModuleStore()
const { notify } = useNotify()
const loading = ref(false)

onMounted(async () => {
  await moduleStore.fetchModules()
})

const handleToggle = async (module: any) => {
  if (module.is_primary)
    return

  loading.value = true
  try {
    await moduleStore.toggleModule(module.name)
    notify({
      type: 'success',
      title: 'Admin.modules.toggle_success',
      message: `Module ${module.name} mis à jour.`,
    })
  }
  catch (err: any) {
    notify({
      type: 'error',
      title: 'Erreur',
      message: err.message,
    })
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <VCard :title="$t('Admin.modules.management')">
    <VTable>
      <thead>
        <tr>
          <th>{{ $t('Admin.modules.status') }}</th>
          <th>Nom</th>
          <th>Description</th>
          <th>Type</th>
          <th class="text-center">
            {{ $t('Admin.modules.actions') }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="module in moduleStore.modules"
          :key="module.name"
        >
          <td>
            <VAvatar
              :color="module.enabled ? 'success' : 'error'"
              size="12"
              class="me-2"
            />
            {{ module.enabled ? $t('Admin.modules.active') : $t('Admin.modules.inactive') }}
          </td>
          <td class="font-weight-bold">
            {{ module.name }}
          </td>
          <td>{{ module.description || 'Pas de description' }}</td>
          <td>
            <VChip
              :color="module.is_primary ? 'info' : 'secondary'"
              size="x-small"
            >
              {{ module.is_primary ? $t('Admin.modules.primary') : $t('Admin.modules.secondary') }}
            </VChip>
          </td>
          <td class="text-center">
            <VSwitch
              v-model="module.enabled"
              :disabled="module.is_primary || loading"
              color="primary"
              density="compact"
              hide-details
              @change="handleToggle(module)"
            />
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCard>
</template>
