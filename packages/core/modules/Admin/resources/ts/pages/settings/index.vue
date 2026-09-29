<route lang="json">
{
  "name": "admin-settings"
}
</route>

<script setup lang="ts">
import SettingTabDocuments from '../../components/SettingTabDocuments.vue'
import SettingTabModule from '../../components/SettingTabModule.vue'
import SettingTabSystem from '../../components/SettingTabSystem.vue'
import SettingTabUser from '../../components/SettingTabUser.vue'

const selectedTab = ref(0)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Gestion des Paramètres">
        <VTabs
          v-model="selectedTab"
          class="v-tabs-pill"
        >
          <VTab prepend-icon="bx-cog">
            Paramètres Système
          </VTab>
          <VTab prepend-icon="bx-cube">
            Paramètres Modules
          </VTab>
          <VTab prepend-icon="bx-user">
            Paramètres Utilisateurs
          </VTab>
          <VTab prepend-icon="bx-file">
            {{ $t('documentWorkflow.title') }}
          </VTab>
        </VTabs>

        <VWindow
          v-model="selectedTab"
          class="mt-6"
          :touch="false"
        >
          <VWindowItem>
            <SettingTabSystem />
          </VWindowItem>

          <VWindowItem>
            <SettingTabModule />
          </VWindowItem>

          <VWindowItem>
            <SettingTabUser />
          </VWindowItem>
          <VWindowItem>
            <SettingTabDocuments />
          </VWindowItem>
        </VWindow>
      </VCard>
    </VCol>
  </VRow>
</template>
