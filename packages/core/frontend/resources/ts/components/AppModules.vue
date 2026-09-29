<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import type { Module } from '@/types/module'

interface Props {
  togglerIcon?: string
  modules: Module[]
}

const props = withDefaults(defineProps<Props>(), {
  togglerIcon: 'bx-grid-alt',
})

const { t } = useI18n()
</script>

<template>
  <IconBtn :aria-label="t('navigation.openModules')">
    <VIcon
      size="22"
      :icon="props.togglerIcon"
    />
    <VMenu
      activator="parent"
      offset="21px"
      location="bottom end"
      width="340"
    >
      <VCard
        :width="$vuetify.display.smAndDown ? 330 : 380"
        max-height="560"
        class="d-flex flex-column"
      >
        <VCardItem class="py-3">
          <h6 class="text-base font-weight-medium">
            {{ t('navigation.modulesTitle') }}
          </h6>

          <template #append>
            <VIcon icon="mdi-apps" />
          </template>
        </VCardItem>

        <VDivider />

        <PerfectScrollbar :options="{ wheelPropagation: false }">
          <VRow class="ma-0 mt-n1">
            <VCol
              v-for="module in props.modules"
              :key="module.title"
              cols="6"
              class="text-center border-t border-e cursor-pointer pa-3 module-icon"
            >
              <VBtn
                :to="module.to"
                variant="text"
                class="h-auto pa-2 w-100 text-none"
                :aria-label="t(`navigation.moduleLabels.${module.title}`)"
              >
                <div>
                  <VAvatar
                    variant="tonal"
                    size="60"
                  >
                    <VIcon
                      size="50"
                      color="high-emphasis"
                      :icon="module.icon"
                    />
                  </VAvatar>

                  <h6 class="text-base font-weight-medium mt-3 mb-0">
                    {{ t(`navigation.moduleLabels.${module.title}`) }}
                  </h6>
                </div>
              </VBtn>
              <!--
                <p class="text-sm mb-0">
                {{ module.subtitle }}
                </p>
              -->
            </VCol>
          </VRow>
          <p
            v-if="!props.modules.length"
            class="pa-4 text-center"
          >
            {{ t('navigation.noModules') }}
          </p>
        </PerfectScrollbar>
      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style lang="scss">
.module-icon:hover {
  background-color: rgba(var(--v-theme-on-surface), var(--v-hover-opacity));
}
</style>
